<?php

namespace PhpMegaDriveEmulator\Hardware;

use Exception;
use PhpMegaDriveEmulator\Rom\Rom;
use PhpMegaDriveEmulator\Util\U;

class Memory
{
    const ROM_MIN = 0x00;
    const ROM_MAX = 0x3F;
    const Z80 = 0xA0;
    const Z80_SPACE_MIN = 0xa00000;
    const Z80_SPACE_Max = 0xa04000;
    const IO = 0xa1;
    const IO_MIN = 0xa10000;
    const IO_MAX = 0xa10020;
    const IO_Z80_MIN = 0xa11100;
    const IO_Z80_MAX = 0xa11300;
    const VDP_MIN = 0xC0;
    const VDP_MAX = 0xDF;
    const RAM_MIN = 0xE0;
    const RAM_MAX = 0xFF;


    private array $memory = [];

    public function __construct(private Rom $rom, private VDP $VDP, private Z80 $z80, private IO $io)
    {
    }

    public function read16(int $address): int
    {
        $range = ($address & 0xff0000) >> 16;
        if ($range >= self::VDP_MIN && $range <= self::VDP_MAX) { // A hack to allow VDP to process 16-bit data
            return $this->VDP->read($address);
        }
        return $this->read8($address) << 8 | $this->read8($address + 1);
    }

    public function read8(int $address)
    {
        $range = ($address & 0xff0000) >> 16;
        if ($range >= self::RAM_MIN && $range <= self::RAM_MAX) {
            $result = $this->memory[$address & 0xffff];
            $bank = 'RAM';
        } elseif ($range <= self::ROM_MAX) {
            $result = $this->rom->read($address);
            $bank = 'ROM(M)';
        } elseif ($range == self::Z80) {
            $result = 0;
            if ($address >= 0xa00000 && $address < 0xa04000) {
                $result = $this->z80->memory[$address & 0x1fff] ?? 0;
            }
            $bank = 'Z80';
        } elseif ($range == self::IO) {
            $result = 0;
            if ($address >= 0xa10000 && $address < 0xa10020) {
                $result = $this->io->read($address & 0x1f);
            } elseif ($address >= 0xa11100 && $address < 0xa11300) {
                $result = $this->z80->ctrl_read($address & 0xffff);
            }
            $bank = 'Z80';
        } elseif ($range >= self::VDP_MIN && $range <= self::VDP_MAX) { // A hack to allow VDP to process 16-bit data
            $result = $this->VDP->read($address);
            $bank = 'VPD';
        }
        if (!isset($result)) {
            throw new Exception('Memory address violation: ' . $address . ' (' . dechex($address) . ') Bank:' . $bank);
        }

        if (DEBUG) {
            echo "Read $bank:  0x" . dechex($address) . '(' . $address . ')  Content: ' . U::dumpHexArray([$result]) . PHP_EOL;
        }

        return $result;
    }

    public function write16(int $address, int $value)
    {
        $range = ($address & 0xff0000) >> 16;
        if ($range >= self::VDP_MIN && $range <= self::VDP_MAX) { // A hack to allow VDP to process 16-bit data
            $this->VDP->write($address, $value);
            return;
        }
        $this->write8($address, ($value >> 8) & 0xff);
        $this->write8($address + 1, ($value) & 0xff);
    }

    public function write8(int $address, int $value)
    {
        $range = ($address & 0xff0000) >> 16;
        $initialAddress = $address;
        if ($range >= 0xe0 && $range <= 0xff) {
            $this->memory[$address & 0xffff] = $value;
            $bank = 'RAM';
        } elseif ($range <= self::ROM_MAX) {
            $this->rom->write($address, $value);
            $bank = 'ROM';
        } elseif ($range == self::Z80) {
            if ($address >= 0xa00000 && $address < 0xa04000) {
                $this->z80->memory[$address & 0x1fff] = $value;
            }
            $bank = 'Z80';
        } elseif ($range == self::IO) {
            if ($address >= 0xa10000 && $address < 0xa10020) {
                $this->io->wirte($address & 0x1f, $value);
            } elseif ($address >= 0xa11100 && $address < 0xa11300) {
                $this->z80->ctrl_write($address & 0xffff, $value);
            }
            $bank = 'IO';
        } elseif ($range >= self::VDP_MIN && $range <= self::VDP_MAX) {
            $bank = 'VPD'; // we won't process 8bit VPD requests.
        }

        if (DEBUG) {
            echo "Write $bank:  0x" . dechex($initialAddress) . '(' . $address . ') Content: ' . U::dumpHexArray([$value]) . PHP_EOL;
        }
    }
}