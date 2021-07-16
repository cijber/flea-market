<?php


namespace Cijber\FleaMarket\KeyValueStorage;


interface Map extends ReadOnlyMap {
    public function insert($key, $value);

    public function delete($key): mixed;

    public function hasAndDelete($key): array;
}