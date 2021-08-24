<?php


namespace Cijber\FleaMarket\Op;


use Closure;


class QueryFilter implements QueryOp {
    /**
     * QueryFilter constructor.
     *
     * @param Closure{array, bool} $filter
     */
    public function __construct(
      private Closure $filter,
    ) {
    }

    public function isOnIndex(): bool {
        return false;
    }

    public function matches(array $object): bool {
        return ($this->filter)($object);
    }
}