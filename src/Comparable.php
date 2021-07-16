<?php


namespace Cijber\FleaMarket;


interface Comparable {
    public function compareTo($b): int;
}