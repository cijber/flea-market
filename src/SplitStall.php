<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\KeyValueStorage\Key\IntKey;
use Cijber\FleaMarket\Op\QueryEq;
use Cijber\FleaMarket\Op\QueryHas;
use Generator;
use RuntimeException;


class SplitStall implements StallInterface {
    private StoragePool $pool;
    private array $stalls = [];
    private array $knownStalls;
    private IndexStore $indexStore;

    private Index $splitter;
    private BackingStorage $poolList;

    public function __construct(?StoragePool $pool = null) {
        if ($pool === null) {
            $this->pool = new MemoryStoragePool();
        } else {
            $this->pool = $pool;
        }

        $this->indexStore = new IndexStore($this->pool);
        $this->splitter   = $this->indexStore->add('_split', new Index());

        $this->poolList = $this->pool->getStorage('pools.list');
        $size           = $this->poolList->seek(0, SEEK_END);
        $this->poolList->seek(0);
        $list              = $this->poolList->read($size);
        $this->knownStalls = array_filter(explode("\n", $list), fn($x) => $x !== "");
    }

    public function split(): Index {
        return $this->splitter;
    }

    public function insert(array $document): array {
        $data = $this->splitter->process($document);
        if (is_array($data)) {
            throw new RuntimeException("Splitter should only return a single value");
        }

        $id = bin2hex($data);

        return $this->getStall($id)->insert($document);
    }

    public function rangeIndex(string $name, $key = IntKey::class, bool $new = false): Index {
        if (!$new) {
            $index = $this->indexStore->index($name);
            if ($index !== null) {
                return $index;
            }
        }

        return $this->indexStore->add($name, new Index(true, $key));
    }

    public function index(string $name, bool $new = false): Index {
        if (!$new) {
            $index = $this->indexStore->index($name);
            if ($index !== null) {
                return $index;
            }
        }

        return $this->indexStore->add($name, new Index());
    }

    private function getStall(string $id) {
        if (isset($this->stalls[$id])) {
            return $this->stalls[$id];
        }

        $stall             = new Stall(new PrefixPool("$id/", $this->pool), $this->indexStore);
        $this->stalls[$id] = $stall;

        return $stall;
    }

    public function find(): Query {
        return new Query($this);
    }

    public function runQuery(Query $query): iterable {
        throw new \RuntimeException("Not implemented");

        foreach ($query->getOperations() as $op) {
            if ($op->isOnIndex() && $op->field === '_split') {
                if ($op instanceof QueryHas) {
                    foreach ($this->knownStalls as $stall) {
                    }
                } elseif ($op instanceof QueryEq) {
                    // :)
                }
            }
        }
    }
}