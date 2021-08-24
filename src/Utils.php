<?php


namespace Cijber\FleaMarket;


use JetBrains\PhpStorm\Pure;


class Utils {

    public const UINT40_MAX = 1099511627775;
    public const UINT32_MAX = 4294967295;
    public const UINT24_MAX = 16777215;
    public const UINT16_MAX = 65535;

    public static ?bool $ffi_enabled = null;
    public static mixed $ffi = null;

    #[Pure]
    public static function write40BitNumber(
      int $nr
    ): string {
        return pack('CV', ($nr >> 32), $nr & self::UINT32_MAX);
    }

    #[Pure]
    public static function read40BitNumber(
      $data,
      int $offset = 0
    ): int {
        $x = unpack('Chigh/Vlow', $data, $offset);

        return $x['low'] + ($x['high'] << 32);
    }

    #[Pure]
    public static function write24BitNumber(
      int $nr
    ): string {
        return pack('Cv', ($nr >> 16), $nr & self::UINT16_MAX);
    }

    #[Pure]
    public static function read24BitNumber(
      $data,
      int $offset = 0
    ): int {
        $x = unpack('Chigh/vlow', $data, $offset);

        return $x['low'] + ($x['high'] << 16);
    }

    public static function exchangeFiles(string $from, string $to) {
        if (self::$ffi_enabled === null) {
            self::$ffi_enabled = extension_loaded('ffi');
        }

        if (self::$ffi_enabled && self::$ffi !== false) {
            self::exchangeFilesViaFFI($from, $to);
        } else {
            self::exchangeFilesViaRename($from, $to);
        }
    }

    public static function exchangeFilesViaRename(string $from, string $to) {
        rename($to, $from . ".tmp");
        rename($from, $to);
        rename($from . ".tmp", $from);
    }

    public static function exchangeFilesViaFFI(string $from, string $to) {
        if (self::$ffi === null) {
            try {
                self::$ffi = \FFI::cdef("int renameat2(int olddirfd, const char *oldpath, int newdirfd, const char *newpath, unsigned int flags);", "libc.so.6");
            } catch (\Throwable $exception) {
                self::$ffi         = false;
                self::$ffi_enabled = false;

                self::exchangeFilesViaRename($from, $to);

                return;
            }
        }

        $from_real = realpath($from);
        $to_real   = realpath($to);

        self::$ffi->renameat2(0, $from_real, 0, $to_real, /* RENAME_EXCHANGE (1<<1) */ 2);
    }
}