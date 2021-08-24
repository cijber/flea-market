<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\KeyValueStorage\Key;
use Cijber\FleaMarket\KeyValueStorage\Key\IntKey;
use Cijber\FleaMarket\KeyValueStorage\Map;
use Cijber\FleaMarket\KeyValueStorage\RawBTree;
use Cijber\FleaMarket\KeyValueStorage\RawHashMap;
use Cijber\FleaMarket\Op\QueryEq;
use Cijber\FleaMarket\Op\QueryHas;
use Cijber\FleaMarket\Op\QueryIn;
use Cijber\FleaMarket\Op\QueryRange;
use JetBrains\PhpStorm\Pure;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;


class SplitStall implements StallInterface {
    private StoragePool $pool;
    /** @var Stall[] */
    private array $stalls = [];
    private array $knownStalls = [];
    private IndexStore $indexStore;

    private Index $splitter;
    private BackingStorage $poolStorage;
    private Map $poolMap;
    private RawHashMap $idMap;

    public function __construct(
      Index $index,
      ?StoragePool $pool = null,
      private string $splitIndexName = '_split',
    ) {
        if ($pool === null) {
            $this->pool = new MemoryStoragePool();
        } else {
            $this->pool = $pool;
        }

        $this->indexStore = new IndexStore($this->pool);
        $this->splitter   = $index;
        $this->indexStore->init($this->splitIndexName, $this->splitter);

        $this->poolStorage = $this->pool->getStorage('pools.map');
        $this->poolMap     = $index->isRangeIndex() ? new RawBTree($this->poolStorage, $index->getKey()) : new RawHashMap($this->poolStorage);
        $this->idMap       = new RawHashMap($this->pool->getStorage('id.map'));

        foreach ($this->poolMap->entries() as [$key, $id]) {
            $this->knownStalls[$id] = $key;
        }
    }

    public function split(): Index {
        return $this->splitter;
    }

    public function insert(array $document): array {
        $data = $this->splitter->process($document);
        if (is_array($data)) {
            throw new RuntimeException("Splitter should only return a single value");
        }

        if ($data === null) {
            throw new RuntimeException("Splitter index returned null");
        }

        $stall_id        = $this->createStallId($data);
        $id              = Uuid::uuid4();
        $document[':id'] = $id->toString();

        $this->idMap->insert($id->toString(), $stall_id);

        return $this->getStall($stall_id, $data)->insert($document);
    }

    public function rangeIndex(string $name, $key = IntKey::class, bool $new = false): Index {
        if ( ! $new) {
            $index = $this->indexStore->index($name);
            if ($index !== null) {
                return $index;
            }
        }

        return $this->indexStore->add($name, new Index(true, $key));
    }

    public function index(string $name, bool $new = false): Index {
        if ( ! $new) {
            $index = $this->indexStore->index($name);
            if ($index !== null) {
                return $index;
            }
        }

        return $this->indexStore->add($name, new Index());
    }

    private function getStall(string $id, mixed $data = null) {
        if (isset($this->stalls[$id])) {
            return $this->stalls[$id];
        }

        if ( ! isset($this->knownStalls[$id])) {
            if ($data === null) {
                return null;
            }

            $this->poolMap->insert($data, $id);
            $this->knownStalls[$id] = $data;
        }

        $stall = new Stall(new PrefixPool("$id/", $this->pool), $this->indexStore);

        $this->stalls[$id] = $stall;

        return $stall;
    }

    #[Pure]
    public function find(): Query {
        return new Query($this);
    }

    public function runQuery(Query $query): iterable {
        $stallQuery = $query->copy();
        $stallQuery->removeOperationsOnIndex($this->splitIndexName);

        $stallIds = $this->getStallIdsForQuery($query);
        if ($stallIds === null) {
            $stallIds = array_keys($this->knownStalls);
        }

        $resort      = null;
        $sortReverse = false;
        foreach ($stallQuery->getIndexOperations() as $index) {
            if ($index instanceof QueryRange) {
                $resort      = $index->getIndex();
                $sortReverse = $index->reversed;
            }
        }

        if ($resort !== null) {
            $from = [];
            foreach ($stallIds as $stallId) {
                $from[] = \iter\map(fn($carr) => [$stallId, $carr], $this->getStall($stallId)->getOffsetCarriersForQuery($stallQuery));
            }


            try {
                foreach (reorder($this->indexStore->index($resort), $sortReverse, ...$from) as [$stallId, $carr]) {
                    [$has, $item] = $this->getStall($stallId)->hasAndGetByHandleOffset($carr->getOffset());
                    if ($has) {
                        yield $item;
                    }
                }
            } catch (\Throwable $t) {
                $____ = $t;
            }

            return;
        }

        // do parallel?
        foreach ($stallIds as $stallId) {
            $stall = $this->getStall($stallId);
            if ($stall === null) {
                continue;
            }

            yield from $stall->runQuery($stallQuery);
        }
    }

