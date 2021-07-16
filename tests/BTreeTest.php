<?php


namespace Cijber\Tests\FleaMarket;


use Cijber\FleaMarket\KeyValueStorage\Key\UuidKey;
use Cijber\FleaMarket\KeyValueStorage\MapEntry;
use Cijber\FleaMarket\KeyValueStorage\RawBTree;
use Cijber\FleaMarket\Storage\BufferedStorage;
use Cijber\FleaMarket\Storage\FileStorage;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function iter\map;
use function iter\toArray;


class BTreeTest extends TestCase {
    public function testInsertion() {
        $t = new RawBTree();
        $t->insert(4, 4);
        $this->assertEquals(4, $t->get(4));
        $t->insert(4, 5);
        $this->assertEquals(5, $t->get(4));

        for ($i = 0; $i < 40; $i++) {
            $t->insert($i, $i);
        }

        for ($i = 0; $i < 40; $i++) {
            $this->assertEquals($i, $t->get($i));
        }

        $t = new RawBTree();

        for ($i = 40; $i >= 0; $i--) {
            $t->insert($i, $i);
        }

        for ($i = 40; $i >= 0; $i--) {
            $this->assertEquals($i, $t->get($i));
        }
    }

    public function testRange() {
        if (file_exists("/tmp/s")) {
            unlink("/tmp/s");
        }
        $t = new RawBTree(new FileStorage("/tmp/s"));

        for ($i = 40; $i >= 0; $i--) {
            $t->insert($i, $i);
        }


        $t = new RawBTree(new FileStorage("/tmp/s"));

        $x = $t->range(34);
        $i = 34;
        foreach ($x as [$key, $value]) {
            $this->assertEquals($i, $key);
            $this->assertEquals($i, $value);

            $i++;
        }

        $this->assertEquals(41, $i);

        $x = $t->range(34, 35);
        $i = 34;
        foreach ($x as [$key, $value]) {
            $this->assertEquals($i, $key);
            $this->assertEquals($i, $value);

            $i++;
        }

        $this->assertEquals(36, $i);

        $x = $t->range(34, 35, false);
        $i = 35;
        foreach ($x as [$key, $value]) {
            $this->assertEquals($i, $key);
            $this->assertEquals($i, $value);

            $i++;
        }

        $this->assertEquals(36, $i);

        $x = $t->range(34, 35, false, false);
        $i = 35;
        foreach ($x as [$key, $value]) {
            $this->assertEquals($i, $key);
            $this->assertEquals($i, $value);

            $i++;
        }

        $this->assertEquals(35, $i);
    }

    public function testReverseRange() {
        $t = new RawBTree();
        $t->insert("1", "1");
        $t->insert("2", "2");
        $t->insert("3", "3");
        $t->insert("4", "4");
        $t->insert("5", "5");

        $items = map(fn(MapEntry $item) => $item->key(), $t->range(1, 4, reverse: true));
        $items = toArray($items);
        $this->assertEquals(["4", "3", "2", "1"], $items);

        $items = map(fn(MapEntry $item) => $item->key(), $t->range(1, 4, reverse: true));
        $items = toArray($items);
        $this->assertEquals(["4", "3", "2", "1"], $items);

        $items = map(fn(MapEntry $item) => $item->key(), $t->range(1, 5, reverse: true));
        $items = toArray($items);
        $this->assertEquals(["5", "4", "3", "2", "1"], $items);
    }

    /**
     * @group extended
     */
    public function testBigSet() {
        $data = json_decode(file_get_contents(__DIR__ . '/keys.json'));
        $t    = new RawBTree();

        foreach ($data as $i => $key) {
            $t->insert($key, $key);

            for ($j = 0; $j < $i; $j++) {
                $this->assertEquals($data[$j], $t->get($data[$j]));
            }
        }
    }

    /**
     * @group extended
     */
    public function testBigSetWithCaching() {
        if (file_exists("/tmp/delete-me")) {
            unlink("/tmp/delete-me");
        }

        $data = json_decode(file_get_contents(__DIR__ . '/keys.json'));
        $t    = new RawBTree(new BufferedStorage(new FileStorage("/tmp/delete-me")));

        foreach ($data as $i => $key) {
            $t->insert($key, $key);

            for ($j = 0; $j < $i; $j++) {
                $this->assertEquals($data[$j], $t->get($data[$j]));
            }
        }
    }

