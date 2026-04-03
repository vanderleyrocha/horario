<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Evaluation\HardRules;

use App\Modules\AG\Domain\Fitness\Delta\Dependency\RuleDependency;
use App\Modules\Horarios\Domain\Constraints\Evaluators\ConstraintEvaluationPipeline;
use App\Modules\Horarios\Domain\Evaluation\Contracts\HardRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class CustomConstraintHardRule implements HardRuleInterface
{
    public function __construct(private readonly ConstraintEvaluationPipeline $pipeline) {}

    public function evaluate(EvaluationContext $context): RuleResult
    {
        $summary = $this->pipeline->evaluate($context);

        return new RuleResult(
            $summary->hardPenalty(),
            self::class,
            $summary->conflictsForLevel('HARD'),
        );
    }

    public function dependencies(): array
    {
        return [
            RuleDependency::TURMA,
            RuleDependency::SLOT,
        ];
    }

    public function isHard(): bool
    {
        return true;
    }
}
