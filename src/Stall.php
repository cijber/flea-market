<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\DocumentStorage\DocumentStore;
use Cijber\FleaMarket\DocumentStorage\JsonDocumentStore;
use Cijber\FleaMarket\Filter\UniqueFilter;
use Cijber\FleaMarket\KeyValueStorage\Key\IntKey;
use Cijber\FleaMarket\KeyValueStorage\Map;
use Cijber\FleaMarket\KeyValueStorage\RawBTree;
use Cijber\FleaMarket\KeyValueStorage\RawHashMap;
use Cijber\FleaMarket\Op\QueryEq;
use Cijber\FleaMarket\Op\QueryHas;
use Cijber\FleaMarket\Op\QueryRange;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

use function iter\chain;
use function iter\filter;
use function iter\flatMap;
use function iter\toIter;


class Stall implements StallInterface {
    private StoragePool $pool;

    private DocumentStore $documentStore;
    private IndexStore $indexStore;
    /** @var Map[] */
    private array $indexMaps = [];

    public function __construct(?StoragePool $pool = null, ?IndexStore $index_store = null) {
        if ($pool === null) {
            $this->pool = new MemoryStoragePool();
        } else {
            $this->pool = $pool;
        }

        if ($index_store === null) {
            $this->indexStore = new IndexStore($this->pool);
        } else {
            $this->indexStore = $index_store;
        }

        $this->documentStore = new JsonDocumentStore($this->pool);
    }

    public function insert(array $document): array {
        $id              = Uuid::uuid4();
        $document[':id'] = $id->toString();
        $this->documentStore->upsert($id, $document, $handle_offset);

        foreach ($this->indexStore->get() as $index_name => $index) {
            $data = $index->process($document);
            if (!is_array($data)) {
                $data = [$data];
            }

            $map = $this->getMap($index_name);

            foreach ($data as $item) {
                if ($item === null) {
                    continue;
                }

                [$found, $items] = $map->hasAndGet($item);
                if ($found) {
                    $offsets = str_split($items, 5);
                } else {
                    $offsets = [];
                }

                $offsets[] = Utils::write40BitNumber($handle_offset);
                $map->insert($item, implode("", $offsets));
            }
        }

        return $document;
    }

    public function rangeIndex(string $name, $key = IntKey::class, bool $new = false): Index {
        $index = $this->indexStore->index($name);
        if ($index === null || $new) {
            return $this->indexStore->add($name, new Index(true, $key));
        }

        return $index;
    }

    public function index(string $name, bool $new = false): Index {
        $index = $this->indexStore->index($name);
        if ($index === null || $new) {
            return $this->indexStore->add($name, new Index());
        }

        return $index;
    }

    private function getMap(string $index_name): Map {
        if (isset($this->indexMaps[$index_name])) {
            return $this->indexMaps[$index_name];
        }

        $index = $this->indexStore->index($index_name);
        if ($index === null) {
            throw new RuntimeException("No index with the name $index_name");
        }

        if ($index->isRangeIndex()) {
            $this->indexMaps[$index_name] = new RawBTree($this->pool->getStorage("$index_name.btree.index"), $index->getKey());
        } else {
            $this->indexMaps[$index_name] = new RawHashMap($this->pool->getStorage("$index_name.hashmap.index"));
        }

        return $this->indexMaps[$index_name];
    }

    public function find(): Query {
        return new Query($this);
    }

    public function runQuery(Query $query): iterable {
        $operations = $query->getOperations();

        $offsets = null;
        foreach ($operations as $operation) {
            if ($operation->isOnIndex()) {
                if ($operation instanceof QueryHas) {
                    $index = $this->indexStore->index($operation->field);

                    if ($index === null) {
                        throw new RuntimeException("No index with the name {$operation->field}");
                    }

                    $map = $this->getMap($operation->field);

                    $new_offsets = flatMap(fn($item) => str_split($item, 5), $map->values());
                    $new_offsets = filter(fn($item) => strlen($item) === 5, $new_offsets);
                    $new_offsets = \iter\map(fn($item) => Utils::read40BitNumber($item), $new_offsets);

                    if ($offsets === null) {
                        $offsets = $new_offsets;
                    } else {
                        $offsets = intersect($new_offsets, $offsets);
                    }
                } elseif ($operation instanceof QueryEq) {
                    $index = $this->indexStore->index($operation->field);

                    if ($index === null) {
                        throw new RuntimeException("No index with the name {$operation->field}");
                    }

                    $input_value = $index->processInput($operation->value);

                    $map = $this->getMap($operation->field);

                    [$found, $items] = $map->hasAndGet($input_value);

                    if (!$found || strlen($items) === 0) {
                        return chain();
                    }

                    $new_offsets = array_map(fn($x) => Utils::read40BitNumber($x), str_split($items, 5));

                    if ($offsets === null) {
                        $offsets = toIter($new_offsets);
                    } else {
                        $offsets = intersect($new_offsets, $offsets);
                    }
                } elseif ($operation instanceof QueryRange) {
                    $index = $this->indexStore->index($operation->field);

                    if ($index === null) {
                        throw new RuntimeException("No index with the name \"{$operation->field}\"");
                    }

                    $from = $operation->range->from !== null ? $index->processInput($operation->range->from) : null;
                    $to   = $operation->range->to !== null ? $index->processInput($operation->range->to) : null;


                    $map = $this->getMap($operation->field);

                    $new_offsets = flatMap(fn($item) => str_split($item->value(), 5), $map->range($from, $to, $operation->range->fromInclusive, $operation->range->toInclusive));
                    $new_offsets = filter(fn($item) => strlen($item) === 5, $new_offsets);
                    $new_offsets = \iter\map(fn($item) => Utils::read40BitNumber($item), $new_offsets);
                    if ($offsets === null) {
                        $offsets = $new_offsets;
                    } else {
                        $offsets = intersect($new_offsets, $offsets);
                    }
                }
            }
        }

        if ($offsets === null) {
            $entries = $this->documentStore->all();
        } else {
            $entries = filter(new UniqueFilter(), $offsets);
            $entries = \iter\map(fn($offset) => $this->documentStore->hasAndGetByHandleOffset($offset), $entries);
            $entries = flatMap(fn($found_and_item) => $found_and_item[0] ? [$found_and_item[1]] : [], $entries);
        }

        return filter(fn($item) => $query->matches($item), $entries);
    }

