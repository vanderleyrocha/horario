<?php

namespace App\Services\GeneticAlgorithm\Genetico\Operators;

use App\Services\GeneticAlgorithm\Genetico\Entities\Cromossomo;
use App\Services\GeneticAlgorithm\Genetico\Entities\Gene;

final class ConflictGuidedMutation implements MutationOperatorInterface
{
    public function __construct(
        private readonly array $horariosDisponiveis
    ) {}

    public function mutate(Cromossomo $cromossomo): void
    {
        if (empty($cromossomo->genes)) {
            return;
        }

        // 1️⃣ Escolhe gene aleatório
        $index = array_rand($cromossomo->genes);
        $original = $cromossomo->genes[$index];

        if ($original->isEmpty()) {
            return;
        }

        $bestCandidate = null;
        $lowestLocalConflict = INF;

        $attempts = min(8, count($this->horariosDisponiveis));

        for ($i = 0; $i < $attempts; $i++) {

            $horario = $this->horariosDisponiveis[
                array_rand($this->horariosDisponiveis)
            ];

            $candidate = new Gene(
                aulaId: $original->aulaId,
                professorId: $original->professorId,
                turmaId: $original->turmaId,
                disciplinaId: $original->disciplinaId,
                diaSemana: $horario['dia'],
                periodoDia: $horario['tempo'],
                duracaoTempos: $original->duracaoTempos
            );

            $localConflict = $this->countLocalConflicts(
                $cromossomo,
                $candidate,
                $index
            );

            if ($localConflict < $lowestLocalConflict) {
                $lowestLocalConflict = $localConflict;
                $bestCandidate = $candidate;
            }
        }

        if ($bestCandidate !== null) {
            $cromossomo->replaceGene($index, $bestCandidate);
        }
    }

    private function countLocalConflicts(
        Cromossomo $cromossomo,
        Gene $candidate,
        int $index
    ): int {

        $conflicts = 0;

        // Verifica conflitos professor
        foreach ($cromossomo->getProfessorGenes($candidate->professorId) as $gene) {

            if ($gene === $cromossomo->genes[$index]) {
                continue;
            }

            if ($this->overlaps($gene, $candidate)) {
                $conflicts++;
            }
        }

        // Verifica conflitos turma
        foreach ($cromossomo->getTurmaGenes($candidate->turmaId) as $gene) {

            if ($gene === $cromossomo->genes[$index]) {
                continue;
            }

            if ($this->overlaps($gene, $candidate)) {
                $conflicts++;
            }
        }

        return $conflicts;
    }

    private function overlaps(Gene $a, Gene $b): bool
    {
        if ($a->diaSemana !== $b->diaSemana) {
            return false;
        }

        $aStart = $a->periodoDia;
        $aEnd   = $aStart + $a->duracaoTempos - 1;

        $bStart = $b->periodoDia;
        $bEnd   = $bStart + $b->duracaoTempos - 1;

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }
}