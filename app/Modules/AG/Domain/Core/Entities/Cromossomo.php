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
     * turmaId => dia => carga
     */
    private array $turmaDiaCarga = [];

    /**
     * professorId => dia => carga
     */
    private array $professorDiaCarga = [];

    /**
     * aulaId => dia => [periodos]
     */
    private array $aulaSlotsIndex = [];

    /**
     * dia => periodo => quantidade total
     */
    private array $ocupacaoGlobal = [];

    /**
     * assinatura estrutural
     */
    private string $signature = '';

    /**
     * @param Gene[] $genes
     */
    public function __construct(array $genes) {
        $this->genes = array_values($genes);

        $this->rebuildIndexes();
    }

    /* ============================================================
     | INDEXAÇÃO
     ============================================================ */

    private function rebuildIndexes(): void {
        $this->professorIndex = [];
        $this->turmaIndex = [];
        $this->cargaProfessor = [];
        $this->cargaTurma = [];
        $this->turmaDiaCarga = [];
        $this->professorDiaCarga = [];
        $this->aulaSlotsIndex = [];
        $this->ocupacaoGlobal = [];

        foreach ($this->genes as $gene) {
            $this->indexGene($gene);
        }

        $this->rebuildSignature();
    }

    private function indexGene(Gene $gene): void {
        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $aula = $gene->aulaId();

        $dia = $gene->diaSemana();
        $periodoInicial = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            $periodo = $periodoInicial + $i;

            $this->professorIndex[$prof][$dia][$periodo] = true;
            $this->turmaIndex[$turma][$dia][$periodo] = true;

            $this->turmaDiaCarga[$turma][$dia] =
                ($this->turmaDiaCarga[$turma][$dia] ?? 0) + 1;

            $this->professorDiaCarga[$prof][$dia] =
                ($this->professorDiaCarga[$prof][$dia] ?? 0) + 1;

            $this->aulaSlotsIndex[$aula][$dia][] = $periodo;

            $this->ocupacaoGlobal[$dia][$periodo] =
                ($this->ocupacaoGlobal[$dia][$periodo] ?? 0) + 1;
        }

        $this->cargaProfessor[$prof] =
            ($this->cargaProfessor[$prof] ?? 0) + $duracao;

        $this->cargaTurma[$turma] =
            ($this->cargaTurma[$turma] ?? 0) + $duracao;
    }

    private function deindexGene(Gene $gene): void {
        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $aula = $gene->aulaId();

        $dia = $gene->diaSemana();
        $periodoInicial = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            $periodo = $periodoInicial + $i;

            unset($this->professorIndex[$prof][$dia][$periodo]);
            unset($this->turmaIndex[$turma][$dia][$periodo]);

            $this->turmaDiaCarga[$turma][$dia]--;

            if ($this->turmaDiaCarga[$turma][$dia] <= 0) {
                unset($this->turmaDiaCarga[$turma][$dia]);
            }

            $this->professorDiaCarga[$prof][$dia]--;

            if ($this->professorDiaCarga[$prof][$dia] <= 0) {
                unset($this->professorDiaCarga[$prof][$dia]);
            }

            $this->ocupacaoGlobal[$dia][$periodo]--;

            if ($this->ocupacaoGlobal[$dia][$periodo] <= 0) {
                unset($this->ocupacaoGlobal[$dia][$periodo]);
            }
        }

        $this->cargaProfessor[$prof] -= $duracao;

        if ($this->cargaProfessor[$prof] <= 0) {
            unset($this->cargaProfessor[$prof]);
        }

        $this->cargaTurma[$turma] -= $duracao;

        if ($this->cargaTurma[$turma] <= 0) {
            unset($this->cargaTurma[$turma]);
        }

        unset($this->aulaSlotsIndex[$aula]);
    }

    private function rebuildSignature(): void {
        $buffer = [];

        foreach ($this->genes as $gene) {

            $buffer[] =
                $gene->aulaId()
                . '-'
                . $gene->diaSemana()
                . '-'
                . $gene->periodoDia();
        }

        sort($buffer);

        $this->signature = md5(implode('|', $buffer));
    }

    public function signature(): string {
        return $this->signature;
    }

    /* ============================================================
     | OPERAÇÕES GENÉTICAS
     ============================================================ */

    public function replaceGene(int $index, Gene $newGene): void {
        $oldGene = $this->genes[$index];

        $this->deindexGene($oldGene);

        $this->genes[$index] = $newGene;

        $this->indexGene($newGene);

        $this->rebuildSignature();
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

        $this->rebuildSignature();
    }

    /* ============================================================
     | GETTERS
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

    public function turmaDiaCarga(): array {
        return $this->turmaDiaCarga;
    }

    public function professorDiaCarga(): array {
        return $this->professorDiaCarga;
    }

    public function aulaSlotsIndex(): array {
        return $this->aulaSlotsIndex;
    }

    public function ocupacaoGlobal(): array {
        return $this->ocupacaoGlobal;
    }

    public function copy(): self {
        $clone = new self($this->genes);

        $clone->setFitness($this->fitness);

        return $clone;
    }
}
