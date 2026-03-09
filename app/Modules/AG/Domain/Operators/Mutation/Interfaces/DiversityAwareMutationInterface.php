<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Operators\Mutation\Interfaces;

interface DiversityAwareMutationInterface {
    public function setDiversity(float $diversity): void;
}
