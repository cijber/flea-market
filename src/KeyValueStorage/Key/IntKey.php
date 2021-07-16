<?php


namespace Cijber\FleaMarket\KeyValueStorage\Key;


use Cijber\FleaMarket\KeyValueStorage\Key;


class IntKey extends Key {
    public function __construct(
      public int $value = 0,
    ) {
    }

    public function keySize(): int {
        return PHP_INT_SIZE;
    }

    public function compareTo($b): int {
        return $this->value <=> $b->value;
    }

    public function serialize(): string {
        return pack('P', $this->value);
    }

    public function load(mixed $value) {
        if (is_numeric($value)) {
            $this->value = intval($value);
        } else {
            throw new \RuntimeException("Invalid integer");
        }
    }

    public function save() {
        return $this->value;
    }


    public static function identity(): static {
        return new static();
    }

    public function unserialize($data) {
        [, $this->value] = unpack('P', $data);
    }

    public function toString(): string {
        return "{$this->value}";
    }
}