    public function delete(UuidInterface|array|string $docOrId) {
        if (is_array($docOrId)) {
            if (!isset($docOrId[':id'])) {
                throw new RuntimeException("Called update without id, either the :id field should be in the document or the id should be given as string");
            }

            $id = $docOrId[':id'];
        } else {
            $id = $docOrId;
        }

        if (!$id instanceof UuidInterface) {
            $id = Uuid::fromString($id);
        }

        [$found, $object, $old_handle_offset] = $this->documentStore->hasAndDelete($id);
        if (!$found) {
            throw new RuntimeException("Document with id $id doesn't exist");
        }

        $old_handle_offset_b = Utils::write40BitNumber($old_handle_offset);

        foreach ($this->indexStore->get() as $index_name => $index) {
            $old_output = $index->process($object);
            $map        = $this->getMap($index_name);

            if (!is_array($old_output)) {
                $old_output = [$old_output];
            }

            foreach ($old_output as $index_key) {
                [$found, $value] = $map->hasAndGet($index_key);
                if (!$found) {
                    continue;
                }

                if ($value === "" || $value === $old_handle_offset_b) {
                    $map->delete($index_key);
                    continue;
                }

                for ($i = 0; $i < strlen($value); $i += 5) {
                    if (substr($value, $i, 5) === $old_handle_offset_b) {
                        $value = substr_replace($value, "", $i, 5);
                        $map->insert($index_key, $value);
                        break;
                    }
                }
            }
        }
    }

    public function update(array $doc, ?string $id = null) {
        if ($id === null) {
            if (!isset($doc[':id'])) {
                throw new RuntimeException("Called update without id, either the :id field should be in the document or \$id should be given");
            }

            $id = $doc[':id'];
        }

        if (!$id instanceof UuidInterface) {
            $id = Uuid::fromString($id);
        }

        [$found, $object, $old_handle_offset] = $this->documentStore->hasAndGet($id);
        if (!$found) {
            throw new RuntimeException("Document with id $id doesn't exist");
        }

        $this->documentStore->update($id, $doc, $handle_offset);

        $handle_offset_b     = Utils::write40BitNumber($handle_offset);
        $old_handle_offset_b = Utils::write40BitNumber($old_handle_offset);

        foreach ($this->indexStore->get() as $index_name => $index) {
            $combo      = [];
            $old_output = $index->process($object);
            $new_output = $index->process($doc);

            $map = $this->getMap($index_name);

            if ($old_output === null) {
                $old_output = [];
            } elseif (!is_array($old_output)) {
                $old_output = [$old_output];
            }

            $x = [];
            foreach ($old_output as $item) {
                $combo[$item] = true;
                $x[$item]     = true;
            }

            $old_output = $x;


            if ($new_output === null) {
                $new_output = [];
            } elseif (!is_array($new_output)) {
                $new_output = [$new_output];
            }

            $x = [];
            foreach ($new_output as $item) {
                $combo[$item] = true;
                $x[$item]     = true;
            }

            $new_output = $x;

            foreach ($combo as $key => $_) {
                // Exists in old, but not in new, remove our entry from the index
                if (isset($old_output[$key]) && !isset($new_output[$key])) {
                    [$found, $offsets] = $map->hasAndGet($key);
                    if (!$found) {
                        continue;
                    }

                    if (strlen($offsets) === 0 || $offsets === $old_handle_offset_b) {
                        $map->delete($key);
                        continue;
                    }

                    for ($i = 0; $i < strlen($offsets); $i += 5) {
                        if (substr($offsets, $i, 5) === $old_handle_offset_b) {
                            $offsets = substr_replace($offsets, "", $i, 5);
                            break;
                        }
                    }

                    $map->insert($key, $offsets);
                    continue;
                }

                // These either need to be replaced or inserted
                [$found, $offsets] = $map->hasAndGet($key);
                if (!$found) {
                    $map->insert($key, $handle_offset_b);
                    continue;
                }

                if (strlen($offsets) === 0 || $offsets === $old_handle_offset_b) {
                    $map->insert($key, $handle_offset_b);
                    continue;
                }

                $replaced = false;

                // If we need to replace, check current offsets
                if (isset($old_output[$key])) {
                    for ($i = 0; $i < strlen($offsets); $i += 5) {
                        if (substr($offsets, $i, 5) === $old_handle_offset_b) {
                            $offsets  = substr_replace($offsets, $handle_offset_b, $i, 5);
                            $replaced = true;
                            break;
                        }
                    }
                }

                if (!$replaced) {
                    $offsets .= $handle_offset_b;
                }

                $map->insert($key, $offsets);
            }
        }
    }
}