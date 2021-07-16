<?php


namespace Cijber\FleaMarket\KeyValueStorage;


class TypedMapEntry extends MapEntry {
    private $cache = null;
    private $isCached = false;

    public function __construct(private TypedMap $map, private MapEntry $entry) {
    }

    public function key(): mixed {
        return $this->entry->key();
    }

    public function value(): mixed {
        if (!$this->isCached) {
            $this->isCached = true;
            $this->cache    = $this->map->unserializeValue($this->entry->value());
        }

        return $this->cache;
    }
}