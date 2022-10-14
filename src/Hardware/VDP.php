<?php

namespace PhpMegaDriveEmulator\Hardware;

class VDP
{

    private const RAM_TYPE_VRAM = 0;
    private const RAM_TYPE_CRAM = 1;
    private const RAM_TYPE_VSRAM = 2;
    const VH_MAP = [
        0 => 32,
        1 => 64,
        3 => 128
    ];
    const HSCROLL_MAP = [
        0x00 => 0x0000,
        0x01 => 0x0007,
        0x02 => 0xfff8,
        0x03 => 0xffff
    ];
    public array $vdp_reg = [];
    public int $vdp_status = 0x3400;
    public int $screen_width;
    public int $screen_height;
    public array $framebuffer = [];
    private array $VRAM = [];
    private array $CRAM = [];
    private array $VSRAM = [];
    private int $control_code = 0;
    private int $control_address = 0;
    private int $control_pending = 0;
    private int $dma_length;
    private int $dma_source;
    private int $dma_fill = 0;

    public function __construct(private MegaDrive $megaDrive)
    {
        $this->VRAM = array_fill(0, 0x10000, 0);
        $this->CRAM = array_fill(0, 0x40, 0);
        $this->VSRAM = array_fill(0, 0x40, 0);
        $this->vdp_reg = array_fill(0, 0x20, 0);
    }

    public function clear_hblank()
    {
        $this->vdp_status &= ~4;
    }

    public function clear_vblank()
    {
        $this->vdp_status &= ~8;
    }

    public function getCRAM(int $index)
    {
        return $this->CRAM[$index & 0x3f];
    }

    public function read($address)
    {
        $address &= 0x1f;

        if ($address >= 0x04 && $address < 0x08) {
            /* VDP status */
            return $this->vdp_status;
        } else if ($address >= 0x08 && $address < 0x10) {
            $vcounter = MegaDrive::$cycle_counter / MegaDrive::MCYCLES_PER_LINE - 1;
            if ($vcounter > ($this->vdp_reg[1] & 0x08 ? 262 : 234)) {
                $vcounter -= MegaDrive::LINES_PER_FRAME;
            }

            if ($this->vdp_reg[12] & 0x01) {
                $hcounter = 0;
            } else {
                $hcounter = ((MegaDrive::$cycle_counter + 10) % MegaDrive::MCYCLES_PER_LINE) / 20;
                if ($hcounter >= 12)
                    $hcounter += 0x56;
                $hcounter += 0x85;
            }

            if ($address & 1)
                return $hcounter & 0xff;
            else
                return $vcounter & 0xff;
        } else if ($address >= 0x04) {
            printf("unexpected vdp->read(%x)\n", $address);
        }
        return 0;

    }

    public function render_line(int $line)
    {

        SDL_SetRenderDrawColor($this->megaDrive->renderer, ($this->CRAM[$this->vdp_reg[7] & 0x3f] << 4) & 0xe0, ($this->CRAM[$this->vdp_reg[7] & 0x3f]) & 0xe0, ($this->CRAM[$this->vdp_reg[7] & 0x3f] >> 4) & 0xe0, 255);
        $y = (240 - $this->screen_height) / 2 + ($line);
        SDL_RenderDrawLine($this->megaDrive->renderer, (320 - $this->screen_width) / 2, $y, $this->screen_width + (320 - $this->screen_width) / 2, $y);

        $this->render_bg($line, 0);
        $this->render_sprites($line, 0);
        $this->render_bg($line, 1);
        $this->render_sprites($line, 1);
    }

    public function set_hblank()
    {
        $this->vdp_status |= 4;
    }

    public function set_vblank()
    {
        $this->vdp_status |= 8;
    }

    public function write(int $address, int $value): void
    {
        $address &= 0x1f;

        if ($address < 0x04) {
            $this->data_port_write($value);
        } else if ($address >= 0x04 && $address < 0x08) {
            $this->control_write($value);
        } else {
            printf("unexpected vdp->write(%x, %x)\n", $address, $value);
        }
    }

