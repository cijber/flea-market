<?php


namespace Cijber\FleaMarket\DocumentStorage;


use Cijber\FleaMarket\BackingStorage;
use Cijber\FleaMarket\KeyValueStorage\U40HashMap;
use Cijber\FleaMarket\Utils;
use Generator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;


class RawDocumentStore implements DocumentStore {
    public const OPTION_IGNORE_UPDATE   = 1;
    public const OPTION_IGNORE_DELETION = 2;

    private U40HashMap $internalIdStore;
    private array $idHandleCache = [];

    public function __construct(
      private BackingStorage $documentStorage,
      private BackingStorage $handleStorage,
      BackingStorage $internalIdStorage
    ) {
        $this->internalIdStore = new U40HashMap($internalIdStorage);
    }

    public function insert($document, ?int &$handle_offset = 0): UuidInterface {
        $handle = $this->insertWithId(Uuid::uuid4(), $document, $handle_offset);

        return $handle->internalId;
    }

    private function insertWithId(UuidInterface $id, string $document, ?int &$handle_offset = 0): DocumentHandle {
        $offset = $this->writeDocument($document);
        $handle = new DocumentHandle($offset, strlen($document), internalId: $id);

        $handle_offset = $this->writeHandle($handle);
        $this->internalIdStore->insert($handle->internalId->getBytes(), $handle_offset);

        return $handle;
    }

    private function getHandle(UuidInterface $id, &$handle_offset = 0): ?DocumentHandle {
        $id_b = $id->getBytes();
        if (isset($this->idHandleCache[$id_b])) {
            $handle_offset = $this->idHandleCache[$id_b][1];

            return $this->idHandleCache[$id_b][0];
        }

        [$found, $handle_offset] = $this->internalIdStore->hasAndGet($id_b);
        if (!$found) {
            return null;
        }

        $handle                     = $this->readHandle($handle_offset);
        $this->idHandleCache[$id_b] = [$handle, $handle_offset];

        return $handle;
    }

    public function get(UuidInterface $id): ?string {
        [$found, $value] = $this->hasAndGet($id);

        return $found ? $value : null;
    }

    public function upsert(UuidInterface $id, $document, ?int &$handle_offset = 0) {
        $handle = $this->getHandle($id);
        if ($handle === null) {
            $this->insertWithId($id, $document, $handle_offset);

            return;
        }

        $this->update($id, $document, $handle_offset);
    }

    public function update(UuidInterface $id, $document, ?int &$handle_offset = 0) {
        $handle = $this->getHandle($id, $old_handle_offset);
        if ($handle === null) {
            throw new RuntimeException("No document with id {$id} exists");
        }

        $offset        = $this->writeDocument($document);
        $new_handle    = new DocumentHandle($offset, strlen($document), $id, prevRevision: $handle->offset);
        $handle_offset = $this->writeHandle($new_handle);

        $this->handleStorage->lock(false);
        $this->handleStorage->seek($old_handle_offset);
        $this->handleStorage->write('U');
        $this->handleStorage->seek($old_handle_offset + 13);
        $this->handleStorage->write(Utils::write40BitNumber($handle_offset));
        $this->handleStorage->flush();
        $this->handleStorage->unlock();
        $id_b = $id->getBytes();
        $this->internalIdStore->insert($id_b, $handle_offset);

        $this->idHandleCache[$id_b] = $new_handle;
    }

    public function delete(UuidInterface $id, bool $fail_if_not_exists) {
        [$found] = $this->hasAndDelete($id);
        if (!$found) {
            if (!$fail_if_not_exists) {
                return;
            }

            throw new RuntimeException("No document with id {$id} exists");
        }
    }

