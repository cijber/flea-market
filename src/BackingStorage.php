<?php


namespace Cijber\FleaMarket;


interface BackingStorage {
    public function lock(bool $reader = true);

    public function unlock();

    public function seek(int $offset, int $which = SEEK_SET);

    public function tell(): int;

    public function write(string $data);

    public function read(int $size): string;

    public function flush();
}