<?php


namespace Cijber\FleaMarket\Storage;


use Cijber\FleaMarket\BackingStorage;


class FileStorage implements BackingStorage {
    private $fd;

    public function __construct(private string $path) {
        $dir = dirname($this->path);
        if ( ! is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        $fd = fopen($path, 'c+');
        if ($fd === false) {
            throw new \RuntimeException(":(");
        }
        $this->fd = $fd;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function setPath(string $path): void {
        $this->path = $path;
    }

    public function lock(bool $reader = true) {
        flock($this->fd, $reader ? LOCK_SH : LOCK_EX);
    }

    public function unlock(): void {
        flock($this->fd, LOCK_UN);
    }

    public function seek(int $offset, int $which = SEEK_SET) {
        if (fseek($this->fd, $offset, $which) === -1) {
            throw new \RuntimeException("Failed to seek :(");
        }
    }

    public function tell(): int {
        return ftell($this->fd);
    }

    public function write(string $data) {
        return fwrite($this->fd, $data);
    }

    public function read(int $size): string {
        return fread($this->fd, $size);
    }

    public function flush(): void {
        fflush($this->fd);
    }

    public function exchange(FileStorage $rhs) {
        $our_fd     = $this->fd;
        $our_path   = $this->path;
        $this->fd   = $rhs->fd;
        $this->path = $rhs->path;
        $rhs->fd    = $our_fd;
        $rhs->path  = $our_path;
    }

    public function close(): void {
        fclose($this->fd);
    }
}