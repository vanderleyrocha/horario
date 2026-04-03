<?php

namespace App\Modules\Horarios\Domain\Evaluation\Contracts;

interface SoftRuleInterface extends RuleInterface
{
    public function isHard(): bool; // retorna false
}