    private function control_write(int $value)
    {
        if (!$this->control_pending) {
            if (($value & 0xc000) == 0x8000) {
                $reg = ($value >> 8) & 0x1f;
                $reg_value = $value & 0xff;

                $this->set_reg($reg, $reg_value);
            } else {
                $this->control_code = ($this->control_code & 0x3c) | (($value >> 14) & 3);
                $this->control_address = ($this->control_address & 0xc000) | ($value & 0x3fff);
                $this->control_pending = 1;
            }
        } else {
            $this->control_code = ($this->control_code & 3) | (($value >> 2) & 0x3c);
            $this->control_address = ($this->control_address & 0x3fff) | (($value & 3) << 14);
            $this->control_pending = 0;

            if (($this->control_code & 0x20) && ($this->vdp_reg[1] & 0x10)) {
                if (($this->vdp_reg[23] >> 6) == 2 && ($this->control_code & 7) == 1) {
                    /* DMA fill */
                    $this->dma_fill = 1;
                } else if (($this->vdp_reg[23] >> 6) == 3) {
                    /* DMA copy */
                    printf("DMA copy\n");
                } else {
                    /* DMA 68k -> VDP */
                    $this->dma_length = $this->vdp_reg[19] | ($this->vdp_reg[20] << 8);
                    $this->dma_source = ($this->vdp_reg[21] << 1) | ($this->vdp_reg[22] << 9) | ($this->vdp_reg[23] << 17);

                    $type = -1;
                    if (($this->control_code & 0x7) == 1) {
                        $type = self::RAM_TYPE_VRAM;
                    } else if (($this->control_code & 0x7) == 3) {
                        $type = self::RAM_TYPE_CRAM;
                    } else if (($this->control_code & 0x7) == 5) {
                        $type = self::RAM_TYPE_VSRAM;
                    }

                    while ($this->dma_length--) {
                        $word = $this->megaDrive->proxyRead16($this->dma_source);
                        $this->dma_source += 2;
                        $this->data_write($word, $type, 1);
                        $this->control_address += $this->vdp_reg[15];
                        $this->control_address &= 0xffff;
                    }
                }
            }
        }
    }

    private function data_port_write(int $value)
    {
        if ($this->control_code & 1)  /* check if write is set */ {
            $type = -1;
            if (($this->control_code & 0xe) == 0)  /* VRAM write */ {
                $type = self::RAM_TYPE_VRAM;
            } else if (($this->control_code & 0xe) == 2)  /* CRAM write */ {
                $type = self::RAM_TYPE_CRAM;
            } else if (($this->control_code & 0xe) == 4)  /* VSRAM write */ {
                $type = self::RAM_TYPE_VSRAM;
            }
            $this->data_write($value, $type, 0);
        }
        $this->control_address = ($this->control_address + $this->vdp_reg[15]) & 0xffff;
        $this->control_pending = 0;

        /* if a DMA is scheduled, do it */
        if ($this->dma_fill) {
            $this->dma_fill = 0;
            $this->dma_length = $this->vdp_reg[19] | ($this->vdp_reg[20] << 8);
            while ($this->dma_length--) {
                $this->VRAM[$this->control_address] = $value >> 8;
                $this->control_address += $this->vdp_reg[15];
                $this->control_address &= 0xffff;
            }
        }
    }

    private function data_write(int $value, int $type, int $dma)
    {
        if ($type == self::RAM_TYPE_VRAM)  /* VRAM write */ {
            $this->VRAM[$this->control_address] = ($value >> 8) & 0xff;
            $this->VRAM[$this->control_address + 1] = ($value) & 0xff;
        } else if ($type == self::RAM_TYPE_CRAM)  /* CRAM write */ {
            $this->CRAM[($this->control_address & 0x7f) >> 1] = $value;
        } else if ($type == self::RAM_TYPE_VSRAM)  /* VSRAM write */ {
            $this->VSRAM[($this->control_address & 0x7f) >> 1] = $value;
        }
    }

    private function draw_cell_pixel(int $cell, int $cell_x, int $cell_y, int $x, int $y)
    {
        $pattern = 0x20 * ($cell & 0x7ff);

        if ($cell & 0x1000)  /* v flip */
            $pattern_index = (7 - ($cell_y & 7)) << 2;
        else
            $pattern_index = ($cell_y & 7) << 2;

        if ($cell & 0x800)  // h flip
            $pattern_index += (7 - ($cell_x & 7)) >> 1;
        else
            $pattern_index += ($cell_x & 7) >> 1;

        $color_index = $this->VRAM[$pattern + $pattern_index];
        $color_index = ($cell_x & 1) ^ (($cell >> 11) & 1) ? $color_index & 0xf : $color_index >> 4;

        if ($color_index) {
            $color_index += ($cell & 0x6000) >> 9;
            $this->set_pixel($this->framebuffer, $x, $y, $color_index);
        }
    }

    private function get_reg(int $reg)
    {
        return $this->vdp_reg[$reg];
    }

