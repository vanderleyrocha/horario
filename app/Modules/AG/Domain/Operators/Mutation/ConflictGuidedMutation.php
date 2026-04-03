<?php

namespace App\Modules\AG\Domain\Operators\Mutation;

use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class ConflictGuidedMutation implements MutationOperatorInterface
{
    public function __construct(private readonly int $maxDias, private readonly int $maxPeriodosPorDia) {}

    public function mutate(Cromossomo $cromossomo): Cromossomo
    {
        $size = $cromossomo->count();

        if ($size === 0) {
            return $cromossomo;
        }

        $genes = $cromossomo->genes();

        $index = random_int(0, $size - 1);
        $gene = $genes[$index];

        $duracao = $gene->duracaoTempos();

        $novoDia = random_int(1, $this->maxDias);

        $maxPeriodoValido = max(1, $this->maxPeriodosPorDia - $duracao + 1);

        $novoPeriodo = random_int(1, $maxPeriodoValido);

        $novoGene = $gene->withDiaPeriodo($novoDia, $novoPeriodo);

        $child = $cromossomo->copy();
        $child->replaceGene($index, $novoGene);

        return $child;
    }

    public function getName(): string
    {
        return 'ConflictGuidedMutation';
    }
}
