<?php

namespace App\Modules\Horarios\Domain\Evaluation\Contracts;

interface HardRuleInterface extends RuleInterface
{
    public function isHard(): bool; // retorna true
}
