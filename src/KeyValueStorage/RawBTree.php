<?php


namespace Cijber\FleaMarket\KeyValueStorage;


use Cijber\FleaMarket\BackingStorage;
use Cijber\FleaMarket\KeyValueStorage\Key\IntKey;
use Cijber\FleaMarket\Storage\MemoryStorage;
use Cijber\FleaMarket\Utils;
use Generator;
use RuntimeException;


class RawBTree implements Map {
    use MapDefaults;


    private ?BTreeNode $root = null;
    private BackingStorage $storage;
    private int $degree;
    private Key $key;
    private array $cache = [];

    public function __construct(
      ?BackingStorage $storage = null,
      $key = IntKey::class,
      private int $maxKeys = 5,
    ) {
        if (!((is_string($key) && is_a($key, Key::class, true)) || ($key instanceof Key))) {
            throw new RuntimeException("BTree key should be type of Cijber\\FleaMarket\\KeyValueStorage\\Key");
        }

        if (is_string($key)) {
            $key = ($key)::identity();
        }

        if ($storage === null) {
            $this->storage = new MemoryStorage();
        } else {
            $this->storage = $storage;
        }

        $this->key    = $key;
        $this->degree = (int)ceil($this->maxKeys / 2);

        $this->loadRoot();
    }

    private function getKey($key): Key {
        $new_key = clone $this->key;
        $new_key->load($key);

        return $new_key;
    }

    public function insert($key, $value) {
        $key = $this->getKey($key);

        $this->storage->lock(false);
        if ($this->root === null) {
            $this->root           = new BTreeNode(leaf: true, root: true);
            $this->root->keys[]   = $key;
            $value_offset         = $this->writeValue($value);
            $this->root->values[] = $value_offset;


            $root_offset = $this->writeNode($this->root);
            $this->writeHeader($root_offset);
            $this->storage->unlock();

            return;
        }

        $this->storage->lock(false);
        if (count($this->root->keys) == $this->maxKeys) {
            $new_root             = new BTreeNode(leaf: false, root: true);
            $new_root->children[] = $this->root->offset;
            $this->writeNode($new_root);

            $this->root->root = false;
            $this->split($new_root, 0, $this->root);
            $this->root = $new_root;
        }

        $root = $this->insertInto($this->root, $key, $value);
        $this->writeHeader($root);
        $this->storage->unlock();
    }

    private function split(BTreeNode $node, int $index, BTreeNode $full) {
        $right = new BTreeNode(leaf: $full->leaf);

        $right->keys   = array_splice($full->keys, $this->degree, $this->degree - 1);
        $right->values = array_splice($full->values, $this->degree, $this->degree - 1);

        if (!$full->leaf) {
            $right->children = array_splice($full->children, $this->degree, $this->degree);
        }

        $right_offset = $this->writeNode($right);
        $old_offset   = $full->offset;

        array_splice($node->children, $index + 1, 0, [$right_offset]);
        array_splice($node->keys, $index, 0, array_splice($full->keys, $this->degree - 1, 1));
        array_splice($node->values, $index, 0, array_splice($full->values, $this->degree - 1, 1));

        for ($i = 0; $i < count($node->children); $i++) {
            if ($node->children[$i] === $old_offset) {
                $node->children[$i] = $this->writeNode($full);
                break;
            }
        }

        return $this->writeNode($node);
    }

    private array $valueCache = [];

    /**
     * @internal
     */
    public function readValue(int $offset): string {
        if (isset($this->valueCache[$offset])) {
            return $this->valueCache[$offset];
        }

        $this->storage->seek($offset);
        $x    = $this->storage->read(5);
        $size = Utils::read40BitNumber($x);
        $data = $this->storage->read($size);

        return $this->valueCache[$offset] = $data;
    }

