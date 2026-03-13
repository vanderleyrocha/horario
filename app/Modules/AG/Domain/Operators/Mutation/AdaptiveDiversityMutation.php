<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Mutation;

use App\Modules\AG\Domain\Operators\Mutation\Interfaces\DiversityAwareMutationInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class AdaptiveDiversityMutation implements MutationOperatorInterface, DiversityAwareMutationInterface
{
    private float $diversity = 1.0;

    public function __construct(private readonly StructuredSwapMutation $structured, private readonly GeneSwapMutation $swap, private readonly ConflictGuidedMutation $conflict)
    {
    }

    public function setDiversity(float $diversity): void
    {
        $this->diversity = max(0.0, min(1.0, $diversity));
    }

    public function mutate(Cromossomo $individual): Cromossomo
    {
        /**
         * Alta diversidade → mutação leve
         */
        if ($this->diversity > 0.65) {
            return $this->structured->mutate($individual);
        }

        /**
         * Diversidade média
         */
        if ($this->diversity > 0.35) {

            if (mt_rand(0, 1) === 0) {
                return $this->swap->mutate($individual);
            }

            return $this->structured->mutate($individual);
        }

        /**
         * Baixa diversidade → mutação agressiva
         */
        return $this->conflict->mutate($individual);
    }

    public function getName(): string
    {
        return 'AdaptiveDiversityMutation';
    }
}
