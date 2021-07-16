<?php


namespace Cijber\FleaMarket\Storage;


use Cijber\FleaMarket\BackingStorage;


class BufferedStorage implements BackingStorage {

    private array $buffer;
    private int $position = 0;

    public function __construct(
      private BackingStorage $storage,
      private int $bufferSize = 4096,
    ) {
    }

    public function lock(bool $reader = true) {
        $this->storage->lock($reader);
    }

    public function unlock() {
        $this->storage->unlock();
    }

    private function getCurrentPage() {
        return (int)floor($this->position / 4096);
    }

    public function write(string $data) {
        $this->storage->write($data);
        $page           = $this->getCurrentPage() - 1;
        $needed         = strlen($data);
        $page_offset    = $this->position % $this->bufferSize;
        $this->position = $this->storage->tell();

        while ($needed > 0) {
            $page++;
            if ($page_offset === 0 && $needed >= $this->bufferSize) {
                $this->buffer[$page] = substr($data, strlen($data) - $needed, $this->bufferSize);
                $needed              -= $this->bufferSize;
                continue;
            }

            if (!isset($this->buffer[$page])) {
                $this->cachePage($page);
                $needed -= $this->bufferSize;
                continue;
            }


            $page_left = $this->bufferSize - $page_offset;
            if ($page_left > $needed) {
                $page_left = $needed;
            }

            $this->buffer[$page] = substr_replace($this->buffer[$page], substr($data, strlen($data) - $needed, $page_left), $page_offset, $page_left);
            $page_offset         = 0;

            $needed -= $page_left;
        }
    }

    public function read(int $size): string {
        $page = $this->getCurrentPage();
        $data = "";

        $needed      = $size;
        $page_offset = $this->position % $this->bufferSize;

        while ($needed > 0) {
            if (!isset($this->buffer[$page])) {
                $this->cachePage($page);
            }

            $page_left = $this->bufferSize - $page_offset;
            if ($page_left > $needed) {
                $page_left = $needed;
            }

            $data           .= substr($this->buffer[$page], $page_offset, $page_left);
            $page_offset    = 0;
            $this->position += $page_left;
            $needed         -= $page_left;
            $page++;
        }

        $this->storage->seek($this->position);

        return $data;
    }

    public function flush() {
        $this->storage->flush();
    }

    public function seek(int $offset, int $which = SEEK_SET) {
        $this->storage->seek($offset, $which);
        $this->position = $this->storage->tell();
    }

    public function tell(): int {
        return $this->position;
    }

    private function cachePage(int $page) {
        $page_offset = $page * $this->bufferSize;
        $this->storage->seek($page_offset);
        $this->buffer[$page] = $this->storage->read($this->bufferSize);
        $this->storage->seek($this->position);
    }
}