    private function insertInto(?BTreeNode $node, Key $key, string $value): int {
        $i = count($node->keys) - 1;
        while ($i >= 0 && $key->compareTo($node->keys[$i]) === -1) {
            $i--;
        }

        if ($i >= 0 && $node->keys[$i]->compareTo($key) === 0) {
            $value_offset = $this->writeValue($value);

            $node->values[$i] = $value_offset;

            return $this->writeNode($node);
        }

        $i += 1;

        // Our parent guarantees that we're not full
        if ($node->leaf) {
            array_splice($node->keys, $i, 0, [$key]);
            $value_offset = $this->writeValue($value);
            array_splice($node->values, $i, 0, [$value_offset]);

            return $this->writeNode($node);
        }

        $i_child = $this->readNode($node->children[$i]);
        if (count($i_child->keys) === $this->maxKeys) {
            $this->split($node, $i, $i_child);
            if ($node->keys[$i]->compareTo($key) === -1) {
                $i++;
            }
        }

        $new_offset         = $this->insertInto($this->readNode($node->children[$i]), $key, $value);
        $node->children[$i] = $new_offset;

        return $this->writeNode($node);
    }

    /** @return array{bool, ?BTreeNode, ?int} */
    private function find(?BTreeNode $node, Key $key): array {
        while (true) {
            $i = 0;
            while ($i < count($node->keys) && $key->compareTo($node->keys[$i]) === 1) {
                $i++;
            }

            if ($i < count($node->keys) && $node->keys[$i]->compareTo($key) === 0) {
                return [true, $node, $i];
            }

            if ($node->leaf) {
                break;
            }

            $node = $this->readNode($node->children[$i]);
        }

        return [false, null, null];
    }

    public function dot(): string {
        return "digraph G {\n" . $this->getDotFor() . "}\n";
    }

    private function getDotFor(?BTreeNode $node = null, ?int &$y = 0): string {
        if ($node === null) {
            $node = $this->root;
        }

        if ($node === null) {
            return "";
        }

        $node_name = implode(" | ", array_map(fn($key) => $key->toString(), $node->keys));

        $x = "A_$y [label=" . json_encode($node_name) . "];\n";

        $me = $y;

        foreach ($node->children as $child) {
            $y++;
            $x .= "A_$me -> A_$y;\n";
            $x .= $this->getDotFor($this->readNode($child), $y);
        }

        return $x;
    }

    public function range($from = null, $to = null, bool $fromInclusive = true, bool $toInclusive = true, bool $reverse = false): Generator {
        $from = $from !== null ? $this->getKey($from) : null;
        $to   = $to !== null ? $this->getKey($to) : null;

        $gen = $this->rangeOn($this->root, $from, $to, $fromInclusive, $toInclusive, $reverse);
        foreach ($gen as [$node, $index]) {
            yield new MapEntryWithOffsetValue($this, $node->keys[$index]->save(), $node->values[$index]);
        }
    }

    private function rangeOn(?BTreeNode $node, ?Key $from, ?Key $to, $inclusiveFrom = false, $inclusiveTo = false, $reverse = false) {
        if ($node === null) {
            return;
        }

        $i = 0;
        $z = count($node->keys);

        if ($from !== null) {
            $c = -1;
            while ($i < $z && ($c = $node->keys[$i]->compareTo($from)) === -1) {
                $i++;
            }

            if (!$inclusiveFrom && $c === 0) {
                $i++;
            }
        }

        if ($to !== null) {
            $c = 1;
            while ($z >= $i && $z > 0 && ($c = $node->keys[$z - 1]->compareTo($to)) === 1) {
                $z--;
            }

            if (!$inclusiveTo && $c === 0) {
                $z--;
            }
        }

        if ($z < $i) {
            return;
        }

        if ($reverse) {
            if (!$node->leaf) {
                yield from $this->rangeOn($this->readNode($node->children[$z]), $from, $to, $inclusiveFrom, $inclusiveTo, $reverse);
            }

            while ($i < $z--) {
                yield [$node, $z];

                if (!$node->leaf) {
                    yield from $this->rangeOn($this->readNode($node->children[$z]), $from, $to, $inclusiveFrom, $inclusiveTo, $reverse);
                }
            }

            return;
        }

        if (!$node->leaf) {
            yield from $this->rangeOn($this->readNode($node->children[$i]), $from, $to, $inclusiveFrom, $inclusiveTo);
        }

        while ($i < $z) {
            yield [$node, $i];

            $i++;

            if (!$node->leaf) {
                yield from $this->rangeOn($this->readNode($node->children[$i]), $from, $to, $inclusiveFrom, $inclusiveTo);
            }
        }
    }

