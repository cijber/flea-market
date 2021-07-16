<?php


namespace Cijber\FleaMarket\DocumentStorage;


use Cijber\FleaMarket\Utils;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;


class DocumentHandle {
    public UuidInterface $internalId;

    public function __construct(
      public int $offset,
      public int $size,
      ?UuidInterface $internalId = null,
      public bool $deleted = false,
      public int $nextRevision = Utils::UINT40_MAX,
      public int $prevRevision = Utils::UINT40_MAX,
    ) {
        if ($internalId === null) {
            $this->internalId = Uuid::uuid4();
        } else {
            $this->internalId = $internalId;
        }
    }
}