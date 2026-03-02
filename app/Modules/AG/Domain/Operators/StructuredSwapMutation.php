<?php

namespace App\Modules\AG\Domain\Operators;

use App\Modules\AG\Domain\Core\Entities\Cromossomo;
use App\Modules\AG\Domain\Core\Entities\Gene;

final class StructuredSwapMutation implements MutationOperatorInterface {
    public function mutate(Cromossomo $cromossomo): void {
        $size = $cromossomo->count();

        if ($size < 2) {
            return;
        }

        $genes = $cromossomo->genes();

        // Seleciona gene base
        $indexA = random_int(0, $size - 1);
        $geneA = $genes[$indexA];

        // Encontra candidatos compatíveis
        $candidates = $this->findCompatibleIndices($genes, $geneA, $indexA);

        if (empty($candidates)) {
            return;
        }

        $indexB = $candidates[array_rand($candidates)];

        $geneB = $genes[$indexB];

        // Verificação extra de segurança usando método do domínio
        if ($geneA->conflictsWith($geneB)) {
            return;
        }

        $cromossomo->swapGenes($indexA, $indexB);
    }

    /**
     * Busca genes estruturalmente compatíveis:
     * - mesma turma OU mesmo professor
     * - mesma duração
     * - não pode conflitar estruturalmente
     */
    private function findCompatibleIndices(array $genes, Gene $baseGene, int $excludeIndex): array {
        $indices = [];

        foreach ($genes as $i => $gene) {

            if ($i === $excludeIndex) {
                continue;
            }

            // mesma duração
            if ($gene->duracaoTempos() !== $baseGene->duracaoTempos()) {
                continue;
            }

            // mesma turma ou mesmo professor
            if ($gene->turmaId() === $baseGene->turmaId() || $gene->professorId() === $baseGene->professorId()) {
                // evita conflito estrutural direto
                if (!$baseGene->conflictsWith($gene)) {
                    $indices[] = $i;
                }
            }
        }

        return $indices;
    }
}
