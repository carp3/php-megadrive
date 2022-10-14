<?php

namespace PhpMegaDriveEmulator\Rom;


use InvalidArgumentException;

class Loader
{
    const SUPPORTED_CONSOLES = ['SEGA MEGA DRIVE', 'SEGA GENESIS'];

    public function __construct(private string $romFile)
    {
        if (!file_exists($this->romFile)) {
            throw new InvalidArgumentException();
        }
    }

    public function load(): Rom
    {
        $rom = new Rom(file_get_contents($this->romFile));
        if (!in_array($rom->getConsole(), self::SUPPORTED_CONSOLES)) {
            throw new \Exception('Console not supported.');
        }
        return $rom;
    }
}
