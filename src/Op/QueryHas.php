<?php


namespace Cijber\FleaMarket\Op;


class QueryHas implements QueryOp {
    public function __construct(
      public string $field,
      private bool $index,
    ) {
    }

    public function isOnIndex(): bool {
        return $this->index;
    }

    public function matches(array $object): bool {
        return isset($object[$this->field]);
    }
}