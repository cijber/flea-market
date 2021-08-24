<?php


namespace Cijber\FleaMarket\Filter;


use Cijber\FleaMarket\Op\OffsetCarrier;


class UniqueFilter {
    private array $history = [];

    public function __invoke(OffsetCarrier $item) {
        if (isset($this->history[$item->getOffset()])) {
            return false;
        }

        $this->history[$item->getOffset()] = true;

        return true;
    }
}