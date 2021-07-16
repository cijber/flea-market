<?php


namespace Cijber\FleaMarket\Op;


interface QueryOp {
    public function isOnIndex(): bool;

    public function matches(array $object): bool;
}