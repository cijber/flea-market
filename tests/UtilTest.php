<?php


namespace Cijber\Tests\FleaMarket;


use Cijber\FleaMarket\Op\OffsetCarrier;
use PHPUnit\Framework\TestCase;

use function Cijber\FleaMarket\intersectOffsetCarriers;
use function iter\map;
use function iter\toArray;


class UtilTest extends TestCase {
    public function testIntersect() {
        $x = [new OffsetCarrier(1, ["hello"]), new OffsetCarrier(1, ["hello"]), new OffsetCarrier(2, ["v1"])];
        $y = [new OffsetCarrier(1, ["hello"]), new OffsetCarrier(2, ["v1"])];

        $x = intersectOffsetCarriers($x, $y);
        $this->assertEquals([1, 2], toArray(map(fn(OffsetCarrier $y) => $y->getOffset(), $x)));
    }
}