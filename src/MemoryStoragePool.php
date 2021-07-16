<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\Storage\MemoryStorage;


class MemoryStoragePool implements StoragePool {
    private array $storages = [];

    public function getStorage(string $name): BackingStorage {
        if (!isset($this->storages[$name])) {
            $this->storages[$name] = new MemoryStorage();
        }

        return $this->storages[$name];
    }
}