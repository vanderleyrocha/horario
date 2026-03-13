<?php

namespace App\Modules\AG\Domain\Operators\Mutation;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;

final class StructuredSwapMutation implements MutationOperatorInterface
{
    public function mutate(Cromossomo $individual): Cromossomo
    {
        $size = $individual->count();

        if ($size < 2) {
            return $individual;
        }

        $cromossomo = $individual->copy();

        $genes = $cromossomo->genes();

        $indexA = random_int(0, $size - 1);
        $geneA = $genes[$indexA];

        $candidates =
            $this->findCompatibleIndices($genes, $geneA, $indexA);

        if (empty($candidates)) {
            return $cromossomo;
        }

        $indexB = $candidates[array_rand($candidates)];
        $geneB = $genes[$indexB];

        if ($geneA->conflictsWith($geneB)) {
            return $cromossomo;
        }

        $cromossomo->swapGenes($indexA, $indexB);

        return $cromossomo;
    }

    public function getName(): string
    {
        return 'StructuredSwapMutation';
    }

    private function findCompatibleIndices(array $genes, Gene $baseGene, int $excludeIndex): array
    {

        $indices = [];

        foreach ($genes as $i => $gene) {

            if ($i === $excludeIndex) {
                continue;
            }

            if ($gene->duracaoTempos() !== $baseGene->duracaoTempos()) {
                continue;
            }

            if (
                $gene->turmaId() === $baseGene->turmaId() ||
                $gene->professorId() === $baseGene->professorId()
            ) {
                if (!$baseGene->conflictsWith($gene)) {
                    $indices[] = $i;
                }
            }
        }

        return $indices;
    }
}
