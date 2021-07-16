<?php


namespace Cijber\FleaMarket\Op;


class QueryRange implements QueryOp {
    public function __construct(
      public string $field,
      public Range $range,
      public bool $index,
    ) {
    }

    public function isOnIndex(): bool {
        return $this->index;
    }

    public function matches(array $object): bool {
        return isset($object[$this->field]) && $this->range->matches($object[$this->field]);
    }
}