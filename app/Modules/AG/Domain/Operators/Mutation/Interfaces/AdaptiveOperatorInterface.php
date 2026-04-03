<?php

namespace App\Modules\AG\Domain\Operators\Mutation\Interfaces;

interface AdaptiveOperatorInterface
{
    public function recordImprovement(float $improvement): void;
}