    private function getStallIdsForQuery(Query $query): ?iterable {
        $filter = null;

        $io = $query->getIndexOperations();
        for ($i = 0; $i < count($io); $i++) {
            $op = $io[$i];
            if ($op->getIndex() === $this->splitIndexName) {
                if ($op instanceof QueryHas) {
                    // All items have the splitter
                    continue;
                }

                if ($op instanceof QueryEq || $op instanceof QueryIn) {
                    if ($op instanceof QueryEq) {
                        $values = [$op->value];
                    } else {
                        $values = $op->value;
                    }

                    $stallIds = array_map(fn($value) => $this->createStallId($this->splitter->processInput($value)), $values);
                    if ($filter === null) {
                        $filter = $stallIds;
                    } else {
                        $filter = intersectOffsetCarriers($stallIds, $filter);
                    }
                }

                if ($op instanceof QueryRange) {
                    $range = $this->poolMap->range($op->range->from, $op->range->to, $op->range->fromInclusive, $op->range->toInclusive, $op->reversed);
                    if ($filter === null) {
                        $filter = $range;
                    } else {
                        $filter = intersectOffsetCarriers($range, $filter);
                    }
                }
            }
        }

        return $filter;
    }

    public function createStallId(mixed $data): string {
        return hash('sha256', $data);
    }

    public function delete(UuidInterface|array|string $docOrId): array {
        if (is_array($docOrId)) {
            $docOrId = $docOrId[':id'];
        }

        if ($docOrId instanceof UuidInterface) {
            $docOrId = $docOrId->toString();
        }

        $stallId = $this->idMap->get($docOrId);
        if ($stallId === null) {
            throw new RuntimeException("Document with id $docOrId doesn't exist");
        }

        $doc = $this->getStall($stallId)->delete($docOrId);
        $this->idMap->delete($docOrId);

        return $doc;
    }

    public function update(array $document, UuidInterface|string|null $id = null): array {
        if ($id === null) {
            if ( ! isset($doc[':id'])) {
                throw new RuntimeException("Called update without id, either the :id field should be in the document or \$id should be given");
            }

            $id = $doc[':id'];
        }

        if ($id instanceof UuidInterface) {
            $id = $id->toString();
        }

        $oldStall = $this->idMap->get($id);
        $newStall = $this->createStallId($this->splitter->process($document));

        if ($oldStall !== $newStall) {
            $oldDoc = $this->getStall($oldStall)->delete($id);
            $this->idMap->insert($id, $newStall);

            $document[':id'] = $id;
            $this->getStall($newStall)->insert($document);

            return $oldDoc;
        } else {
            return $this->getStall($newStall)->update($document, $id);
        }
    }

    public function all(): iterable {
        foreach (array_keys($this->knownStalls) as $id) {
            yield from $this->getStall($id)->all();
        }
    }

    public function close(): void {
        $stalls       = $this->stalls;
        $this->stalls = [];
        foreach ($stalls as $stall) {
            $stall->close();
        }

        $this->poolMap->close();
        $this->pool->close();
    }

    public static function create(callable $indexFn, ?StoragePool $pool = null, string $splitterIndexName = "_split"): SplitStall {
        $index = $indexFn(new Index());

        return new SplitStall($index, $pool, $splitterIndexName);
    }

    public static function createRange(Key $key, callable $indexFn, ?StoragePool $pool = null, string $splitterIndexName = "_split"): SplitStall {
        $index = $indexFn(new Index(true, $key));

        return new SplitStall($index, $pool, $splitterIndexName);
    }
}