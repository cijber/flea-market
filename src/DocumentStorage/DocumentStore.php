<?php


namespace Cijber\FleaMarket\DocumentStorage;


use Generator;
use Ramsey\Uuid\UuidInterface;


interface DocumentStore {
    public function insert($document, ?int &$handle_offset = 0): UuidInterface;

    public function update(UuidInterface $id, $document, ?int &$handle_offset = 0);

    public function upsert(UuidInterface $id, $document, ?int &$handle_offset = 0);

    public function delete(UuidInterface $id, bool $fail_if_not_exists);

    public function hasAndGetByHandleOffset(int $offset): array;

    public function hasAndGet(UuidInterface $id): array;

    public function hasAndDelete(UuidInterface $id): array;

    public function get(UuidInterface $id): mixed;

    public function has(UuidInterface $id): bool;

    public function all(): Generator;

    public function close(): void;
}