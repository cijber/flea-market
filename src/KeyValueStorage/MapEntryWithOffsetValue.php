<?php


namespace Cijber\FleaMarket\KeyValueStorage;


class MapEntryWithOffsetValue extends MapEntry {
    public function __construct(protected ReadOnlyMap $map, protected mixed $key, private int $valueOffset) {
    }

    public function value(): mixed {
        return $this->map->readValue($this->valueOffset);
    }

    public function key(): mixed {
        return $this->key;
    }
}