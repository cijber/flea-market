<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use Cijber\FleaMarket\BackingStorage;
use Cijber\FleaMarket\Utils;
use JetBrains\PhpStorm\Pure;


class U40HashMap extends TypedMap {

    public function __construct(BackingStorage $storage, int $size = 12) {
        parent::__construct(new RawHashMap($storage, $size));
    }

    #[Pure]
    public function serializeValue(
      mixed $value
    ): string {
        return Utils::write40BitNumber($value);
    }

    public function unserializeValue(string $value): int {
        return Utils::read40BitNumber($value);
    }
}