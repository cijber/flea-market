<?php


namespace Cijber\FleaMarket\Filter;


class UniqueFilter {
    private array $history = [];

    public function __invoke($item) {
        if (array_key_exists($item, $this->history)) {
            return false;
        }

        $this->history[$item] = true;

        return true;
    }
}