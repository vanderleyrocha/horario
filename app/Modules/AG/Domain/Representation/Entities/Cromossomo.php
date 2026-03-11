<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Representation\Entities;

use App\Modules\AG\Domain\ConflictGraph\ConflictGraph;
use App\Modules\AG\Domain\ConflictGraph\ConflictGraphUpdater;
use App\Modules\AG\Domain\Evaluation\Window\WindowCalculator;

final class Cromossomo
{
    private ConflictGraph $conflictGraph;

    /** @var Gene[] */
    private array $genes;

    private float $fitness = 0.0;

    private array $professorIndex = [];
    private array $turmaIndex = [];

    /**
     * professorId => dia => periodo => [geneIndexes]
     */
    private array $professorPeriodoIndex = [];

    /**
     * turmaId => dia => periodo => [geneIndexes]
     */
    private array $turmaPeriodoIndex = [];

    private array $cargaProfessor = [];
    private array $cargaTurma = [];
    private array $turmaDiaCarga = [];
    private array $professorDiaCarga = [];

    private array $aulaSlotsIndex = [];
    private array $ocupacaoGlobal = [];

    private array $turmaJanelas = [];
    private array $professorJanelas = [];
    private array $aulaBlocos = [];

    private string $signature = '';

    public function __construct(array $genes)
    {
        $this->genes = array_values($genes);

        $this->conflictGraph = new ConflictGraph();

        $this->rebuildIndexes();
        $this->rebuildConflictGraph();
    }

    /* ============================================================
     | INDEXAÇÃO
     ============================================================ */

    private function rebuildIndexes(): void
    {
        $this->professorIndex = [];
        $this->turmaIndex = [];
        $this->professorPeriodoIndex = [];
        $this->turmaPeriodoIndex = [];
        $this->cargaProfessor = [];
        $this->cargaTurma = [];
        $this->turmaDiaCarga = [];
        $this->professorDiaCarga = [];
        $this->aulaSlotsIndex = [];
        $this->ocupacaoGlobal = [];
        $this->turmaJanelas = [];
        $this->professorJanelas = [];
        $this->aulaBlocos = [];

        foreach ($this->genes as $index => $gene) {
            $this->indexGene($index, $gene);
        }

        $this->computeWindows();
        $this->rebuildSignature();
    }

    private function rebuildConflictGraph(): void
    {
        $builder = new ConflictGraphUpdater();

        foreach ($this->genes as $index => $gene) {
            $builder->indexGene($this->conflictGraph, $index, $gene, $this->genes);
        }
    }

    private function indexGene(int $geneIndex, Gene $gene): void
    {
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
            $this->professorPeriodoIndex[$prof][$dia][$periodo][] = $geneIndex;
            $this->turmaPeriodoIndex[$turma][$dia][$periodo][] = $geneIndex;
            $this->turmaDiaCarga[$turma][$dia] = ($this->turmaDiaCarga[$turma][$dia] ?? 0) + 1;
            $this->professorDiaCarga[$prof][$dia] = ($this->professorDiaCarga[$prof][$dia] ?? 0) + 1;
            $this->aulaSlotsIndex[$aula][$dia][] = $periodo;
            $this->ocupacaoGlobal[$dia][$periodo] = ($this->ocupacaoGlobal[$dia][$periodo] ?? 0) + 1;
        }

        $this->cargaProfessor[$prof] = ($this->cargaProfessor[$prof] ?? 0) + $duracao;
        $this->cargaTurma[$turma] = ($this->cargaTurma[$turma] ?? 0) + $duracao;
        $this->aulaBlocos[$aula] = $duracao;

