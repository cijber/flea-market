<?php


namespace Cijber\FleaMarket\Op;


use Cijber\FleaMarket\Index;
use Cijber\FleaMarket\KeyValueStorage\Map;
use Cijber\FleaMarket\KeyValueStorage\MapEntry;
use Cijber\FleaMarket\Utils;

use function iter\flatMap;


class QueryRange implements QueryIndexOp {
    public function __construct(
      public string $field,
      public Range $range,
      public bool $index = false,
      public bool $reversed = false,
    ) {
    }

    public function isOnIndex(): bool {
        return $this->index;
    }

    public function matches(array $object): bool {
        return isset($object[$this->field]) && $this->range->matches($object[$this->field]);
    }

    public function getIndex(): string {
        return $this->field;
    }

    public function getOffsets(Index $index, Map $map): iterable {
        $from = $this->range->from !== null ? $index->processInput($this->range->from) : null;
        $to   = $this->range->to !== null ? $index->processInput($this->range->to) : null;

        return flatMap(
          function(MapEntry $item) use ($index) {
              $index_value = $item->key();
              $offsets     = str_split($item->value(), 5);

              foreach ($offsets as $offset) {
                  if (strlen($offset) !== 5) {
                      continue;
                  }
                  yield new OffsetCarrier(Utils::read40BitNumber($offset), [$index->getName() => $index_value]);
              }
          },
          $map->range($from, $to, $this->range->fromInclusive, $this->range->toInclusive, $this->reversed)
        );
    }
}