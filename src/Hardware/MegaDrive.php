<?php

namespace PhpMegaDriveEmulator\Hardware;

use PhpMegaDriveEmulator\Rom\Rom;

class MegaDrive
{
    public const MCYCLES_PER_LINE = 3420;
    public const LINES_PER_FRAME = 313;
    public static int $cycle_counter = 0;
    private M6800 $m6800;
    private VDP $VDP;
    private Memory $memory;
    private Z80 $Z80;
    private IO $IO;

    public function __construct(private Rom $rom, public $renderer)
    {
        $this->VDP = new VDP($this);
        $this->Z80 = new Z80();
        $this->IO = new IO();
        $this->memory = new Memory($this->rom, $this->VDP, $this->Z80, $this->IO);
        $this->m6800 = new M6800($this->memory);
    }

    public function frame()
    {
        global $renderer;

        $hint_counter = $this->VDP->vdp_reg[10];

        self::$cycle_counter = 0;

        $this->VDP->screen_width = ($this->VDP->vdp_reg[12] & 0x01) ? 320 : 256;
        $this->VDP->screen_height = ($this->VDP->vdp_reg[1] & 0x08) ? 240 : 224;

        $this->VDP->clear_vblank();
        SDL_SetRenderDrawColor($renderer, 0, 0, 0, 255);

        SDL_RenderClear($renderer);

        for ($line = 0; $line < $this->VDP->screen_height; $line++) {
            $this->m6800->execute(2560 + 120);

            if (--$hint_counter < 0) {
                $hint_counter = $this->VDP->vdp_reg[10];
                if ($this->VDP->vdp_reg[0] & 0x10) {
                    $this->m6800->set_irq(4); /* HInt */
                }
            }

            $this->VDP->set_hblank();
            $this->m6800->execute(64 + 313 + 259); /* HBlank */
            $this->VDP->clear_hblank();

            $this->m6800->execute(104);

            $this->VDP->render_line($line); /* render line */
        }

        $this->VDP->set_vblank();

        $this->m6800->execute(588);

        $this->VDP->vdp_status |= 0x80;

        $this->m6800->execute(200);

        if ($this->VDP->vdp_reg[1] & 0x20) {
            $this->m6800->set_irq(6); /* HInt */
        }

        $this->m6800->execute(3420 - 788);
        $line++;

        for (; $line < self::LINES_PER_FRAME; $line++) {
            $this->m6800->execute(3420); /**/
        }
        return $this->VDP->framebuffer;
    }

    public function pressKey(int $key)
    {
        $this->IO->pad_press_button(0, $key);
    }

    public function proxyRead16(int $address)
    {
        return $this->memory->read16($address);
    }

    public function releaseKey(int $key)
    {
        $this->IO->pad_release_button(0, $key);
    }

}