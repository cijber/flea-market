<?php


namespace Cijber\Tests\FleaMarket;


use Cijber\FleaMarket\DirectoryStoragePool;
use Cijber\FleaMarket\DocumentStorage\JsonDocumentStore;
use Cijber\FleaMarket\MemoryStoragePool;
use PHPUnit\Framework\TestCase;


class DocumentStoreTest extends TestCase {
    public function testCrud() {
        $s = new JsonDocumentStore(new MemoryStoragePool());

        $x = $s->insert(["hello" => ":)"]);
        $y = $s->get($x);
        $this->assertEquals(":)", $y["hello"]);
    }

    public function testCrudOnDisk() {
        $s = new JsonDocumentStore(new DirectoryStoragePool("/tmp/doc-store"));

        $x = $s->insert(["hello" => ":)"]);
        $y = $s->get($x);
        $this->assertEquals(":)", $y["hello"]);
    }

    protected function tearDown(): void {
        foreach (glob("/tmp/doc-store/*") as $item) {
            unlink($item);
        }
    }
}