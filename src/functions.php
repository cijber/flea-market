<?php

namespace Cijber\FleaMarket;

use Generator;

use function iter\toIter;


function cmp(Comparable $a, $op, Comparable $b): bool {
    $cmp = $a->compareTo($b);

    return match ($op) {
        '>' => $cmp === 1,
        '>=' => $cmp >= 0,
        '==', '===' => $cmp === 0,
        '<=' => $cmp <= 0,
        '<' => $cmp === -1,
    };
}

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