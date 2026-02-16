<?php

declare(strict_types=1);

namespace App\Services\GeneticAlgorithm\Genetico\Entities;

final class Cromossomo
{
    /** @var Gene[] */
    private array $genes;

    private float $fitness = 0.0;

    /*
    |--------------------------------------------------------------------------
    | Índices Estruturais (O(1))
    |--------------------------------------------------------------------------
    */

    // professorId => dia => periodo => count
    private array $professorHorarioIndex = [];

    // turmaId => dia => periodo => count
    private array $turmaHorarioIndex = [];

    // professorId => carga total
    private array $cargaProfessorIndex = [];

    // turmaId => carga total
    private array $cargaTurmaIndex = [];

    /*
    |--------------------------------------------------------------------------
    | Construtor
    |--------------------------------------------------------------------------
    */

    /**
     * @param Gene[] $genes
     */
    public function __construct(array $genes)
    {
        $this->genes = array_values($genes);
        $this->buildInitialIndexes();
    }

    /*
    |--------------------------------------------------------------------------
    | Inicialização
    |--------------------------------------------------------------------------
    */

    private function buildInitialIndexes(): void
    {
        foreach ($this->genes as $gene) {
            $this->applyGeneToIndexes($gene);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Atualização Incremental
    |--------------------------------------------------------------------------
    */

    private function applyGeneToIndexes(Gene $gene): void
    {
        $prof = $gene->getProfessorId();
        $turma = $gene->getTurmaId();
        $dia = $gene->getDiaSemana();
        $periodo = $gene->getPeriodoDia();
        $duracao = $gene->getDuracaoTempos();

        // Professor horário
        $this->professorHorarioIndex[$prof][$dia][$periodo] =
            ($this->professorHorarioIndex[$prof][$dia][$periodo] ?? 0) + 1;

        // Turma horário
        $this->turmaHorarioIndex[$turma][$dia][$periodo] =
            ($this->turmaHorarioIndex[$turma][$dia][$periodo] ?? 0) + 1;

        // Carga professor
        $this->cargaProfessorIndex[$prof] =
            ($this->cargaProfessorIndex[$prof] ?? 0) + $duracao;

        // Carga turma
        $this->cargaTurmaIndex[$turma] =
            ($this->cargaTurmaIndex[$turma] ?? 0) + $duracao;
    }

    private function removeGeneFromIndexes(Gene $gene): void
    {
        $prof = $gene->getProfessorId();
        $turma = $gene->getTurmaId();
        $dia = $gene->getDiaSemana();
        $periodo = $gene->getPeriodoDia();
        $duracao = $gene->getDuracaoTempos();

        // Professor horário
        $this->professorHorarioIndex[$prof][$dia][$periodo]--;

        if ($this->professorHorarioIndex[$prof][$dia][$periodo] <= 0) {
            unset($this->professorHorarioIndex[$prof][$dia][$periodo]);
        }

        // Turma horário
        $this->turmaHorarioIndex[$turma][$dia][$periodo]--;

        if ($this->turmaHorarioIndex[$turma][$dia][$periodo] <= 0) {
            unset($this->turmaHorarioIndex[$turma][$dia][$periodo]);
        }

        // Carga professor
        $this->cargaProfessorIndex[$prof] -= $duracao;
        if ($this->cargaProfessorIndex[$prof] <= 0) {
            unset($this->cargaProfessorIndex[$prof]);
        }

        // Carga turma
        $this->cargaTurmaIndex[$turma] -= $duracao;
        if ($this->cargaTurmaIndex[$turma] <= 0) {
            unset($this->cargaTurmaIndex[$turma]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Operações Evolutivas O(1)
    |--------------------------------------------------------------------------
    */

    public function replaceGene(int $index, Gene $novoGene): void
    {
        $geneAntigo = $this->genes[$index];

        $this->removeGeneFromIndexes($geneAntigo);

        $this->genes[$index] = $novoGene;

        $this->applyGeneToIndexes($novoGene);
    }

    public function swapGenes(int $i, int $j): void
    {
        if ($i === $j) {
            return;
        }

        $geneA = $this->genes[$i];
        $geneB = $this->genes[$j];

        $this->removeGeneFromIndexes($geneA);
        $this->removeGeneFromIndexes($geneB);

        $this->genes[$i] = $geneB;
        $this->genes[$j] = $geneA;

        $this->applyGeneToIndexes($geneB);
        $this->applyGeneToIndexes($geneA);
    }

    /*
    |--------------------------------------------------------------------------
    | Getters Estruturais (para Rules)
    |--------------------------------------------------------------------------
    */

    public function getProfessorHorarioIndex(): array
    {
        return $this->professorHorarioIndex;
    }

    public function getTurmaHorarioIndex(): array
    {
        return $this->turmaHorarioIndex;
    }

    public function getCargaProfessorIndex(): array
    {
        return $this->cargaProfessorIndex;
    }

    public function getCargaTurmaIndex(): array
    {
        return $this->cargaTurmaIndex;
    }

    /*
    |--------------------------------------------------------------------------
    | Genes
    |--------------------------------------------------------------------------
    */

    /**
     * @return Gene[]
     */
    public function getGenes(): array
    {
        return $this->genes;
    }

    public function count(): int
    {
        return count($this->genes);
    }

    /*
    |--------------------------------------------------------------------------
    | Fitness
    |--------------------------------------------------------------------------
    */

    public function getFitness(): float
    {
        return $this->fitness;
    }

    public function setFitness(float $fitness): void
    {
        $this->fitness = $fitness;
    }

    /*
    |--------------------------------------------------------------------------
    | Clone Defensivo
    |--------------------------------------------------------------------------
    */

    public function copy(): self
    {
        $clone = new self($this->genes);
        $clone->setFitness($this->fitness);
        return $clone;
    }
}