    public function hasAndGet($key): array {
        $key = $this->getKey($key);

        if ($this->root === null) {
            return [false, null];
        }

        [$found, $node, $index] = $this->find($this->root, $key);
        if (!$found) {
            return [false, null];
        }

        $val = $this->readValue($node->values[$index]);

        return [true, $val];
    }

    private function loadRoot() {
        $this->storage->seek(0, SEEK_END);
        $size = $this->storage->tell();
        if ($size === 0) {
            $this->storage->lock(false);
            $this->writeHeader(-1);
            $this->storage->unlock();

            return;
        }

        $this->storage->lock();
        $this->storage->seek(0);
        $root_offset_b = $this->storage->read(13);
        if (substr($root_offset_b, 0, 8) !== "CIJBERBT") {
            throw new RuntimeException("File isn't a BTree");
        }
        $root_offset = Utils::read40BitNumber($root_offset_b, 8);

        if ($root_offset === Utils::UINT40_MAX) {
            // Root is null
            $this->root = null;

            return;
        }

        $this->root = $this->readNode($root_offset);
        $this->storage->unlock();
    }

    private function writeHeader(int $offset) {
        $header = "CIJBERBT";
        $header .= Utils::write40BitNumber($offset === -1 ? Utils::UINT40_MAX : $offset);
        $this->storage->seek(0);
        $this->storage->write($header);
    }

    private function writeNode(BTreeNode $node): int {
        unset($this->cache[$node->offset]);

        $this->storage->flush();
        $this->storage->seek(0, SEEK_END);
        $offset       = $this->storage->tell();
        $node->offset = $offset;

        $data = ($node->leaf ? ($this->root ? 'r' : 'L') : ($this->root ? 'R' : 'N')) . chr(count($node->keys));

        for ($i = 0; $i < count($node->children); $i++) {
            $data .= Utils::write40BitNumber($node->children[$i]);
        }

        for ($i = 0; $i < count($node->values); $i++) {
            $data .= Utils::write40BitNumber($node->values[$i]);
        }

        foreach ($node->keys as $key) {
            $data .= $key->serialize();
        }

        $this->storage->write($data);

        $this->cache[$offset] = $node;

        return $offset;
    }

    private function readNode(int $offset): BTreeNode {
        if (isset($this->cache[$offset])) {
            return $this->cache[$offset];
        }

        // Header / L/N, child count
        $node_size = 2;


        $this->storage->seek($offset);
        $data = $this->storage->read($node_size);
        $leaf = $data[0] === "L" || $data[0] === 'r';
        $root = $data[0] === 'R' || $data[0] === 'r';

        if (!in_array($data[0], ['r', 'R', 'L', 'N'], true)) {
            throw new RuntimeException("B-Tree storage is corrupt :(");
        }

        $key_count = ord($data[1]);

        if (!$leaf) {
            // Children Offsets
            $node_size += ($key_count + 1) * 5;
        }
        // Value Offsets
        $node_size += ($key_count * 5);
        // keys
        $node_size += ($this->key->keySize() * $key_count);

        $data .= $this->storage->read($node_size - 2);

        $node         = new BTreeNode(leaf: $leaf, root: $root);
        $node->offset = $offset;

        $j = 2;

        if (!$leaf) {
            for ($i = 0; $i < $key_count + 1; $i++) {
                $node->children[] = Utils::read40BitNumber($data, $j);
                $j                += 5;
            }
        }

        for ($i = 0; $i < $key_count; $i++) {
            $node->values[] = Utils::read40BitNumber($data, $j);
            $j              += 5;
        }

        $key_offset = $j;
        $key_size   = $this->key->keySize();
        for ($i = 0; $i < $key_count; $i++) {
            $new_key  = clone $this->key;
            $key_data = substr($data, $key_offset + ($i * $key_size), $key_size);
            $new_key->unserialize($key_data);
            $node->keys[$i] = $new_key;
        }

        $this->cache[$offset] = $node;

        return $node;
    }

