<?php


namespace Cijber\FleaMarket;


use JmesPath\CompilerRuntime;


class IndexStore {
    private CompilerRuntime $jmesRuntime;

    public function __construct(
      private StoragePool $pool,
      private array $indexes = []
    ) {
        $this->jmesRuntime = new CompilerRuntime($this->pool instanceof DirectoryStoragePool ? $this->pool->getPath() . '/.jmes' : null);
    }

    function add(string $name, Index $index): Index {
        $index->setJmesRuntime($this->jmesRuntime);

        return $this->indexes[$name] = $index;
    }

    function index(string $name): ?Index {
        return $this->indexes[$name] ?? null;
    }

    /** @return Index[] */
    function get(): array {
        return $this->indexes;
    }

    public function has(string $name) {
        return isset($this->indexes[$name]);
    }
}