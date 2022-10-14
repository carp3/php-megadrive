<?php

namespace PhpMegaDriveEmulator\Hardware;

class M6800
{
    public function __construct(private Memory $memory)
    {
        $this->init();
        $this->reset();
    }

    public function execute(int $cycles)
    {
        m68k_execute($cycles);
    }

    public function init()
    {
        m68k_init();
        m68k_set_read_memory_8_callback([$this, 'read8']);
        m68k_set_read_memory_16_callback([$this, 'read16']);
        m68k_set_read_memory_32_callback([$this, 'read32']);


        m68k_set_write_memory_8_callback([$this, 'write8']);
        m68k_set_write_memory_16_callback([$this, 'write16']);
        m68k_set_write_memory_32_callback([$this, 'write32']);
    }

    public function reset()
    {
        m68k_pulse_reset();
    }

    public function set_irq(int $level)
    {
        m68k_set_irq($level);
    }

    protected function read16($address)
    {
        return $this->memory->read16($address);
    }

    protected function read32($address)
    {
        return $this->memory->read8($address) << 24 |
            $this->memory->read8($address + 1) << 16 |
            $this->memory->read8($address + 2) << 8 |
            $this->memory->read8($address + 3);
    }

    protected function read8($address)
    {
        return $this->memory->read8($address);
    }

    protected function write16($address, $value)
    {
        $this->memory->write16($address, $value);
    }

    protected function write32($address, $value)
    {
        $this->memory->write16($address, ($value >> 16) & 0xffff);
        $this->memory->write16($address + 2, ($value) & 0xffff);
    }

    protected function write8($address, $value)
    {
        $this->memory->write8($address, $value);
    }
}