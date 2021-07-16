<?php


namespace Cijber\FleaMarket;


class PrefixPool implements StoragePool {
    public function __construct(private string $prefix, private StoragePool $pool) {
    }

    public function getPrefix(): string {
        return $this->prefix;
    }

    public function getStorage(string $name): BackingStorage {
        return $this->pool->getStorage($this->prefix . $name);
    }
}