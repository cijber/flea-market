<?php


namespace Cijber\FleaMarket;


class Index {

    private $mutators = [];
    private $inputMutator = [];
    private $jmesRuntime = null;

    public function __construct(
      private bool $rangeIndex = false,
      private $key = null,
    ) {
    }

    public function setJmesRuntime(callable $jmesRuntime): void {
        $this->jmesRuntime = $jmesRuntime;
    }

    public function mutate(callable $mutator): static {
        $this->mutators[] = $mutator;

        return $this;
    }

    public function path(string $path): static {
        return $this->mutate(fn($object) => ($this->jmesRuntime)($path, $object));
    }

    public function mutateInput(callable $inputMutator): static {
        $this->inputMutator[] = $inputMutator;

        return $this;
    }

    public function process($data) {
        for ($i = 0; $i < count($this->mutators); $i++) {
            $data = ($this->mutators[$i])($data);
        }

        return $data;
    }

    public function processInput($data) {
        for ($i = 0; $i < count($this->inputMutator); $i++) {
            $data = ($this->inputMutator[$i])($data);
        }

        return $data;
    }

    public function isRangeIndex(): bool {
        return $this->rangeIndex;
    }

    public function getKey() {
        return $this->key;
    }
}