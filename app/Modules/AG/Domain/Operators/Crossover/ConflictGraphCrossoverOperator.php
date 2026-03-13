<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Crossover;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;

final class ConflictGraphCrossoverOperator implements CrossoverOperatorInterface
{
    public function crossover(Cromossomo $parentA, Cromossomo $parentB): array
    {
        $size = $parentA->count();

        if ($size !== $parentB->count()) {
            throw new \RuntimeException('Pais com tamanhos diferentes.');
        }

        if ($size === 0) {
            return [$parentA->copy(), $parentB->copy()];
        }

        $genesA = $parentA->genes();
        $genesB = $parentB->genes();

        $childGenesA = [];
        $childGenesB = [];

        $profIndexA = [];
        $turmaIndexA = [];

        $profIndexB = [];
        $turmaIndexB = [];

        for ($i = 0; $i < $size; $i++) {

            $geneA = $genesA[$i];
            $geneB = $genesB[$i];

            $bestForChildA = $this->chooseBestGene($geneA, $geneB, $profIndexA, $turmaIndexA);

            $bestForChildB = $this->chooseBestGene($geneB, $geneA, $profIndexB, $turmaIndexB);

            $childGenesA[] = $bestForChildA;
            $childGenesB[] = $bestForChildB;

            $this->indexGene($bestForChildA, $profIndexA, $turmaIndexA);
            $this->indexGene($bestForChildB, $profIndexB, $turmaIndexB);
        }

        return [
            new Cromossomo($childGenesA),
            new Cromossomo($childGenesB),
        ];
    }

    public function getName(): string
    {
        return 'ConflictGraphCrossover';
    }

    private function chooseBestGene(Gene $candidateA, Gene $candidateB, array $profIndex, array $turmaIndex): Gene
    {

        $conflictsA = $this->countConflicts($candidateA, $profIndex, $turmaIndex);

        $conflictsB = $this->countConflicts($candidateB, $profIndex, $turmaIndex);

        if ($conflictsA < $conflictsB) {
            return $candidateA;
        }

        if ($conflictsB < $conflictsA) {
            return $candidateB;
        }

        return mt_rand(0, 1) === 0 ? $candidateA : $candidateB;
    }

    private function countConflicts(Gene $gene, array $profIndex, array $turmaIndex): int
    {

        $conflicts = 0;

        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $tempo = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            if (isset($profIndex[$prof][$dia][$tempo + $i])) {
                $conflicts++;
            }

            if (isset($turmaIndex[$turma][$dia][$tempo + $i])) {
                $conflicts++;
            }
        }

        return $conflicts;
    }

    private function indexGene(Gene $gene, array &$profIndex, array &$turmaIndex): void
    {

        $prof = $gene->professorId();
        $turma = $gene->turmaId();
        $dia = $gene->diaSemana();
        $tempo = $gene->periodoDia();
        $duracao = $gene->duracaoTempos();

        for ($i = 0; $i < $duracao; $i++) {

            $t = $tempo + $i;

            $profIndex[$prof][$dia][$t] = true;
            $turmaIndex[$turma][$dia][$t] = true;
        }
    }
}
