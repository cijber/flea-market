<?php

include __DIR__ . "/../vendor/autoload.php";

use Cijber\FleaMarket\Stall;


$s = new Stall();

$s->rangeIndex('year')
  ->path('date')
  ->mutate(fn($d) => date_parse($d)['year']);

$s->insert(['date' => '2 october 2009', 'event' => 'my gander reveal']);
$s->insert(['date' => '2 october 2007', 'event' => 'php was born']);

$items = $s->find()->eq('year', 2007, true)->execute();

foreach ($items as $doc) {
    echo "{$doc[':id']} => {$doc['event']}\n"; // fe68ea18-b2ae-4c35-b7ec-9b4aa8d021be => php was born
}