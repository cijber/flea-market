<?php


namespace Cijber\FleaMarket\KeyValueStorage;


class MapEntryWithOffsetValue extends MapEntry {

    public function __construct(
      protected RawBTree|RawHashMap $map,
      protected mixed $key,
      private int $valueOffset
    ) {
    }

    public function value(): mixed {
        /** @psalm-type RawBTree|RawHashMap */
        $map = $this->map;

        return $map->readValue($this->valueOffset);
    }

    public function key(): mixed {
        return $this->key;
    }
}