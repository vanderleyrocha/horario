<?php

declare(strict_types=1);

namespace App\Modules\Horarios\Domain\Constraints\Evaluators;

use App\Modules\Horarios\Domain\Constraints\Results\ConstraintEvaluationSummary;
use App\Modules\Horarios\Domain\Evaluation\EvaluationContext;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use InvalidArgumentException;

final class ConstraintEvaluationPipeline
{
    /**
     * @var array<int, ConstraintEvaluatorInterface>
     */
    private array $evaluators;

    /**
     * @var array<string, ConstraintEvaluationSummary>
     */
    private array $cache = [];

    private ?ConstraintEvaluationSummary $lastSummary = null;

    /**
     * @param array<int, ConstraintEvaluatorInterface> $evaluators
     */
    public function __construct(array $evaluators)
    {
        $this->evaluators = array_values($evaluators);
    }

    public function evaluate(EvaluationContext $context): ConstraintEvaluationSummary
    {
        $signature = $context->cromossomo()->signature();

        if (isset($this->cache[$signature])) {
            return $this->lastSummary = $this->cache[$signature];
        }

        $summary = ConstraintEvaluationSummary::empty();

        foreach ($context->data()->customConstraints as $constraint) {
            if (! $constraint instanceof CustomConstraintData || ! $constraint->isActive) {
                continue;
            }

            $summary->merge($this->resolveEvaluator($constraint)->evaluate($constraint, $context));
        }

        $this->cache[$signature] = $summary;

        return $this->lastSummary = $summary;
    }

    public function lastSummary(): ?ConstraintEvaluationSummary
    {
        return $this->lastSummary;
    }

    public function clearCache(): void
    {
        $this->cache = [];
        $this->lastSummary = null;
    }

    private function resolveEvaluator(CustomConstraintData $constraint): ConstraintEvaluatorInterface
    {
        foreach ($this->evaluators as $evaluator) {
            if ($evaluator->supports($constraint)) {
                return $evaluator;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Nenhum evaluator registrado para constraint do tipo %s.',
            $constraint->type,
        ));
    }
}
