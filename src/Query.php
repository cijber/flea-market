<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\Op\QueryEq;
use Cijber\FleaMarket\Op\QueryFilter;
use Cijber\FleaMarket\Op\QueryHas;
use Cijber\FleaMarket\Op\QueryIn;
use Cijber\FleaMarket\Op\QueryIndexOp;
use Cijber\FleaMarket\Op\QueryNot;
use Cijber\FleaMarket\Op\QueryOp;
use Cijber\FleaMarket\Op\QueryRange;
use Cijber\FleaMarket\Op\Range;
use Closure;


class Query {
    /** @var QueryOp[] */
    private array $operations = [];

    /** @var QueryIndexOp[] */
    private array $indexOperations = [];

    public function __construct(private StallInterface $stall) {
    }

    public function range(string $field, ?Range $range = null, bool $index = false, bool $reversed = false): static {
        return $this->addOperation(new QueryRange($field, $range ?: RAnge::all(), $index, $reversed));
    }

    public function eq(string $field, $value, bool $index = false): static {
        return $this->addOperation(new QueryEq($field, $value, $index));
    }

    public function filter(Closure $filter): static {
        $this->addOperation(new QueryFilter($filter));
    }

    /** @return QueryOp[] */
    public function getOperations(): array {
        return $this->operations;
    }

    /**
     * @return QueryIndexOp[]
     */
    public function getIndexOperations(): array {
        return $this->indexOperations;
    }

    public function first(): ?array {
        foreach ($this->execute() as $item) {
            return $item;
        }

        return null;
    }

    public function execute(): iterable {
        return $this->stall->runQuery($this);
    }

    public function has(string $field, bool $index = false): static {
        return $this->addOperation(new QueryHas($field, $index));
    }

    public function matches($object): bool {
        foreach ($this->operations as $operation) {
            if ( ! $operation->matches($object)) {
                return false;
            }
        }

        return true;
    }

    private function addOperation(QueryOp $op): static {
        if ($op->isOnIndex()) {
            $this->indexOperations[] = $op;
        } else {
            $this->operations[] = $op;
        }

        return $this;
    }

    public function in(string $field, array $values, bool $index = false): static {
        return $this->addOperation(new QueryIn($field, $values, $index));
    }

    public function not(callable $query): static {
        return $this->addOperation(new QueryNot($query($this->stall->find())));
    }

    public function copy(): static {
        return clone $this;
    }

    public function removeOperationsOnIndex(string $index): void {
        $new = [];
        foreach ($this->indexOperations as $index_operation) {
            if ($index_operation->getIndex() === $index) {
                continue;
            }
            $new[] = $index_operation;
        }

        $this->indexOperations = $new;
    }
}