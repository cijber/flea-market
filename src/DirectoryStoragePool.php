<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\Storage\BufferedStorage;
use Cijber\FleaMarket\Storage\FileStorage;


class DirectoryStoragePool implements StoragePool {
    private array $storages = [];

    public function __construct(
      private string $path,
      private bool $useBuffered = true,
    ) {
        if (!is_dir($this->path)) {
            mkdir($this->path, recursive: true);
        }
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getStorage(string $name): BackingStorage {
        if (!isset($this->storages[$name])) {
            $storage = new FileStorage($this->path . '/' . $name);
            if ($this->useBuffered) {
                $storage = new BufferedStorage($storage);
            }

            $this->storages[$name] = $storage;
        }

        return $this->storages[$name];
    }
}