    private function writeValue(string $value): int {
        $this->storage->flush();
        $this->storage->seek(0, SEEK_END);
        $value_offset = $this->storage->tell();
        $data         = Utils::write40BitNumber(strlen($value));
        $data         .= $value;

        $this->storage->write($data);
        $this->valueCache[$value_offset] = $value;

        return $value_offset;
    }

    public function hasAndDelete($key): array {
        if ($this->root === null) {
            return [false, null];
        }

        $key = $this->getKey($key);

        [$found, $value] = $this->deleteOn($this->root, $key);

        if (count($this->root->keys) === 0) {
            if ($this->root->leaf) {
                $this->root = null;
                $this->writeHeader(-1);
            } else {
                $this->root       = $this->readNode($this->root->children[0]);
                $this->root->root = true;
                $node_offset      = $this->writeNode($this->root);
                $this->writeHeader($node_offset);
            }
        } elseif ($found) {
            $this->writeNode($this->root);
            $this->writeHeader($this->root->offset);
        }

        return [$found, $value === null ? null : $this->readValue($value)];
    }

    private function deleteOn(?BTreeNode $node, $key): array {
        if ($node === null) {
            return [false, null, null];
        }

        $idx = $this->findKeyOn($node, $key);
        if ($idx < count($node->keys) && $node->keys[$idx]->compareTo($key) === 0) {
            if ($node->leaf) {
                array_splice($node->keys, $idx, 1, []);
                $values = array_splice($node->values, $idx, 1, []);

                return [true, $values[0]];
            } else {
                return $this->deleteOnBranch($node, $key, $idx);
            }
        }

        if ($node->leaf) {
            return [false, null, null];
        }

        $flag  = $idx == count($node->keys);
        $child = $this->readNode($node->children[$idx]);
        if (count($child->keys) < $this->degree) {
            $this->fill($node, $idx);
        }

        if ($flag && $idx > count($node->keys)) {
            $idx--;
        }

        $child                = $this->readNode($node->children[$idx]);
        $data                 = $this->deleteOn($child, $key);
        $node->children[$idx] = $this->writeNode($child);

        return $data;
    }

    private function findKeyOn(BTreeNode $node, $key): int {
        $i = 0;
        while ($i < count($node->keys)
          && $node->keys[$i]->compareTo($key) === -1) {
            $i++;
        }

        return $i;
    }

    private function deleteOnBranch(BTreeNode $node, $key, int $idx): array {
        $child = $this->readNode($node->children[$idx]);
        if (count($child->keys) >= $this->degree) {
            $pred             = $this->getPredecessor($node, $idx);
            $node->keys[$idx] = $pred;

            $data                 = $this->deleteOn($child, $pred);
            $node->children[$idx] = $this->writeNode($child);

            return $data;
        }

        $child = $this->readNode($node->children[$idx + 1]);
        if (count($child->keys) >= $this->degree) {
            $succ             = $this->getSuccessor($node, $idx);
            $node->keys[$idx] = $succ;

            $data                     = $this->deleteOn($child, $succ);
            $node->children[$idx + 1] = $this->writeNode($child);

            return $data;
        }

        $this->merge($node, $idx, $child);
        $child = $this->readNode($node->children[$idx]);

        $data                 = $this->deleteOn($child, $key);
        $node->children[$idx] = $this->writeNode($child);

        return $data;
    }

