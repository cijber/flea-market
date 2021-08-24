<?php


namespace Cijber\FleaMarket\Op;


use JetBrains\PhpStorm\Pure;


class OffsetCarrier {
    public function __construct(
      private int $offset,
      private array $indexes = [],
    ) {
    }

    public function getOffset(): int {
        return $this->offset;
    }

    public function getIndexes(): array {
        return $this->indexes;
    }

    #[Pure]
    public function merge(OffsetCarrier $carrier): OffsetCarrier {
        return new OffsetCarrier($this->getOffset(), array_merge($this->getIndexes(), $carrier->getIndexes()));
    }
}