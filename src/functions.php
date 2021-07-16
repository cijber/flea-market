<?php

namespace Cijber\FleaMarket;

use Generator;

use function iter\toIter;


function intersect(iterable $iterable, iterable $input): Generator {
    $items = [];

    $iterable = toIter($iterable);

    foreach ($input as $item) {
        if (isset($items[$item])) {
            yield $item;
        }

        foreach ($iterable as $value) {
            $items[$value] = true;
            if ($item == $value) {
                yield $item;
                break;
            }
        }
    }
}