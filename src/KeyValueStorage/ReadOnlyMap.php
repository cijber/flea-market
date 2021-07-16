<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use Generator;


interface ReadOnlyMap {

    public function get($key): mixed;

    public function hasAndGet($key): array;

    public function has($key): bool;

    public function keys(): Generator;

    public function entries(): Generator;

    public function values(): Generator;

    public function range($from, $to, bool $fromInclusive = true, bool $toInclusive = true, bool $reverse = false): Generator;
}