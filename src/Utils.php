<?php


namespace Cijber\FleaMarket;


class Utils {

    public const UINT40_MAX = 1099511627775;
    public const UINT32_MAX = 4294967295;
    public const UINT24_MAX = 16777215;
    public const UINT16_MAX = 65535;

    public static function write40BitNumber(int $nr): string {
        return pack('CV', ($nr >> 32), $nr & self::UINT32_MAX);
    }

    public static function read40BitNumber($data, int $offset = 0): int {
        $x = unpack('Chigh/Vlow', $data, $offset);

        return $x['low'] + ($x['high'] << 32);
    }

    public static function write24BitNumber(int $nr): string {
        return pack('Cv', ($nr >> 16), $nr & self::UINT16_MAX);
    }

    public static function read24BitNumber($data, int $offset = 0): int {
        $x = unpack('Chigh/vlow', $data, $offset);

        return $x['low'] + ($x['high'] << 16);
    }
}