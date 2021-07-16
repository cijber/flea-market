<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use Cijber\FleaMarket\Comparable;
use Serializable;


abstract class Key implements Comparable, Serializable {
    abstract public function keySize(): int;

    abstract public function load(mixed $value);

    abstract public function save();

    abstract public static function identity(): static;

    abstract public function toString(): string;
}