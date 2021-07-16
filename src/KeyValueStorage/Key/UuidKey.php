<?php


namespace Cijber\FleaMarket\KeyValueStorage\Key;


use Cijber\FleaMarket\KeyValueStorage\Key;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;


class UuidKey extends Key {

    public function __construct(
      public UuidInterface $value,
    ) {
    }

    public function compareTo($b): int {
        return $this->value->compareTo($b->value);
    }

    public function serialize() {
        return $this->value->getBytes();
    }

    public function unserialize($data) {
        $this->value = Uuid::fromBytes($data);
    }

    public function keySize(): int {
        return 16;
    }

    public function load(mixed $value) {
        if (!$value instanceof UuidInterface) {
            throw new \RuntimeException("Key should be a UUID");
        }

        $this->value = $value;
    }

    public function save() {
        return $this->value;
    }

    public static function identity(): static {
        return new static(Uuid::uuid4());
    }

    public function __clone() {
        $this->value = clone $this->value;
    }

    public function toString(): string {
        return $this->value->toString();
    }
}