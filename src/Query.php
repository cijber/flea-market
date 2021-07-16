<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\Op\QueryEq;
use Cijber\FleaMarket\Op\QueryHas;
use Cijber\FleaMarket\Op\QueryNot;
use Cijber\FleaMarket\Op\QueryOp;
use Cijber\FleaMarket\Op\QueryRange;
use Cijber\FleaMarket\Op\Range;
use Generator;


class Query {
    /** @var QueryOp[] */
    private array $operations = [];

    public function __construct(private StallInterface $stall) {
    }

    public function range(string $field, Range $range, bool $index = false): static {
        $this->operations[] = new QueryRange($field, $range, $index);

        return $this;
    }

    public function eq(string $field, $value, bool $index = false): static {
        $this->operations[] = new QueryEq($field, $value, $index);

        return $this;
    }

    /** @return QueryOp[] */
    public function getOperations(): array {
        return $this->operations;
    }

    public function execute(): iterable {
        return $this->stall->runQuery($this);
    }

    public function has(string $field, bool $index = false): static {
        $this->operations[] = new QueryHas($field, $index);

        return $this;
    }

    public function matches($object) {
        foreach ($this->operations as $operation) {
            if ($operation->isOnIndex()) {
                continue;
            }

            if (!$operation->matches($object)) {
                return false;
            }
        }

        return true;
    }

    public function not(callable $query): static {
        $this->operations[] = new QueryNot($query($this->stall->find()));

        return $this;
    }
}