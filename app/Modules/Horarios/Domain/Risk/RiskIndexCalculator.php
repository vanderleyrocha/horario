<?php

namespace App\Modules\Horarios\Domain\Risk;

class RiskIndexCalculator {
    public function calculate(array $diagnostico): int {
        $score = 0;

        $score += ($diagnostico['saturacao_global'] ?? 0);
        $score += count($diagnostico['turmas_criticas'] ?? []) * 5;
        $score += count($diagnostico['professores_criticos'] ?? []) * 3;

        return min(100, $score);
    }
}
