<?php


namespace Cijber\FleaMarket\Op;


use Cijber\FleaMarket\Index;
use Cijber\FleaMarket\KeyValueStorage\Map;
use Cijber\FleaMarket\Utils;

use function iter\filter;
use function iter\flatMap;


class QueryHas implements QueryIndexOp {
    public function __construct(
      public string $field,
      private bool $index,
    ) {
    }

    public function isOnIndex(): bool {
        return $this->index;
    }

    public function matches(array $object): bool {
        return isset($object[$this->field]);
    }

    public function getIndex(): string {
        return $this->field;
    }

    public function getOffsets(Index $index, Map $map): iterable {
        $new_offsets = flatMap(fn($item) => str_split($item, 5), $map->values());
        $new_offsets = filter(fn($item) => strlen($item) === 5, $new_offsets);

        return \iter\map(fn($item) => new OffsetCarrier(Utils::read40BitNumber($item)), $new_offsets);
    }
}