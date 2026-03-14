<?php

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\AG\Domain\Fitness\Incremental\IncrementalRule;
use App\Modules\AG\Domain\Fitness\Delta\AffectedRegion;
use App\Modules\AG\Domain\Fitness\Delta\Dependency\RuleDependency;
use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;
use Illuminate\Support\Facades\Log;

final class WindowPenaltyRule implements SoftRuleInterface, IncrementalRule
{
    public function evaluate(EvaluationContext $context): RuleResult
    {
        $windows = $context->cromossomo()->turmaJanelas();

        $penalty = array_sum($windows);

        if (config('ag.log_window_penalty', false)) {
            Log::debug("WindowPenaltyRule: penalty = {$penalty}");
        }

        return new RuleResult($penalty, self::class);
    }

    public function evaluateIncremental(EvaluationContext $context, AffectedRegion $region): float
    {

        $windows = $context->cromossomo()->turmaJanelas();

        $penalty = 0;

        foreach ($region->turmas as $turma) {
            $penalty += $windows[$turma] ?? 0;
        }

        return $penalty;
    }

    public function dependencies(): array
    {
        return [
            RuleDependency::TURMA,
            RuleDependency::DIA
        ];
    }

    public function isHard(): bool
    {
        return false;
    }
}
