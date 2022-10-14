<?php

namespace PhpMegaDriveEmulator\Hardware;

class Z80
{
    public array $memory = [];
    private int $bus_ack = 0;
    private int $reset = 0;

    public function ctrl_read(int $address)
    {
        if ($address == 0x1100) {
            return !($this->reset && $this->bus_ack);
        }
        return 0;
    }

    public function ctrl_write(int $address, int $value)
    {
        if ($address == 0x1100) // BUSREQ
        {
            if ($value) {
                $this->bus_ack = 1;
            } else {
                $this->bus_ack = 0;
            }
        } else if ($address == 0x1200) // RESET
        {
            if ($value) {
                $this->reset = 1;
            } else {
                $this->reset = 0;
            }
        }
    }
}