<?php


namespace Cijber\FleaMarket\Op;


use Cijber\FleaMarket\Index;
use Cijber\FleaMarket\KeyValueStorage\Map;


interface QueryIndexOp extends QueryOp {
    public function getIndex(): string;

    public function getOffsets(Index $index, Map $map): iterable;
}