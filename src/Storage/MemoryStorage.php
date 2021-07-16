<?php


namespace Cijber\FleaMarket\Storage;


use Cijber\FleaMarket\BackingStorage;


class MemoryStorage implements BackingStorage {
    private $data = "";
    private $position = 0;

    public function lock(bool $reader = true) {
        // empty
    }

    public function unlock() {
        // empty
    }

    public function flush() {
        // empty
    }

    public function seek(int $offset, int $which = SEEK_SET) {
        $this->position = match ($which) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen($this->data) - $offset,
        };
    }

    public function tell(): int {
        return $this->position;
    }

    public function write(string $data) {
        if (strlen($this->data) === $this->position) {
            $this->data .= $data;
        } else {
            $this->data = substr_replace($this->data, $data, $this->position, strlen($data));
        }

        $this->position += strlen($data);
    }

    public function read(int $size): string {
        $data           = substr($this->data, $this->position, $size);
        $this->position += $size;

        return $data;
    }
}