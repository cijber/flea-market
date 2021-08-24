<?php

namespace Cijber\FleaMarket;

use Cijber\FleaMarket\KeyValueStorage\Key;
use Cijber\FleaMarket\Op\OffsetCarrier;
use Generator;
use NoRewindIterator;

use function iter\toIter;


function intersectOffsetCarriers(iterable $iterable, iterable $input): Generator {
    $items = [];

    // a no rewind iterator so we continue the inner foreach on cache miss
    $iterable = new NoRewindIterator(toIter($iterable));

    /** @var OffsetCarrier $item */
    foreach ($input as $item) {
        if (isset($items[$item->getOffset()])) {
            yield $item->merge($items[$item->getOffset()]);
        }

        /** @var OffsetCarrier $value */
        foreach ($iterable as $value) {
            $items[$value->getOffset()] = $value;
            if ($item->getOffset() == $value->getOffset()) {
                yield $item->merge($items[$item->getOffset()]);
                break;
            }
        }
    }
}

function reorder(Index $index, bool $reverse, iterable ...$iterables): iterable {
    $mine = [];
    /** @var OffsetCarrier[] $queue */
    $queue = [];

    $identity = Key::get($index->getKey());


    foreach ($iterables as $i => $iterable) {
        $x = toIter($iterable);
        if ($x->valid()) {
            $mine["X$i"]  = $x;
            $queue["X$i"] = clone $identity;
            $queue["X$i"]->load($x->current()[1]->getIndexes()[$index->getName()]);
        }
    }

    $cmp = $reverse ? -1 : 1;

    while ( ! empty($mine)) {
        if (count($mine) === 1) {
            yield from array_pop($mine);

            return;
        }

        $first   = true;
        $nextKey = null;
        $nextX   = null;
        foreach ($queue as $i => $item) {
            if ($first) {
                $first   = false;
                $nextKey = $item;
                $nextX   = $i;
                continue;
            }

            if ($nextKey->compareTo($item) === $cmp) {
                $nextKey = $item;
                $nextX   = $i;
            }
        }

        $i = $nextX;

        yield $mine[$i]->current();
        $mine[$i]->next();
        if ($mine[$i]->valid()) {
            $queue[$i] = clone $identity;
            $queue[$i]->load($mine[$i]->current()[1]->getIndexes()[$index->getName()]);
        } else {
            unset($mine[$i]);
            unset($queue[$i]);
        }
    }
}