    public function hasAndDelete(UuidInterface $id): array {
        $id_b = $id->getBytes();
        [$found, $handle_offset] = $this->internalIdStore->hasAndDelete($id_b);

        if (!$found) {
            return [false, null, Utils::UINT40_MAX];
        }

        unset($this->idHandleCache[$id_b]);

        $this->handleStorage->lock(false);
        $this->handleStorage->seek($handle_offset);
        $this->handleStorage->write('D');
        $this->handleStorage->flush();
        $this->handleStorage->unlock();

        $handle = $this->readHandle($handle_offset, self::OPTION_IGNORE_DELETION);

        return [true, $this->readDocument($handle), $handle_offset];
    }

    private function writeHandle(DocumentHandle $handle): int {
        $this->handleStorage->lock(false);
        $this->handleStorage->seek(0, SEEK_END);
        $handle_offset = $this->handleStorage->tell();

        // 1
        $handle_bytes = $handle->deleted ? 'D' : ($handle->nextRevision === Utils::UINT40_MAX ? 'C' : 'U');
        // 5
        $handle_bytes .= Utils::write40BitNumber($handle->offset);
        // 3
        $handle_bytes .= Utils::write24BitNumber($handle->size);
        // 5
        $handle_bytes .= Utils::write40BitNumber($handle->nextRevision);
        // 5
        $handle_bytes .= Utils::write40BitNumber($handle->prevRevision);
        // 16
        $handle_bytes .= $handle->internalId->getBytes();
        // 1
        $handle_bytes .= "\n";
        // 36

        $this->handleStorage->write($handle_bytes);
        $this->handleStorage->flush();
        $this->handleStorage->unlock();

        return $handle_offset;
    }

    private function readHandle(int $offset, int $options = 0): ?DocumentHandle {
        $this->handleStorage->seek($offset);
        $handle_bytes = $this->handleStorage->read(36);
        if ($handle_bytes[0] === 'D' && ($options & self::OPTION_IGNORE_DELETION) === 0) {
            return null;
        }

        if ($handle_bytes[0] === 'U' && ($options & self::OPTION_IGNORE_UPDATE) === 0) {
            $new_offset = Utils::read40BitNumber($handle_bytes, 9);

            return $this->readHandle($new_offset, $options);
        }

        $deleted         = $handle_bytes[0] === 'D';
        $document_offset = Utils::read40BitNumber($handle_bytes, 1);
        $size            = Utils::read24BitNumber($handle_bytes, 6);
        $next_revision   = Utils::read40BitNumber($handle_bytes, 9);
        $prev_revision   = Utils::read40BitNumber($handle_bytes, 14);
        $internal_id     = Uuid::fromBytes(substr($handle_bytes, 19, 16));

        return new DocumentHandle($document_offset, $size, $internal_id, $deleted, $next_revision, $prev_revision);
    }

    private function writeDocument(string $document): int {
        $this->documentStorage->lock(false);
        $this->documentStorage->seek(0, SEEK_END);
        $offset = $this->documentStorage->tell();
        $this->documentStorage->write($document);
        $this->documentStorage->unlock();

        return $offset;
    }

    private function readDocument(DocumentHandle $handle): string {
        $this->documentStorage->seek($handle->offset);

        return $this->documentStorage->read($handle->size);
    }

    public function has(UuidInterface $id): bool {
        return $this->internalIdStore->has($id);
    }

    public function hasAndGet(UuidInterface $id): array {
        $handle = $this->getHandle($id, $handle_offset);
        if ($handle === null) {
            return [false, null, Utils::UINT40_MAX];
        }

        return [true, $this->readDocument($handle), $handle_offset];
    }

    public function hasAndGetByHandleOffset(int $offset): array {
        $handle = $this->readHandle($offset);
        if ($handle === null) {
            return [false, null];
        }

        return [true, $this->readDocument($handle)];
    }

    public function all(): Generator {
        foreach ($this->internalIdStore->values() as $offset) {
            [$found, $document] = $this->hasAndGetByHandleOffset($offset);
            if ($found) {
                yield $document;
            }
        }
    }
}