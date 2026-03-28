<?php

declare(strict_types=1);

namespace App\Modules\AG\Infrastructure\Parallel;

use App\Modules\AG\Domain\Contracts\FitnessEvaluatorInterface;
use App\Modules\AG\Domain\Contracts\GeneticProblem;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use Illuminate\Support\Facades\Log;
use Spatie\Async\Pool;

final class AsyncFitnessEvaluator implements FitnessEvaluatorInterface
{
    public function __construct(
        private readonly GeneticProblem $problem,
        private readonly int $concurrency = 8,
        private readonly int $timeoutSeconds = 120
    ) {
    }

    public function evaluate(array $population): void
    {
        if ($population === []) {
            return;
        }

        if (! $this->shouldRunAsync(count($population))) {
            $this->evaluateSequentially($population);
            return;
        }

        $serializedProblem = $this->serializePayload($this->problem);

        if ($serializedProblem === null) {
            Log::warning('ag.async_evaluator.fallback_sync', [
                'reason' => 'problem_not_serializable',
            ]);

            $this->evaluateSequentially($population);
            return;
        }

        $fitnessByIndex = [];
        $failedIndexes = [];

        try {
            $pool = Pool::create()
                ->concurrency(max(1, $this->concurrency))
                ->timeout($this->timeoutSeconds)
                ->autoload(base_path('vendor/autoload.php'));

            foreach ($population as $index => $individual) {
                $serializedIndividual = $this->serializePayload($individual);

                if ($serializedIndividual === null) {
                    $failedIndexes[$index] = true;
                    continue;
                }

                $pool->add(static function () use ($index, $serializedProblem, $serializedIndividual): array {
                    /** @var GeneticProblem $problem */
                    $problem = unserialize(base64_decode($serializedProblem), ['allowed_classes' => true]);

                    /** @var Cromossomo $individual */
                    $individual = unserialize(base64_decode($serializedIndividual), ['allowed_classes' => true]);

                    $problem->evaluate($individual);

                    return [
                        'index' => $index,
                        'fitness' => $individual->fitness(),
                    ];
                })->then(function (array $result) use (&$fitnessByIndex): void {
                    $fitnessByIndex[(int) $result['index']] = (float) $result['fitness'];
                })->catch(function () use (&$failedIndexes, $index): void {
                    $failedIndexes[$index] = true;
                });
            }

            $pool->wait();
        } catch (\Throwable $e) {
            Log::warning('ag.async_evaluator.fallback_sync', [
                'reason' => 'pool_runtime_error',
                'message' => $e->getMessage(),
            ]);

            $this->evaluateSequentially($population);
            return;
        }

        foreach ($fitnessByIndex as $index => $fitness) {
            $population[$index]->setFitness($fitness);
        }

        $missingIndexes = [];

        foreach ($population as $index => $individual) {
            if (! array_key_exists($index, $fitnessByIndex)) {
                $missingIndexes[] = $index;
            }
        }

        if ($missingIndexes !== []) {
            Log::warning('ag.async_evaluator.partial_fallback_sync', [
                'missing_count' => count($missingIndexes),
                'failed_count' => count($failedIndexes),
            ]);

            $this->evaluateSequentially($population, $missingIndexes);
        }
    }

    private function shouldRunAsync(int $populationSize): bool
    {
        $parallelEnabled = true;

        if (function_exists('app') && app()->bound('config')) {
            $parallelEnabled = (bool) config('ag.parallel_evaluation', true);
        }

        if (! $parallelEnabled) {
            return false;
        }

        if ($this->concurrency <= 1 || $populationSize <= 1) {
            return false;
        }

        return Pool::isSupported();
    }

    /**
     * @param Cromossomo[] $population
     * @param int[]|null $indexes
     */
    private function evaluateSequentially(array $population, ?array $indexes = null): void
    {
        if ($indexes === null) {
            foreach ($population as $individual) {
                $this->problem->evaluate($individual);
            }

            return;
        }

        foreach ($indexes as $index) {
            if (! isset($population[$index])) {
                continue;
            }

            $this->problem->evaluate($population[$index]);
        }
    }

    private function serializePayload(object $payload): ?string
    {
        try {
            return base64_encode(serialize($payload));
        } catch (\Throwable) {
            return null;
        }
    }
}
