<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use Generator;


trait MapDefaults {
    public function get($key): mixed {
        [$found, $value] = $this->hasAndGet($key);

        return $found ? $value : null;
    }

    public function delete($key): mixed {
        [$found, $value] = $this->hasAndDelete($key);

        return $found ? $value : null;
    }

    public function values(): Generator {
        foreach ($this->entries() as $entry) {
            yield $entry->value();
        }
    }

    public function keys(): Generator {
        foreach ($this->entries() as $entry) {
            yield $entry->key();
        }
    }

    public function range($from, $to, $fromInclusive = true, $toInclusive = true): Generator {
        if ($from === null && $to === null)
            return $this->entries();

        /** @var MapEntry $entry */
        foreach ($this->entries() as $entry) {
            $key = $entry->key();
            if ($from !== null && !($key > $from || ($fromInclusive && $key === $from))) {
                continue;
            }

            if ($to !== null && !($key < $to || ($toInclusive && $key === $to))) {
                continue;
            }

            yield $entry;
        }
    }
}