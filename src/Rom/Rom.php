<?php

namespace PhpMegaDriveEmulator\Rom;

use PhpMegaDriveEmulator\Util\U;
use Exception;

class Rom
{
    const ROM_CONSOLE = 256;
    const ROM_COPYRIGHT = 272;
    const ROM_DOMESTIC = 288;
    const ROM_WORLD = 336;
    const ROM_TYPE = 384;
    const ROM_PRODUCT = 386;
    const ROM_CHECKSUM = 398;
    const ROM_IOSUPPORT = 400;
    const ROM_ROM_START = 416;
    const ROM_ROM_END = 420;
    const ROM_RAMINFO = 424;
    const ROM_RAMSTART = 436;
    const ROM_RAMEND = 440;
    const ROM_MODEMINFO = 444;
    const ROM_MEMO = 456;
    const ROM_COUNTRY = 496;

    /**
     * @var int[]
     */
    private array $rom;

    public function __construct(string $rom)
    {
        $this->rom = array_values(unpack('C*', $rom));
    }

    public function getGlobalGameName(): string
    {
        return $this->_readAndTrim(self::ROM_WORLD, 48);

    }

    private function _readAndTrim(int $address, int $length): string
    {
        return trim(preg_replace('/\s+/', ' ', $this->_readAndPack($address, $length)));
    }

    private function _readAndPack(int $address, int $length): string
    {
        return pack('C*', ... array_slice($this->rom, $address, $length));
    }

    public function getCountry(): string
    {
        return $this->_readAndTrim(self::ROM_COUNTRY, 16);
    }

    public function getConsole(): string
    {
        return $this->_readAndTrim(self::ROM_CONSOLE, 16);
    }

    public function getRomStart(): int
    {
        return unpack('L', $this->_readAndPack(self::ROM_ROM_START, 4))[1];
    }

    public function read(int $address): array|string
    {
        if (!isset($this->rom[$address])) {
            throw new Exception('ROM address violation');
        }
        $result = $this->rom[$address];
        if (DEBUG) {
            echo "Read ROM: " . dechex($address) .' Content: ' . U::dumpHexArray([$result]) . PHP_EOL;
        }
        return $result;
    }

    public function write(int $address, int $value) {
        $this->rom[$address] = $value;
    }

    public function getRomEnd(): int
    {
        return unpack('N', $this->_readAndPack(self::ROM_ROM_END, 4))[1];
    }

}