        $this->updateTurmaWindows($turma, $dia);
        $this->updateProfessorWindows($prof, $dia);
    }

    private function deindexGene(int $geneIndex, Gene $gene): void
    {
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

            if (isset($this->professorPeriodoIndex[$prof][$dia][$periodo])) {

                $this->professorPeriodoIndex[$prof][$dia][$periodo] =
                    array_diff($this->professorPeriodoIndex[$prof][$dia][$periodo], [$geneIndex]);
            }

            if (isset($this->turmaPeriodoIndex[$turma][$dia][$periodo])) {

                $this->turmaPeriodoIndex[$turma][$dia][$periodo] =
                    array_diff($this->turmaPeriodoIndex[$turma][$dia][$periodo], [$geneIndex]);
            }

            $this->ocupacaoGlobal[$dia][$periodo]--;
        }

        unset($this->aulaSlotsIndex[$aula]);
        unset($this->aulaBlocos[$aula]);

        $this->updateTurmaWindows($turma, $dia);
        $this->updateProfessorWindows($prof, $dia);
    }

    /* ============================================================
     | JANELAS
     ============================================================ */

    private function computeWindows(): void
    {
        foreach ($this->turmaPeriodoIndex as $turma => $dias) {

            foreach ($dias as $dia => $periodos) {

                ksort($periodos);

                $prev = null;

                foreach (array_keys($periodos) as $periodo) {

                    if ($prev !== null && $periodo - $prev > 1) {

                        $this->turmaJanelas[$turma] =
                            ($this->turmaJanelas[$turma] ?? 0) + 1;
                    }

                    $prev = $periodo;
                }
            }
        }

        foreach ($this->professorPeriodoIndex as $prof => $dias) {

            foreach ($dias as $dia => $periodos) {

                ksort($periodos);

                $prev = null;

                foreach (array_keys($periodos) as $periodo) {

                    if ($prev !== null && $periodo - $prev > 1) {

                        $this->professorJanelas[$prof] =
                            ($this->professorJanelas[$prof] ?? 0) + 1;
                    }

                    $prev = $periodo;
                }
            }
        }
    }

    private function updateTurmaWindows(int $turma, int $dia): void
    {
        $periods = [];

        if (!isset($this->turmaPeriodoIndex[$turma][$dia])) {
            $this->turmaJanelas[$turma] = 0;
            return;
        }

        foreach ($this->turmaPeriodoIndex[$turma][$dia] as $periodo => $genes) {
            $periods[] = $periodo;
        }

        $windows = WindowCalculator::compute($periods);

        $this->turmaJanelas[$turma] = $windows;
    }

    private function updateProfessorWindows(int $professor, int $dia): void
    {
        $periods = [];

        if (!isset($this->professorPeriodoIndex[$professor][$dia])) {
            $this->professorJanelas[$professor] = 0;
            return;
        }

        foreach ($this->professorPeriodoIndex[$professor][$dia] as $periodo => $genes) {
            $periods[] = $periodo;
        }

        $windows = WindowCalculator::compute($periods);

        $this->professorJanelas[$professor] = $windows;
    }

    /* ============================================================
     | ASSINATURA
     ============================================================ */

    private function rebuildSignature(): void
    {
        $buffer = [];

        foreach ($this->genes as $gene) {
            $buffer[] = $gene->aulaId() . '-' . $gene->diaSemana() . '-' . $gene->periodoDia();
        }

        sort($buffer);

        $this->signature = md5(implode('|', $buffer));
    }

    public function signature(): string
    {
        return $this->signature;
    }

    /* ============================================================
     | OPERAÇÕES GENÉTICAS
     ============================================================ */

    public function replaceGene(int $index, Gene $newGene): void
    {
        $oldGene = $this->genes[$index];

        $updater = new ConflictGraphUpdater();

        $updater->removeGene($this->conflictGraph, $index);

        $this->deindexGene($index, $oldGene);

        $this->genes[$index] = $newGene;

        $this->indexGene($index, $newGene);

        $updater->indexGene($this->conflictGraph, $index, $newGene, $this->genes);

        $this->computeWindows();
        $this->rebuildSignature();
    }

    public function swapGenes(int $i, int $j): void
    {
        if ($i === $j) {
            return;
        }

        $geneA = $this->genes[$i];
        $geneB = $this->genes[$j];

        $this->deindexGene($i, $geneA);
        $this->deindexGene($j, $geneB);

        $this->genes[$i] = $geneB;
        $this->genes[$j] = $geneA;

        $this->indexGene($i, $geneB);
        $this->indexGene($j, $geneA);

        $this->computeWindows();
        $this->rebuildSignature();
    }

    /* ============================================================
    | GETTERS
    ============================================================ */

    public function genes(): array
    {
        return $this->genes;
    }

    public function professorIndex(): array
    {
        return $this->professorIndex;
    }

    public function turmaIndex(): array
    {
        return $this->turmaIndex;
    }

    public function professorPeriodoIndex(): array
    {
        return $this->professorPeriodoIndex;
    }

    public function turmaPeriodoIndex(): array
    {
        return $this->turmaPeriodoIndex;
    }

    public function cargaProfessor(): array
    {
        return $this->cargaProfessor;
    }

    public function cargaTurma(): array
    {
        return $this->cargaTurma;
    }

    public function turmaDiaCarga(): array
    {
        return $this->turmaDiaCarga;
    }

    public function professorDiaCarga(): array
    {
        return $this->professorDiaCarga;
    }

    public function aulaSlotsIndex(): array
    {
        return $this->aulaSlotsIndex;
    }

    public function ocupacaoGlobal(): array
    {
        return $this->ocupacaoGlobal;
    }

    public function turmaJanelas(): array
    {
        return $this->turmaJanelas;
    }

    public function professorJanelas(): array
    {
        return $this->professorJanelas;
    }

    public function aulaBlocos(): array
    {
        return $this->aulaBlocos;
    }

    public function fitness(): float
    {
        return $this->fitness;
    }

    public function setFitness(float $fitness): void
    {
        $this->fitness = $fitness;
    }

    public function conflictGraph(): ConflictGraph
    {
        return $this->conflictGraph;
    }

    public function count(): int
    {
        return count($this->genes);
    }

    public function copy(): self
    {
        $clone = new self($this->genes);
        $clone->setFitness($this->fitness);
        return $clone;
    }
}
