<?php


namespace Cijber\FleaMarket\KeyValueStorage\Key;


use Cijber\FleaMarket\KeyValueStorage\Key;


class StringKey extends Key {
    public function __construct(
      private string $value = "",
      private int $size = 255,
    ) {
    }

    public function compareTo($b): int {
        return strcmp($this->value, $b->value);
    }

    public function serialize() {
        return $this->value . str_repeat("\x00", $this->size - strlen($this->value));
    }

    public function unserialize($data) {
        $this->value = rtrim($data, "\x00");
    }

    public function keySize(): int {
        return $this->size;
    }

    public function load(mixed $value) {
        $this->value = $value;
    }

    public function save() {
        return $this->value;
    }

    public static function identity(): static {
        return self::withSize(255);
    }

    public static function withSize(int $size): static {
        return new self(size: $size);
    }

    public function toString(): string {
        return rtrim($this->value, "\x00");
    }
}