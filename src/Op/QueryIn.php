<?php


namespace Cijber\FleaMarket\Op;


use Cijber\FleaMarket\Index;
use Cijber\FleaMarket\KeyValueStorage\Map;
use Cijber\FleaMarket\Utils;


class QueryIn implements QueryIndexOp {
    public function __construct(
      public string $field,
      public array $value,
      public bool $index,
    ) {
    }

    public function isOnIndex(): bool {
        return $this->index;
    }

    public function matches(array $object): bool {
        return isset($object[$this->field]) && in_array($object[$this->field], $this->value, true);
    }

    public function getIndex(): string {
        return $this->field;
    }

    public function getOffsets(Index $index, Map $map): iterable {
        foreach ($this->value as $value) {
            $input_value = $index->processInput($value);
            [$found, $items] = $map->hasAndGet($input_value);
            if ($found && strlen($items) !== 0) {
                yield from \iter\map(fn($x) => new OffsetCarrier(Utils::read40BitNumber($x), [(string)$index->getName() => $input_value]), str_split($items, 5));
            }
        }
    }
}