<?php


namespace Cijber\FleaMarket\Op;


class QueryEq implements QueryOp {
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
}