    private function getPredecessor(BTreeNode $node, int $idx): Key {
        $node = $this->readNode($node->children[$idx + 1]);
        while (!$node->leaf) {
            $node = $this->readNode($node->children[0]);
        }

        return $node->keys[0];
    }

    private function getSuccessor(BTreeNode $node, int $idx): Key {
        $node = $this->readNode($node->children[$idx]);
        while (!$node->leaf) {
            $node = $this->readNode($node->children[count($node->keys) - 1]);
        }

        return $node->keys[count($node->keys) - 1];
    }

    private function merge(BTreeNode $node, int $idx, BTreeNode $child) {
        $sibling = $this->readNode($node->children[$idx + 1]);

        $child->keys[$this->degree - 1]   = $node->keys[$idx];
        $child->values[$this->degree - 1] = $node->values[$idx];

        for ($i = 0; $i < count($sibling->keys); $i++) {
            $child->keys[$this->degree + $i]   = $sibling->keys[$i];
            $child->values[$this->degree + $i] = $sibling->values[$i];
        }

        if (!$sibling->leaf) {
            for ($i = 0; $i <= count($sibling->keys); $i++) {
                $child->children[$this->degree + $i] = $sibling->children[$i];
            }
        }

        array_splice($node->children, $idx + 1, 1, []);

        array_splice($node->keys, $idx, 1, []);
        array_splice($node->values, $idx, 1, []);

        $node->children[$idx] = $this->writeNode($child);
    }

    private function fill(BTreeNode $node, int $idx) {
        if ($idx !== 0 && count($this->readNode($node->children[$idx - 1])->keys) >= $this->degree) {
            $this->borrowFromPrev($node, $idx);
        } elseif (count($node->keys) !== $idx && count($this->readNode($node->children[$idx + 1])->keys) >= $this->degree) {
            $this->borrowFromNext($node, $idx);
        } else {
            if ($idx === count($node->keys)) {
                $idx--;
            }

            $child = $this->readNode($node->children[$idx]);
            $this->merge($node, $idx, $child);
        }
    }

    private function borrowFromPrev(BTreeNode $node, int $idx) {
        $child   = $this->readNode($node->children[$idx]);
        $sibling = $this->readNode($node->children[$idx + 1]);

        array_push($child->keys, $node->keys[$idx]);
        array_push($child->values, $node->values[$idx]);

        if (!$child->leaf) {
            array_push($child->children, array_shift($sibling->children));
        }

        $node->keys[$idx]   = array_shift($sibling->keys);
        $node->values[$idx] = array_shift($sibling->values);

        $node->children[$idx]     = $this->writeNode($child);
        $node->children[$idx + 1] = $this->writeNode($sibling);
    }

    private function borrowFromNext(BTreeNode $node, int $idx) {
        $child   = $this->readNode($node->children[$idx]);
        $sibling = $this->readNode($node->children[$idx - 1]);

        array_unshift($child->keys, $node->keys[$idx - 1]);
        array_unshift($child->values, $node->values[$idx - 1]);

        $child_offset = 0;
        if (!$child->leaf) {
            array_unshift($child->children, array_pop($sibling->children));
            $child_offset += 1;
        }

        $node->keys[$idx - 1]   = array_pop($sibling->keys);
        $node->values[$idx - 1] = array_pop($sibling->values);

        $node->children[$child_offset + $idx]       = $this->writeNode($child);
        $node->children[$child_offset + ($idx - 1)] = $this->writeNode($sibling);
    }

    public function has($key): bool {
        $key = $this->getKey($key);

        if ($this->root === null) {
            return false;
        }

        [$found, ,] = $this->find($this->root, $key);

        return $found;
    }

    public function entries(): Generator {
        return $this->range();
    }
}