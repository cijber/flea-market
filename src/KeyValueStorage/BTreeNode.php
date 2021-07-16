<?php


namespace Cijber\FleaMarket\KeyValueStorage;


class BTreeNode {
    public int $offset = 0;
    public int $size = 0;

    public function __construct(
      public bool $leaf = true,
      public bool $root = false,
    ) {
    }

    /** @var int[] */
    public array $children = [];

    /** @var Key[] */
    public array $keys = [];

    /** @var int[] */
    public array $values = [];
}