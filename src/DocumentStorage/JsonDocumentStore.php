<?php


namespace Cijber\FleaMarket\DocumentStorage;

use Cijber\FleaMarket\StoragePool;
use Generator;
use Ramsey\Uuid\UuidInterface;


class JsonDocumentStore implements DocumentStore {

    private RawDocumentStore $store;

    public function __construct(StoragePool $pool, private bool $useJsonLines = true) {
        $this->store = new RawDocumentStore(
          $pool->getStorage($this->useJsonLines ? 'documents.jsonl' : 'documents.db'),
          $pool->getStorage('handles.db'),
          $pool->getStorage('id.db'),
        );
    }

    public function insert($document, ?int &$handle_offset = 0): UuidInterface {
        return $this->store->insert(json_encode($document) . ($this->useJsonLines ? "\n" : ""), $handle_offset);
    }

    public function update(UuidInterface $id, $document, ?int &$handle_offset = 0) {
        $this->store->update($id, json_encode($document) . ($this->useJsonLines ? "\n" : ""), $handle_offset);
    }

    public function upsert(UuidInterface $id, $document, ?int &$handle_offset = 0) {
        $this->store->upsert($id, json_encode($document) . ($this->useJsonLines ? "\n" : ""), $handle_offset);
    }

    public function delete(UuidInterface $id, bool $fail_if_not_exists) {
        $this->store->delete($id, $fail_if_not_exists);
    }

    public function hasAndGetByHandleOffset(int $offset): array {
        [$found, $value] = $this->store->hasAndGetByHandleOffset($offset);
        if ($found) {
            $value = json_decode($value, true);
        }

        return [$found, $value];
    }

    public function hasAndGet(UuidInterface $id): array {
        [$found, $value, $offset] = $this->store->hasAndGet($id);
        if ($found) {
            $value = json_decode($value, true);
        }

        return [$found, $value, $offset];
    }

    public function get(UuidInterface $id): mixed {
        [$found, $val] = $this->hasAndGet($id);

        return $found ? $val : null;
    }

    public function has(UuidInterface $id): bool {
        return $this->store->has($id);
    }

    public function all(): Generator {
        foreach ($this->store->all() as $value) {
            yield json_decode($value, true);
        }
    }

    public function hasAndDelete(UuidInterface $id): array {
        [$found, $document, $handle_offset] = $this->store->hasAndDelete($id);
        if ($found) {
            $document = json_decode($document, true);
        }

        return [$found, $document, $handle_offset];
    }
}