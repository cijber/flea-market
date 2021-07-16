<?php


namespace Cijber\FleaMarket\Op;


use Cijber\FleaMarket\Query;


class QueryNot implements QueryOp {
    public function __construct(private Query $query) {
    }

    public function isOnIndex(): bool {
        return false;
    }

    public function matches(array $object): bool {
        return !$this->query->matches($object);
    }
}