<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\KeyValueStorage\Key\IntKey;
use Generator;


interface StallInterface {
    public function insert(array $document): array;

    public function rangeIndex(string $name, $key = IntKey::class, bool $new = false): Index;

    public function index(string $name, bool $new = false): Index;

    public function find(): Query;

    public function runQuery(Query $query): iterable;
}