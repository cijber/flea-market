<?php


namespace Cijber\FleaMarket;


interface StoragePool {
    public function getStorage(string $name): BackingStorage;
}