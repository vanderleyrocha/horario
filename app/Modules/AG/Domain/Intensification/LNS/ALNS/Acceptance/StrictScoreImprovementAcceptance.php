<?php

declare(strict_types=1);

namespace App\Modules\AG\Domain\Intensification\LNS\ALNS\Acceptance;

use App\Modules\AG\Domain\Fitness\FitnessResult;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;

final class StrictScoreImprovementAcceptance implements AlnsAcceptanceCriterion
{
    // 🔧 PRIORIDADE 2: Constantes para escape moves
    private const HARD_PENALTY_ESCAPE_THRESHOLD = -0.5;  // Aceitar se piora <= 0.5

    public function shouldAccept(Cromossomo $current, FitnessResult $currentResult, Cromossomo $candidate, FitnessResult $candidateResult, int $generation, int $stagnation): bool
    {
        $currentViable = $currentResult->score() >= 50.0;
        $candidateViable = $candidateResult->score() >= 50.0;

        // 🔧 PRIORIDADE 2: ESCAPE MOVE 1 - Inviável → Viável é SEMPRE bom!
        if ($candidateViable && !$currentViable) {
            return true;  // ← SEMPRE aceita
        }

        // Se candidato piorou para inviável, rejeita
        if (!$candidateViable && $currentViable) {
            return false;  // ← NUNCA piora de viável para inviável
        }

        // Se ambos viáveis, usar critério padrão (fitness melhor)
        if ($candidateViable && $currentViable) {
            if ($candidateResult->hardPenalty() < $currentResult->hardPenalty()) {
                return true;
            }

            if ($candidateResult->hardPenalty() > $currentResult->hardPenalty()) {
                return false;
            }

            return $candidateResult->score() > $currentResult->score();
        }

        // 🔧 PRIORIDADE 2: ESCAPE MOVE 2 - Se ambos inviáveis, aceitar se hard_penalty melhora
        if (!$candidateViable && !$currentViable) {
            $hardDelta = $candidateResult->hardPenalty() - $currentResult->hardPenalty();

            // Aceitar se reduz hard violations (mesmo que pouco)
            if ($hardDelta < self::HARD_PENALTY_ESCAPE_THRESHOLD) {
                return true;  // ← ESCAPE move inviável→inviável com melhoria
            }

            // Se hard penalty igual, tentar melhorar soft penalty
            if (abs($hardDelta) < 0.01) {
                $softDelta = $candidateResult->softPenalty() - $currentResult->softPenalty();
                return $softDelta < -0.01;  // Aceitar se soft piora pouco
            }
        }

        return false;  // Rejeita
    }
}
