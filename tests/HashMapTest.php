<?php


namespace Cijber\Tests\FleaMarket;


use Cijber\FleaMarket\KeyValueStorage\RawHashMap;
use Cijber\FleaMarket\Storage\BufferedStorage;
use Cijber\FleaMarket\Storage\FileStorage;
use Cijber\FleaMarket\Storage\MemoryStorage;
use PHPUnit\Framework\TestCase;


class HashMapTest extends TestCase {
    public function testInsertion() {
        $b = new MemoryStorage();
        $x = new RawHashMap(storage: $b);
        foreach (range(0, 1000) as $k) {
            $x->insert("$k", "$k");
        }

        $x->insert("hello", "1");
        $this->assertEquals("1", $x->get("hello"));

        foreach (range(0, 1000) as $k) {
            $this->assertEquals("$k", $x->get("$k"));
        }

        $b->seek(0, SEEK_END);
    }

    /**
     * @group extended
     */
    public function testBigSet() {
        if (file_exists("/tmp/delete-me")) {
            unlink("/tmp/delete-me");
        }

        $data = array_map(fn($x) => "$x", json_decode(file_get_contents(__DIR__ . '/keys.json')));

        $t = new RawHashMap(new FileStorage("/tmp/delete-me"));

        foreach ($data as $i => $key) {
            $t->insert($key, $key);

            for ($j = 0; $j < $i; $j++) {
                $this->assertEquals($data[$j], $t->get($data[$j]));
            }
        }
    }

    public function testDeletion() {
        $b = new MemoryStorage();
        $x = new RawHashMap(storage: $b);
        foreach (range(0, 1000) as $k) {
            $x->insert("$k", "$k");
        }

        $x->insert("hello", "1");
        $this->assertEquals("1", $x->get("hello"));
        $this->assertEquals([true, "5"], $x->hasAndDelete("5"));
        $this->assertEquals([false, null], $x->hasAndDelete("5"));

        foreach (range(0, 1000) as $k) {
            if ($k == 5) {
                continue;
            }

            $this->assertEquals("$k", $x->get("$k"));
        }
    }
}