    private function render_bg(int $line, int $priority)
    {
        $h_cells = self::VH_MAP[$this->vdp_reg[16] & 3];
        $v_cells = self::VH_MAP[($this->vdp_reg[16] >> 4) & 3];

        $hscroll_table = $this->vdp_reg[13] << 10;
        $hscroll_mask = self::HSCROLL_MAP[$this->vdp_reg[11] & 3];

        if ($this->vdp_reg[11] & 4) {
            $vscroll_mask = 0xfff0;
        } else {
            $vscroll_mask = 0x0000;
        }

        for ($scroll_i = 0; $scroll_i < 2; $scroll_i++) {
            if ($scroll_i == 0) {
                $scroll = $this->vdp_reg[4] << 13;
            } else {
                $scroll = $this->vdp_reg[2] << 10;
            }

            $hscroll = ($this->VRAM[$hscroll_table + ((($line & $hscroll_mask)) * 4 + ($scroll_i ^ 1) * 2)] << 8) | $this->VRAM[$hscroll_table + ((($line & $hscroll_mask)) * 4 + ($scroll_i ^ 1) * 2 + 1)];
            for ($column = 0; $column < $this->screen_width; $column++) {
                $vscroll = $this->VSRAM[($column & $vscroll_mask) / 4 + ($scroll_i ^ 1)] & 0x3ff;
                $e_line = ($line + $vscroll) & ($v_cells * 8 - 1);
                $cell_line = $e_line >> 3;
                $e_column = ($column - $hscroll) & ($h_cells * 8 - 1);
                $cell_column = $e_column >> 3;
                $cell = ($this->VRAM[$scroll + (($cell_line * $h_cells + $cell_column) * 2)] << 8) | $this->VRAM[$scroll + (($cell_line * $h_cells + $cell_column) * 2 + 1)];

                if ((($cell & 0x8000) && $priority) || (($cell & 0x8000) == 0 && $priority == 0))
                    $this->draw_cell_pixel($cell, $e_column, $e_line, $column, $line);
            }
        }
    }

    private function render_sprite(int $sprite_index, int $line)
    {
        $sprite = ($this->vdp_reg[5] << 9) + $sprite_index * 8;

        $y_pos = (($this->VRAM[$sprite] << 8) | $this->VRAM[$sprite + 1]) & 0x3ff;
        $h_size = (($this->VRAM[$sprite + 2] >> 2) & 0x3) + 1;
        $v_size = ($this->VRAM[$sprite + 2] & 0x3) + 1;
        $cell = ($this->VRAM[$sprite + 4] << 8) | $this->VRAM[$sprite + 5];
        $x_pos = (($this->VRAM[$sprite + 6] << 8) | $this->VRAM[$sprite + 7]) & 0x3ff;

        $y = (128 - $y_pos + $line) & 7;
        $cell_y = (128 - $y_pos + $line) >> 3;

        for ($cell_x = 0; $cell_x < $h_size; $cell_x++) {
            for ($x = 0; $x < 8; $x++) {
                $e_x = $cell_x * 8 + $x + $x_pos - 128;
                $e_cell = $cell;

                if ($cell & 0x1000)
                    $e_cell += $v_size - $cell_y - 1;
                else
                    $e_cell += $cell_y;

                if ($cell & 0x800)
                    $e_cell += ($h_size - $cell_x - 1) * $v_size;
                else
                    $e_cell += $cell_x * $v_size;
                if ($e_x >= 0 && $e_x < $this->screen_width) {
                    $this->draw_cell_pixel($e_cell, $x, $y, $e_x, $line);
                }
            }
        }
    }

    private function render_sprites(int $line, int $priority)
    {
        $sprite_table = $this->vdp_reg[5] << 9;

        $sprite_queue = array_fill(0, 50, 0);
        $i = 0;
        $cur_sprite = 0;
        while (true) {
            $sprite = ($this->vdp_reg[5] << 9) + $cur_sprite * 8;
            $y_pos = ($this->VRAM[$sprite] << 8) | $this->VRAM[$sprite + 1];
            $v_size = ($this->VRAM[$sprite + 2] & 0x3) + 1;
            $cell = ($this->VRAM[$sprite + 4] << 8) | $this->VRAM[$sprite + 5];

            $y_min = $y_pos - 128;
            $y_max = ($v_size - 1) * 8 + 7 + $y_min;

            if ($line >= $y_min && $line <= $y_max) {
                if (($cell >> 15) == $priority)
                    $sprite_queue[$i++] = $cur_sprite;
            }

            $cur_sprite = $this->VRAM[$sprite_table + ($cur_sprite * 8 + 3)];
            if (!$cur_sprite)
                break;

            if ($i >= 80)
                break;
        }
        while ($i > 0) {
            $this->render_sprite($sprite_queue[--$i], $line);
        }
    }

    private function set_pixel(array &$framebuffer, int $x, int $y, int $index)
    {
        SDL_SetRenderDrawColor($this->megaDrive->renderer, ($this->CRAM[$index] << 4) & 0xe0, ($this->CRAM[$index]) & 0xe0, ($this->CRAM[$index] >> 4) & 0xe0, 255);
        SDL_RenderDrawPoint($this->megaDrive->renderer, ($x) + (320 - $this->screen_width) / 2, ((240 - $this->screen_height) / 2 + ($y)));
    }

    private function set_reg(int $reg, int $reg_value)
    {
        if ($this->vdp_reg[1] & 4 || $reg <= 10)
            $this->vdp_reg[$reg] = $reg_value;

        $this->control_code = 0;
    }
}