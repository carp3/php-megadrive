<?php

namespace PhpMegaDriveEmulator\Hardware;

class IO
{
    public const PAD_UP = 0;
    public const PAD_DOWN = 1;
    public const PAD_LEFT = 2;
    public const PAD_RIGHT = 3;
    public const PAD_B = 4;
    public const PAD_C = 5;
    public const PAD_A = 6;
    public const PAD_S = 7;

    private array $io_reg = [0xa0, 0x7f, 0x7f, 0x7f, 0, 0, 0, 0xff, 0, 0, 0xff, 0, 0, 0xff, 0, 0];
    private array $button_state = [0, 0, 0];
    private array $pad_state = [0, 0, 0];

    public function pad_press_button(int $pad, int $button)
    {
        echo 'Pad: ' . $pad . ':' . $button . ' Pressed' . PHP_EOL;
        $this->button_state[$pad] |= (1 << $button);
    }

    public function pad_release_button(int $pad, int $button)
    {
        echo 'Pad: ' . $pad . ':' . $button . ' Released' . PHP_EOL;
        $this->button_state[$pad] &= ~(1 << $button);
    }

    public function read(int $address)
    {
        $address >>= 1;

        if ($address >= 0x1 && $address < 0x4) {
            $mask = 0x80 | $this->io_reg[$address + 3];
            $value = $this->io_reg[$address] & $mask;
            $value |= $this->pad_read($address - 1) & ~$mask;
            return $value;
        } else {
            return $this->io_reg[$address];
        }
    }

    public function wirte(int $address, int $value)
    {
        $address >>= 1;

        if ($address >= 0x1 && $address < 0x4) {
            /* port data */
            $this->io_reg[$address] = $value;
            $this->pad_write($address - 1, $value);
            return;
        } else if ($address >= 0x4 && $address < 0x7) {
            /* port ctrl */
            if ($this->io_reg[$address] != $value) {
                $this->io_reg[$address] = $value;
                $this->pad_write($address - 4, $this->io_reg[$address - 3]);
            }
            return;
        }

//        printf("io_write_memory(%x, %x)\n", $address, $value);
    }

    private function pad_read(int $pad)
    {
        $value = $this->pad_state[$pad] & 0x40;
        $value |= 0x3f;

        if ($value & 0x40) {
            $value &= ~($this->button_state[$pad] & 0x3f);
        } else {
            $value &= ~(0xc | ($this->button_state[$pad] & 3) | (($this->button_state[$pad] >> 2) & 0x30));
        }
        return $value;
    }

    private function pad_write(int $pad, int $value)
    {
        $mask = $this->io_reg[$pad + 4];

        $this->pad_state[$pad] &= ~$mask;
        $this->pad_state[$pad] |= $value & $mask;
    }
}