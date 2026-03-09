<?php

namespace App\Modules\AG\Domain\ConflictGraph;

class ConflictGraph {
    /** @var ConflictNode[] */
    private array $nodes = [];

    public function ensureNode(int $geneId): ConflictNode {
        if (!isset($this->nodes[$geneId])) {
            $this->nodes[$geneId] = new ConflictNode($geneId);
        }

        return $this->nodes[$geneId];
    }

    public function addEdge(int $a, int $b): void {
        $this->ensureNode($a)->addNeighbor($b);
        $this->ensureNode($b)->addNeighbor($a);
    }

    public function removeEdge(int $a, int $b): void {
        $this->nodes[$a]?->removeNeighbor($b);
        $this->nodes[$b]?->removeNeighbor($a);
    }

    public function neighbors(int $geneId): array {
        return $this->nodes[$geneId]?->neighbors() ?? [];
    }
}
