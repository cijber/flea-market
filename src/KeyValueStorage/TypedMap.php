<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use Generator;


abstract class TypedMap implements Map {
    use MapDefaults;


    protected function __construct(
      protected Map $parent,
    ) {
    }

    abstract function serializeValue(mixed $value): string;

    abstract function unserializeValue(string $value): mixed;

    public function insert($key, $value) {
        $this->parent->insert($key, $this->serializeValue($value));
    }

    public function hasAndDelete($key): array {
        [$found, $value] = $this->parent->hasAndDelete($key);
        if ($found) {
            $value = $this->unserializeValue($value);
        }

        return [$found, $value];
    }

    public function hasAndGet($key): array {
        [$found, $value] = $this->parent->hasAndGet($key);
        if ($found) {
            $value = $this->unserializeValue($value);
        }

        return [$found, $value];
    }

    public function has($key): bool {
        return $this->parent->has($key);
    }

    public function range($from, $to, $fromInclusive = true, $toInclusive = true, bool $reverse = false): Generator {
        foreach ($this->parent->range($from, $to, $fromInclusive, $toInclusive, $reverse) as $entry) {
            yield new TypedMapEntry($this, $entry);
        }
    }

    public function entries(): Generator {
        foreach ($this->parent->entries() as $entry) {
            yield new TypedMapEntry($this, $entry);
        }
    }

    public function close(): void {
        $this->parent->close();
    }
}