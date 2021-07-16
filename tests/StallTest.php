<?php


namespace Cijber\Tests\FleaMarket;


use Cijber\FleaMarket\DirectoryStoragePool;
use Cijber\FleaMarket\Op\Range;
use Cijber\FleaMarket\Query;
use Cijber\FleaMarket\Stall;
use PHPUnit\Framework\TestCase;

use function iter\toArray;


class StallTest extends TestCase {
    public function testCrud() {
        $s = new Stall();
        $s->rangeIndex("age")->path("age");

        $doc = [
          "age" => 4,
        ];
        $doc = $s->insert($doc);

        $items = $s->find()->range("age", Range::inclusive(4, 5), true)->execute();
        $items = toArray($items);
        $this->assertCount(1, $items);

        $items = $s->find()->range("age", Range::exclusive(4, 6), true)->execute();
        $items = toArray($items);
        $this->assertCount(0, $items);

        $doc['age'] = 10;
        $s->update($doc);
    }

    public function testCrudOnDisk() {
        $s = new Stall(new DirectoryStoragePool("/tmp/stall"));
        $s->rangeIndex("gender")->path("gender");

        $four = $s->insert(["gender" => 4]);
        $six  = $s->insert(["gender" => 6]);
        $s->insert(["_comment" => "this object is agender gamers"]);


        $items = $s->find()->range("gender", Range::inclusive(4, 5), true)->execute();
        $items = toArray($items);
        $this->assertCount(1, $items);

        $items = $s->find()->has('gender', true)->execute();
        $items = toArray($items);
        $this->assertCount(2, $items);

        $items = $s->find()->range("gender", Range::exclusive(4, 6), true)->execute();
        $items = toArray($items);
        $this->assertCount(0, $items);

        $items = $s->find()->not(fn(Query $q) => $q->range("gender", Range::exclusive(4, 7)))->execute();
        $items = toArray($items);
        $this->assertCount(2, $items);

        $four['gender'] = 1;
        $s->update($four);

        $items = $s->find()->range("gender", Range::inclusive(4, 5), true)->execute();
        $items = toArray($items);
        $this->assertCount(0, $items);

        unset($six['gender']);

        $s->update($six);
        $items = $s->find()->eq("gender", 6, true)->execute();
        $items = toArray($items);
        $this->assertCount(0, $items);

        $items = $s->find()->eq("gender", 1, true)->execute();
        $items = toArray($items);
        $this->assertCount(1, $items);

        $s->delete($items[0]);

        $items = $s->find()->eq("gender", 1, true)->execute();
        $items = toArray($items);
        $this->assertCount(0, $items);

        $items = $s->find()->has('gender', true)->execute();
        $items = toArray($items);
        $this->assertCount(0, $items);

        $s = new Stall(new DirectoryStoragePool("/tmp/stall"));
        $s->rangeIndex("gender")->path("gender");

        $items = $s->find()->has('_comment')->execute();
        $items = toArray($items);
        $this->assertCount(1, $items);

        $items = $s->find()->has('gender', true)->execute();
        $items = toArray($items);
        $this->assertCount(0, $items);
    }

    public function testMultipleHitsForOneDocument() {
        $s = new Stall();
        $s->rangeIndex("gender")->path("gender");

        $s->insert(["gender" => [4, 6]]);
        $items = $s->find()->range('gender', Range::inclusive(4, 6), true)->execute();
        $items = toArray($items);
        $this->assertCount(1, $items);

        $items = $s->find()->eq('gender', 4, true)->execute();
        $items = toArray($items);
        $this->assertCount(1, $items);

        $items = $s->find()->eq('gender', 6, true)->execute();
        $items = toArray($items);
        $this->assertCount(1, $items);
    }

    protected function tearDown(): void {
        // Clean up old files
        foreach (glob("/tmp/stall/*") as $item) {
            unlink($item);
        }
    }
}