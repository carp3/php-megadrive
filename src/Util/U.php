<?php

namespace PhpMegaDriveEmulator\Util;

class U
{
    public static function dumpHexArray(array $input)
    {
        $chars = array_map(function ($value) {
            return base_convert($value ?? 0, 10, 16);
        }, $input);

        return '0x' . join(', 0x', $chars);
    }
}