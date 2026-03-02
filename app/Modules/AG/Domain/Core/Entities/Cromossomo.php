<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Core\Entities;

final class Cromossomo {
    /** @var Gene[] */
    private array $genes;

    private float $fitness = 0.0;

    /**
     * professorId => dia => tempo => true
     */
    private array $professorIndex = [];

    /**
     * turmaId => dia => tempo => true
     */
    private array $turmaIndex = [];

    /**
     * professorId => carga total
     */
    private array $cargaProfessor = [];

    /**
     * turmaId => carga total
     */
    private array $cargaTurma = [];

    /**
     * @param Gene[] $genes
     */
    public function __construct(array $genes) {
        $this->genes = array_values($genes);
        $this->rebuildIndexes();
    }

    /* ============================================================
     |  INDEXAÇÃO
     ============================================================ */

    private function rebuildIndexes(): void {
        $this->professorIndex = [];
        $this->turmaIndex = [];
        $this->cargaProfessor = [];
        $this->cargaTurma = [];

        foreach ($this->genes as $gene) {
            $this->indexGene($gene);
        }
    }

    private function indexGene(Gene $gene): void {
        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $tempoInicial = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            $tempo = $tempoInicial + $i;

            $this->professorIndex[$prof][$dia][$tempo] = true;
            $this->turmaIndex[$turma][$dia][$tempo] = true;
        }

        $this->cargaProfessor[$prof] =
            ($this->cargaProfessor[$prof] ?? 0) + $duracao;

        $this->cargaTurma[$turma] =
            ($this->cargaTurma[$turma] ?? 0) + $duracao;
    }

    private function deindexGene(Gene $gene): void {
        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $tempoInicial = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            $tempo = $tempoInicial + $i;

            unset($this->professorIndex[$prof][$dia][$tempo]);
            unset($this->turmaIndex[$turma][$dia][$tempo]);
        }

        $this->cargaProfessor[$prof] -= $duracao;
        if ($this->cargaProfessor[$prof] <= 0) {
            unset($this->cargaProfessor[$prof]);
        }

        $this->cargaTurma[$turma] -= $duracao;
        if ($this->cargaTurma[$turma] <= 0) {
            unset($this->cargaTurma[$turma]);
        }
    }

    /* ============================================================
     |  OPERAÇÕES GENÉTICAS
     ============================================================ */

    public function replaceGene(int $index, Gene $newGene): void {
        $oldGene = $this->genes[$index];

        $this->deindexGene($oldGene);

        $this->genes[$index] = $newGene;

        $this->indexGene($newGene);
    }

    public function swapGenes(int $i, int $j): void {
        if ($i === $j) {
            return;
        }

        $geneA = $this->genes[$i];
        $geneB = $this->genes[$j];

        $this->deindexGene($geneA);
        $this->deindexGene($geneB);

        $this->genes[$i] = $geneB;
        $this->genes[$j] = $geneA;

        $this->indexGene($geneB);
        $this->indexGene($geneA);
    }

    /* ============================================================
     |  GETTERS
     ============================================================ */

    /** @return Gene[] */
    public function genes(): array {
        return $this->genes;
    }

    public function count(): int {
        return count($this->genes);
    }

    public function fitness(): float {
        return $this->fitness;
    }

    public function setFitness(float $fitness): void {
        $this->fitness = $fitness;
    }

    public function professorIndex(): array {
        return $this->professorIndex;
    }

    public function turmaIndex(): array {
        return $this->turmaIndex;
    }

    public function cargaProfessor(): array {
        return $this->cargaProfessor;
    }

    public function cargaTurma(): array {
        return $this->cargaTurma;
    }

    public function copy(): self {
        $clone = new self($this->genes);
        $clone->setFitness($this->fitness);

        return $clone;
    }
}
