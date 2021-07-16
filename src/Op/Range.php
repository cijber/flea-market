<?php


namespace Cijber\FleaMarket\Op;


use Cijber\FleaMarket\Comparable;
use JetBrains\PhpStorm\Pure;


class Range {
    private $isComparable = false;

    public function __construct(
      public $from,
      public $to,
      public bool $fromInclusive = true,
      public bool $toInclusive = true,
    ) {
        if ($from instanceof Comparable || $to instanceof Comparable) {
            $this->isComparable = true;
        }
    }


    #[Pure]
    public static function exclusive(
      $from,
      $to
    ): Range {
        return new Range($from, $to, false, false);
    }

    #[Pure]
    public static function inclusive(
      $from,
      $to
    ): Range {
        return new Range($from, $to);
    }

    public function matches(mixed $value): bool {
        if ($this->from === null && $this->to === null) {
            return true;
        }


        if ($this->from !== null) {
            $fromCmp = $this->isComparable ? $this->from->compareTo($value) : $this->from <=> $value;
            if ($fromCmp === 1) {
                return false;
            }

            if (!$this->fromInclusive && $fromCmp === 0) {
                return false;
            }
        }

        if ($this->to !== null) {
            $toCmp = $this->isComparable ? $this->to->compareTo($value) : $this->to <=> $value;
            if ($toCmp === -1) {
                return false;
            }

            if (!$this->toInclusive && $fromCmp === 0) {
                return false;
            }
        }

        return true;
    }
}