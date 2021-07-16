<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use RuntimeException;


abstract class MapEntry implements \ArrayAccess {
    abstract public function key(): mixed;

    abstract public function value(): mixed;

    public function offsetExists($offset) {
        return $offset === 0 || $offset === 1;
    }

    public function offsetGet($offset) {
        return match ($offset) {
            0 => $this->key(),
            1 => $this->value(),
            default => null
        };
    }

    public function offsetSet($offset, $value) {
        throw new RuntimeException("Map entry is read-only");
    }

    public function offsetUnset($offset) {
        // noop
    }
}