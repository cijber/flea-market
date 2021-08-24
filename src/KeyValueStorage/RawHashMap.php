<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use Cijber\FleaMarket\BackingStorage;
use Cijber\FleaMarket\Storage\MemoryStorage;
use Cijber\FleaMarket\Utils;
use Generator;


class RawHashMap implements Map {
    use MapDefaults;


    private string $hashingKey;
    private int $actualSize;
    private BackingStorage $storage;

    private array $bucketCache = [];
    private array $cache = [];

    public function __construct(?BackingStorage $storage = null, ?int $size = null) {
        if ($storage === null) {
            $this->storage = new MemoryStorage();
        } else {
            $this->storage = $storage;
        }

        if ($size === null) {
            $size = 12;
        }

        $fill = false;
        $this->storage->seek(0, SEEK_END);
        if ($this->storage->tell() === 0) {
            $this->hashingKey = \sodium_crypto_shorthash_keygen();
            $this->storage->write($this->hashingKey);
            $this->storage->write(chr($size));
            $fill = true;
        } else {
            $this->storage->seek(0);
            $this->hashingKey = $this->storage->read(16);
            $size             = ord($this->storage->read(1));
        }

        $this->actualSize = (int)(2 ** $size);

        if ($fill) {
            $this->storage->seek(0, SEEK_END);
            $this->storage->write(str_repeat(Utils::write40BitNumber(Utils::UINT40_MAX), $this->actualSize));
        }
    }

    public function hasAndDelete($key): array {
        $offset = $this->getBucketPointerOffset($key);
        $this->storage->seek($offset);
        $bucket_offset_b = $this->storage->read(5);
        $bucket_offset   = Utils::read40BitNumber($bucket_offset_b);

        if ($bucket_offset === Utils::UINT40_MAX) {
            return [false, null];
        }

        $bucket = $this->readBucket($bucket_offset);

        for ($i = 0; $i < count($bucket->keys); $i++) {
            if ($bucket->keys[$i] === $key) {
                array_splice($bucket->keys, $i, 1);
                $values = array_splice($bucket->values, $i, 1);

                if (count($bucket->values) === 0) {
                    $bucket_offset = Utils::UINT40_MAX;
                } else {
                    $bucket_offset = $this->writeBucket($bucket);
                }

                $this->storage->seek($offset);
                $this->storage->write(Utils::write40BitNumber($bucket_offset));

                return [true, $this->readValue($values[0])];
            }
        }

        return [false, null];
    }

    public function insert($key, $value) {
        $offset = $this->getBucketPointerOffset($key);
        $this->storage->seek($offset);
        $bucket_offset_b = $this->storage->read(5);
        $bucket_offset   = Utils::read40BitNumber($bucket_offset_b);

        if ($bucket_offset !== Utils::UINT40_MAX) {
            $bucket = $this->readBucket($bucket_offset);
        } else {
            $bucket = new HashTableBucket();
        }

        $value_offset = $this->writeValue($value);

        $found = false;
        for ($i = 0; $i < count($bucket->keys); $i++) {
            if ($bucket->keys[$i] === $key) {
                $bucket->values[$i] = $value_offset;
                $found              = true;
                break;
            }
        }

        if (!$found) {
            $bucket->keys[]   = $key;
            $bucket->values[] = $value_offset;
        }

        $bucket_offset   = $this->writeBucket($bucket);
        $bucket_offset_b = Utils::write40BitNumber($bucket_offset);

        $this->storage->seek($offset);
        $this->storage->write($bucket_offset_b);
        $this->storage->flush();
    }

    public function getBucketPointerOffset(string $key) {
        $hash = \sodium_crypto_shorthash($key, $this->hashingKey);
        [, $hash_int] = unpack('P', $hash);

        $bucket = $hash_int & ($this->actualSize - 1);

        return ($bucket * 5) + 17;
    }

    public function hasAndGet($key): array {
        $offset = $this->getBucketPointerOffset($key);
        $this->storage->seek($offset);
        $bucket_offset_b = $this->storage->read(5);
        $bucket_offset   = Utils::read40BitNumber($bucket_offset_b);

        if ($bucket_offset === Utils::UINT40_MAX) {
            return [false, null];
        }

        $bucket = $this->readBucket($bucket_offset);

        foreach ($bucket->keys as $i => $k) {
            if ($k === $key) {
                return [true, $this->readValue($bucket->values[$i])];
            }
        }

        return [false, null];
    }

    /**
     * @internal
     */
    public function readValue(int $offset): string {
        if (isset($this->cache[$offset])) {
            return $this->cache[$offset];
        }

        $this->storage->seek($offset);
        $value_size_b = $this->storage->read(5);
        $value_size   = Utils::read40BitNumber($value_size_b);

        return $this->cache[$offset] = $this->storage->read($value_size);
    }

    public function writeBucket(HashTableBucket $bucket): int {
        $this->storage->seek(0, SEEK_END);
        $offset                     = $this->storage->tell();
        $this->bucketCache[$offset] = $bucket;
        $k                          = count($bucket->keys);

        $data = Utils::write24BitNumber($k);
        foreach ($bucket->keys as $i => $key) {
            $data .= pack('v', strlen($key));
            $data .= $key;
            $data .= Utils::write40BitNumber($bucket->values[$i]);
        }

        $this->storage->write($data);

        return $offset;
    }


    public function readBucket(int $offset): HashTableBucket {
        if (isset($this->bucketCache[$offset])) {
            return $this->bucketCache[$offset];
        }

        $this->storage->seek($offset);
        $key_count_b = $this->storage->read(3);
        $key_count   = Utils::read24BitNumber($key_count_b);

        $bucket = new HashTableBucket();
        for ($i = 0; $i < $key_count; $i++) {
            $key_size_b = $this->storage->read(2);
            [, $key_size] = unpack('v', $key_size_b);
            $key = $this->storage->read($key_size);
            array_push($bucket->keys, $key);
            $value_offset_b = $this->storage->read(5);
            array_push($bucket->values, Utils::read40BitNumber($value_offset_b));
        }

        return $this->bucketCache[$offset] = $bucket;
    }

    private function writeValue($value): int {
        $this->storage->seek(0, SEEK_END);
        $offset = $this->storage->tell();
        $this->storage->write(Utils::write40BitNumber(strlen($value)));
        $this->storage->write($value);

        $this->cache[$offset] = $value;

        return $offset;
    }

    public function has($key): bool {
        $offset = $this->getBucketPointerOffset($key);
        $this->storage->seek($offset);
        $bucket_offset_b = $this->storage->read(5);
        $bucket_offset   = Utils::read40BitNumber($bucket_offset_b);

        if ($bucket_offset === Utils::UINT40_MAX) {
            return false;
        }

        $bucket = $this->readBucket($bucket_offset);

        foreach ($bucket->keys as $i => $k) {
            if ($k === $key) {
                return true;
            }
        }

        return false;
    }

    public function entries(): Generator {
        $offset = 17;

        $this->storage->seek($offset);
        $offsets = $this->storage->read($this->actualSize * 5);

        for ($i = 0; $i < $this->actualSize; $i++) {
            $bucket_offset = Utils::read40BitNumber($offsets, $i * 5);
            if ($bucket_offset === Utils::UINT40_MAX) {
                continue;
            }

            $bucket = $this->readBucket($bucket_offset);

            foreach ($bucket->keys as $j => $key) {
                yield new MapEntryWithOffsetValue($this, $key, $bucket->values[$j]);
            }
        }
    }

    public function close(): void {
        $this->storage->close();
    }
}