<?php

namespace App\Services\GeneticAlgorithm\Genetico\Entities;

final class Cromossomo
{
    /** @var Gene[] */
    public array $genes = [];

    private float $fitness = INF;

    /**
     * Índices auxiliares de alta performance
     * Estrutura:
     *  - professorId => Gene[]
     *  - turmaId     => Gene[]
     *  - aulaId      => Gene[]
     */
    private array $indexProfessor = [];
    private array $indexTurma = [];
    private array $indexAula = [];

    public function __construct(array $genes = [])
    {
        $this->genes = $genes;

        if (!empty($genes)) {
            $this->buildIndexes();
        }
    }

    /* ============================================================
       FITNESS
       ============================================================ */

    public function setFitness(float $fitness): void
    {
        $this->fitness = $fitness;
    }

    public function getFitness(): float
    {
        return $this->fitness;
    }

    /* ============================================================
       GENES
       ============================================================ */

    public function addGene(Gene $gene): void
    {
        $this->genes[] = $gene;

        if ($gene->isEmpty()) {
            return;
        }

        if ($gene->professorId !== null) {
            $this->indexProfessor[$gene->professorId][] = $gene;
        }

        if ($gene->turmaId !== null) {
            $this->indexTurma[$gene->turmaId][] = $gene;
        }

        if ($gene->aulaId !== null) {
            $this->indexAula[$gene->aulaId][] = $gene;
        }
    }

    public function replaceGene(int $index, Gene $gene): void
    {
        $this->genes[$index] = $gene;
        $this->rebuildIndexes();
    }

    public function count(): int
    {
        return count($this->genes);
    }

    /* ============================================================
       INDEXES
       ============================================================ */

    private function buildIndexes(): void
    {
        foreach ($this->genes as $gene) {

            if ($gene->isEmpty()) {
                continue;
            }

            if ($gene->professorId !== null) {
                $this->indexProfessor[$gene->professorId][] = $gene;
            }

            if ($gene->turmaId !== null) {
                $this->indexTurma[$gene->turmaId][] = $gene;
            }

            if ($gene->aulaId !== null) {
                $this->indexAula[$gene->aulaId][] = $gene;
            }
        }
    }

    public function rebuildIndexes(): void
    {
        $this->indexProfessor = [];
        $this->indexTurma = [];
        $this->indexAula = [];

        $this->buildIndexes();
    }

    public function getProfessorGenes(int $professorId): array
    {
        return $this->indexProfessor[$professorId] ?? [];
    }

    public function getTurmaGenes(int $turmaId): array
    {
        return $this->indexTurma[$turmaId] ?? [];
    }

    public function getAulaGenes(int $aulaId): array
    {
        return $this->indexAula[$aulaId] ?? [];
    }

    /* ============================================================
       CLONE ULTRA RÁPIDO
       ============================================================ */

    public function copy(): self
    {
        $newGenes = [];

        foreach ($this->genes as $gene) {
            $newGenes[] = $gene->copy();
        }

        $clone = new self($newGenes);
        $clone->fitness = $this->fitness;

        return $clone;
    }

    /* ============================================================
       UTIL
       ============================================================ */

    public function hasHardConflict(): bool
    {
        return $this->fitness > 0;
    }
}