<?php

namespace App\Modules\AG\Domain\ConflictGraph;

class ConflictNode {
    public int $geneId;

    /**
     * @var int[]
     */
    private array $neighbors = [];

    public function __construct(int $geneId) {
        $this->geneId = $geneId;
    }

    public function addNeighbor(int $id): void {
        $this->neighbors[$id] = $id;
    }

    public function removeNeighbor(int $id): void {
        unset($this->neighbors[$id]);
    }

    public function neighbors(): array {
        return $this->neighbors;
    }
}
