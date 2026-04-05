<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Evaluation\SoftRules;

use App\Modules\AG\Domain\Fitness\Delta\Dependency\RuleDependency;
use App\Modules\Horarios\Domain\Constraints\Evaluators\ConstraintEvaluationPipeline;
use App\Modules\Horarios\Domain\Evaluation\Contracts\SoftRuleInterface;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\Evaluation\RuleResult;

final class CustomConstraintSoftRule implements SoftRuleInterface
{
    public function __construct(private readonly ConstraintEvaluationPipeline $pipeline)
    {
    }

    public function evaluate(EvaluationContext $context): RuleResult
    {
        $summary = $this->pipeline->evaluate($context);

        return new RuleResult(
            $summary->softPenalty(),
            self::class,
            $summary->conflictsForLevel('SOFT'),
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
        return false;
    }
}
