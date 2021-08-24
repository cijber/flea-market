<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\Storage\MemoryStorage;


class MemoryStoragePool implements StoragePool {
    /**
     * @var BackingStorage[]
     */
    private array $storages = [];

    public function getStorage(string $name): BackingStorage {
        if ( ! isset($this->storages[$name])) {
            $this->storages[$name] = new MemoryStorage();
        }

        return $this->storages[$name];
    }

    public function close(): void {
        foreach ($this->storages as $storage) {
            $storage->close();
        }
    }

    public function hasDirectory(string $directory): bool {
        foreach ($this->storages as $key => $_) {
            if (str_starts_with($key, $directory . '/')) {
                return true;
            }
        }

        return false;
    }
}