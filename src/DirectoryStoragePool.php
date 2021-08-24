<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\Storage\FileStorage;


class DirectoryStoragePool implements StoragePool {
    /**
     * @var FileStorage[]
     */
    private array $storages = [];

    public function __construct(
      private string $path,
        //private bool $useBuffered = true,
    ) {
        if (!is_dir($this->path)) {
            mkdir($this->path, recursive: true);
        }
    }

    public function getPath(): string {
        return $this->path;
    }

    public function exchangeStorage(string $old_name, string $new_name) {
        $old_storage               = $this->storages[$old_name];
        $new_storage               = $this->storages[$new_name];
        $this->storages[$old_name] = $new_storage;
        $this->storages[$new_name] = $old_storage;

        //if ($old_storage instanceof BufferedStorage) {
        //    $old_storage->clearCache();
        //    $old_storage = $old_storage->getInnerStorage();
        //}
        //
        //if ($new_storage instanceof BufferedStorage) {
        //    $new_storage->clearCache();
        //    $new_storage = $new_storage->getInnerStorage();
        //}

        /** @var FileStorage $old_storage */
        /** @var FileStorage $new_storage */

        $old_storage->exchange($new_storage);
    }

    public function getStorage(string $name): BackingStorage {
        if (!isset($this->storages[$name])) {


            $storage               = new FileStorage($this->path . '/' . $name);
            $this->storages[$name] = $storage;
        }

        return $this->storages[$name];
    }

    public function close(): void {
        foreach ($this->storages as $storage) {
            $storage->close();
        }
    }
}