    public function testUuidKeys() {
        $uuids = <<<UUIDS
83310e7a-332a-451e-b492-056351bca0fb
244a2948-0de1-4050-952c-b34360d51c17
f280091d-81cc-4ba8-898d-5296ea74e085
b34cf99c-bf6e-4947-82b3-937c7cd5a1db
e0419786-a0b5-415f-b7c1-3ba6149fb1fb
7bdd62b1-835f-41f3-86ec-edaedc4ba6ae
4025d0e9-4faa-4247-bda1-dc7cff9c30e5
12d42480-0a8e-4813-bcbb-21945b94c24e
c083e474-0235-43eb-83e9-8565dee9e6f8
5f05d169-fe84-49a5-9390-d71ee167d5fc
730e0285-a468-4cad-9714-fdff0c3d93c5
14367455-a1bb-4a9a-83c9-cf3989cbc0b4
732dd5e9-39c3-44ac-9241-3598241feb31
d7221694-644b-467f-86ef-5bac49b27118
712d66d6-aac8-4099-8233-8eb24040b7d0
5acd8ca0-d521-42de-8aed-b4be9fa0ffb8
62c141b1-a8ad-44f2-bd39-94bf8dbbfc43
eee80cb0-1db5-480e-951d-c799415017e1
220fe28a-df41-4268-a030-812fcfefaafd
28eadbf1-c3c3-4c36-a1b4-cbd62f280143
fb7f5b62-ea71-4442-a650-5b74d04265fb
42d88911-bdc0-40e5-88b8-cfd6d8885f97
f47592bf-1561-49c6-b461-31169ee2ea63
3ace4c68-2f9b-4348-b068-8cb9ac46f7ec
aeef6827-7e7b-409b-8df5-5c48b504802b
da38059c-5109-4f94-b1f6-cbcc556d1d8e
e55bb36d-1e64-4860-891f-cfb6afb57ac8
f8805148-e26c-45ea-a327-1bfba36bc79c
bd4e990a-838b-4230-a76b-ffe673e1e83d
d8eabe41-c436-4e20-afea-20850c2171da
67d4ee33-da88-47ac-b805-a9df80fccab2
2c12c821-3070-40d1-b2b1-d26c0f4f8287
08e82eba-d8ec-43c8-8682-47cee8bdb8f9
a8d72266-fd27-4a3f-8428-a07d58eea3e8
e45f9b10-5606-4ad3-8a30-3320b740d3a1
5e50d69e-a3c9-495e-a707-dc606d6711e2
1d3fac9a-396a-4892-9b43-178c43584eb5
3d0998a6-f14d-4a95-9d64-c52283010b22
c92e3622-3411-4fcc-b70c-259fa8f8daa5
aa127c24-40ae-47e2-a608-735ec332204f
UUIDS;
        $keys  = array_map(fn($x) => Uuid::fromString($x), array_filter(explode("\n", $uuids), fn($x) => !empty($x)));

        $t = new RawBTree(key: UuidKey::class);
        foreach ($keys as $i => $key) {
            $t->insert($key, "$i");
        }

        foreach ($keys as $i => $key) {
            $this->assertEquals($i, $t->get($key));
        }

        $d = [];

        $i = 0;
        foreach ($t->range() as [$key, $value]) {
            $this->assertFalse(isset($d[$key->toString()]));
            $d[$key->toString()] = $value;
            $i++;
        }

        $this->assertEquals(count($keys), $i);
    }

    public function testDeletion() {
        $t = new RawBTree();
        $t->insert(1, 1);
        $this->assertEquals([true, "1"], $t->hasAndDelete(1));
        $this->assertNull($t->get(1));

        $t = new RawBTree();
        $this->assertNull($t->get(1));

        foreach (range(0, 1000) as $i) {
            $t->insert($i, "$i");
        }

        $this->assertEquals([true, "34"], $t->hasAndDelete(34));
        $this->assertNull($t->get(34));

        foreach (range(0, 1000) as $i) {
            if ($i == 34) {
                continue;
            }
            $this->assertEquals("$i", $t->get($i));
        }
    }
}