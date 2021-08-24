<?php


namespace Cijber\FleaMarket\Op;


use Cijber\FleaMarket\Index;
use Cijber\FleaMarket\KeyValueStorage\Map;
use Cijber\FleaMarket\Utils;

use function iter\toIter;


class QueryEq implements QueryIndexOp {
    public function __construct(
      public string $field,
      public $value,
      public bool $index,
    ) {
    }

    public function isOnIndex(): bool {
        return $this->index;
    }

    public function matches(array $object): bool {
        return isset($object[$this->field]) && $this->value == $object[$this->field];
    }

    public function getIndex(): string {
        return $this->field;
    }

    public function getOffsets(Index $index, Map $map): iterable {
        $input_value = $index->processInput($this->value);

        [$found, $items] = $map->hasAndGet($input_value);

        if ( ! $found || strlen($items) === 0) {
            return toIter([]);
        }

        return \iter\map(fn($x) => new OffsetCarrier(Utils::read40BitNumber($x), [$index->getName() => $input_value]), str_split($items, 5));
    }
}