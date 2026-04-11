<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\OperatorSelectionStrategy;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\AdaptiveDestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\DestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Intensification\LNS\Repair\AdaptiveRepairOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RepairOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;

it('publishes destroy and repair telemetry with improvement after alns intensification', function (): void {
    $destroy = makeFakeDestroyOperator('ConflictDestroy');
    $repair = makeFakeRepairOperator('RegretInsertion', 9.5);

    $selector = makeFixedAlnsSelectionStrategy([
        'ConflictDestroy',
        'RegretInsertion',
    ]);

    $alns = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [$destroy],
        repairOperators: [$repair],
        selector: $selector,
    );

    $solution = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
    ]);
    $solution->setFitness(4.0);

    $candidate = $alns->improve($solution);
    $telemetry = $alns->lastTelemetry();

    expect($candidate->fitness())->toBe(9.5)
        ->and($telemetry)->toMatchArray([
            'alns_destroy_operator' => 'ConflictDestroy',
            'alns_repair_operator' => 'RegretInsertion',
            'alns_improvement' => 5.5,
        ])
        ->and($telemetry['alns_destroy_stats'])->toMatchArray([
            'uses' => 1,
            'reward' => 5.5,
            'mean_reward' => 5.5,
        ])
        ->and($telemetry['alns_repair_stats'])->toMatchArray([
            'uses' => 1,
            'reward' => 5.5,
            'mean_reward' => 5.5,
        ])
        ->and($telemetry['alns_intensity_profile'])->toMatchArray([
            'aggression_label' => 'low',
            'trigger_reason' => null,
        ])
        ->and($telemetry['alns_recent_effectiveness'])->toMatchArray([
            'sample_size' => 1,
            'mean_improvement' => 5.5,
            'success_rate' => 1.0,
        ]);
});

it('adjusts destroy and repair intensity using landscape pressure and recent alns effectiveness', function (): void {
    $destroy = makeAdaptiveFakeDestroyOperator('ConflictDestroy');
    $repair = makeAdaptiveFakeRepairOperator('RegretInsertion', [7.0, 6.8]);

    $selector = makeFixedAlnsSelectionStrategy([
        'ConflictDestroy',
        'RegretInsertion',
        'ConflictDestroy',
        'RegretInsertion',
    ]);

    $alns = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [$destroy],
        repairOperators: [$repair],
        selector: $selector,
    );

    $solution = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
        new Gene(2, 1, 1, 2, 1, 2, 1),
        new Gene(3, 1, 1, 3, 1, 3, 1),
        new Gene(4, 1, 1, 4, 1, 4, 1),
    ]);
    $solution->setFitness(4.0);

    $firstCandidate = $alns->improve($solution, [
        'trigger' => [
            'alns_trigger_reason' => 'budget_interval+landscape_pressure',
            'alns_landscape_pressure' => true,
            'alns_real_activation_applied' => true,
            'alns_real_activation_policy' => 'basin_lock_escape',
            'alns_real_activation_mode' => 'opt_in',
        ],
        'landscape_observation' => [
            'phenomenon' => 'deep_valley',
            'basin_of_attraction_lock_detected' => true,
        ],
    ]);
    $firstTelemetry = $alns->lastTelemetry();

    $secondCandidate = $alns->improve($solution, [
        'trigger' => [
            'alns_trigger_reason' => 'budget_interval',
            'alns_landscape_pressure' => false,
        ],
        'landscape_observation' => [
            'phenomenon' => 'neutral',
            'basin_of_attraction_lock_detected' => false,
        ],
    ]);
    $secondTelemetry = $alns->lastTelemetry();

    expect($firstCandidate->fitness())->toBe(7.0)
        ->and($firstTelemetry['alns_intensity_profile']['aggression_label'])->toBe('high')
        ->and($firstTelemetry['alns_intensity_profile']['trigger_reason'])->toBe('budget_interval+landscape_pressure')
        ->and($firstTelemetry['alns_intensity_profile']['real_activation_policy'])->toBe('basin_lock_escape')
        ->and($firstTelemetry['alns_intensity_profile']['real_activation_mode'])->toBe('opt_in')
        ->and($firstTelemetry['alns_intensity_profile']['destroy_ratio'])->toBeGreaterThan(0.40)
        ->and($firstTelemetry['alns_intensity_profile']['reasons'])->toContain('activation_gate')
        ->and($firstTelemetry['alns_removed_genes'])->toBeGreaterThan(0)
        ->and($destroy->configuredIntensities[0] ?? null)->toBeGreaterThan(0.70)
        ->and($repair->configuredIntensities[0] ?? null)->toBeGreaterThan(0.80)
        ->and($secondCandidate->fitness())->toBe(6.8)
        ->and($secondTelemetry['alns_intensity_profile']['aggression_label'])->toBe('low')
        ->and($secondTelemetry['alns_intensity_profile']['recent_success_rate'])->toBe(1.0)
        ->and($secondTelemetry['alns_intensity_profile']['recent_mean_improvement'])->toBe(3.0)
        ->and($destroy->configuredIntensities[1] ?? null)->toBeLessThan($destroy->configuredIntensities[0] ?? 1.0)
        ->and($repair->configuredIntensities[1] ?? null)->toBeLessThan($repair->configuredIntensities[0] ?? 1.0);
});

it('propagates repair context to the selected alns repair operator', function (): void {
    $observed = (object) [
        'checkpoint' => null,
        'heartbeatEvent' => null,
        'limit' => null,
    ];

    $destroy = makeFakeDestroyOperator('ConflictDestroy');
    $repair = new class ($observed) implements RepairOperatorInterface {
        public function __construct(private object $observed)
        {
        }

        public function repair(PartialSolution $partial, array $context = []): Cromossomo
        {
            $this->observed->limit = $context['limits']['max_millis'] ?? null;

            if (is_callable($context['abort_if_timed_out'] ?? null)) {
                $context['abort_if_timed_out']('context_forwarded');
            }

            if (is_callable($context['progress_heartbeat'] ?? null)) {
                $context['progress_heartbeat'](['event' => 'repair_started']);
            }

            $candidate = new Cromossomo($partial->assigned());
            $candidate->setFitness(5.0);

            return $candidate;
        }

        public function getName(): string
        {
            return 'ContextAwareRepair';
        }
    };

    $selector = makeFixedAlnsSelectionStrategy([
        'ConflictDestroy',
        'ContextAwareRepair',
    ]);

    $alns = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [$destroy],
        repairOperators: [$repair],
        selector: $selector,
    );

    $solution = new Cromossomo([
        new Gene(1, 1, 1, 1, 1, 1, 1),
    ]);
    $solution->setFitness(1.0);

    $alns->improve($solution, [
        'repair_context' => [
            'abort_if_timed_out' => function (string $checkpoint = 'unknown') use ($observed): void {
                $observed->checkpoint = $checkpoint;
            },
            'progress_heartbeat' => function (array $payload) use ($observed): void {
                $observed->heartbeatEvent = $payload['event'] ?? null;
            },
            'limits' => [
                'max_millis' => 1234,
            ],
        ],
    ]);

    expect($observed->checkpoint)->toBe('context_forwarded')
        ->and($observed->heartbeatEvent)->toBe('repair_started')
        ->and($observed->limit)->toBe(1234);
});

function makeFixedAlnsSelectionStrategy(array $selectionOrder): OperatorSelectionStrategy
{
    return new class ($selectionOrder) implements OperatorSelectionStrategy {
        public function __construct(private array $selectionOrder)
        {
        }

        public function select(array $operators, array $stats): object
        {
            $target = array_shift($this->selectionOrder);

            foreach ($operators as $operator) {
                if (method_exists($operator, 'getName') && $operator->getName() === $target) {
                    return $operator;
                }
            }

            throw new RuntimeException('No operator matched the fixed ALNS selection.');
        }
    };
}

function makeFakeDestroyOperator(string $name): DestroyOperatorInterface
{
    return new class ($name) implements DestroyOperatorInterface {
        public function __construct(private readonly string $name)
        {
        }

        public function destroy(Cromossomo $solution): PartialSolution
        {
            return new PartialSolution($solution->genes(), []);
        }

        public function getName(): string
        {
            return $this->name;
        }
    };
}

function makeAdaptiveFakeDestroyOperator(string $name): AdaptiveDestroyOperatorInterface&DestroyOperatorInterface
{
    return new class ($name) implements AdaptiveDestroyOperatorInterface, DestroyOperatorInterface {
        public array $configuredIntensities = [];

        private float $intensity = 0.35;

        public function __construct(private readonly string $name)
        {
        }

        public function destroy(Cromossomo $solution): PartialSolution
        {
            $genes = $solution->genes();
            $removeCount = max(1, (int) floor(count($genes) * max(0.1, min(0.65, $this->intensity))));

            return new PartialSolution(
                array_slice($genes, $removeCount),
                array_slice($genes, 0, $removeCount),
            );
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function configureDestroyIntensity(float $intensity): void
        {
            $this->intensity = $intensity;
            $this->configuredIntensities[] = $intensity;
        }
    };
}

function makeFakeRepairOperator(string $name, float $resultFitness): RepairOperatorInterface
{
    return new class ($name, $resultFitness) implements RepairOperatorInterface {
        public function __construct(
            private readonly string $name,
            private readonly float $resultFitness,
        ) {
        }

        public function repair(PartialSolution $partial, array $context = []): Cromossomo
        {
            $candidate = new Cromossomo($partial->assigned());
            $candidate->setFitness($this->resultFitness);

            return $candidate;
        }

        public function getName(): string
        {
            return $this->name;
        }
    };
}

function makeAdaptiveFakeRepairOperator(
    string $name,
    array $resultFitnessSequence,
): AdaptiveRepairOperatorInterface&RepairOperatorInterface {
    return new class ($name, $resultFitnessSequence) implements AdaptiveRepairOperatorInterface, RepairOperatorInterface {
        public array $configuredIntensities = [];

        private float $intensity = 0.35;

        public function __construct(
            private readonly string $name,
            private array $resultFitnessSequence,
        ) {
        }

        public function repair(PartialSolution $partial, array $context = []): Cromossomo
        {
            $candidate = new Cromossomo(array_merge($partial->assigned(), $partial->unassigned()));
            $candidate->setFitness(array_shift($this->resultFitnessSequence) ?? (4.0 + $this->intensity));

            return $candidate;
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function configureRepairIntensity(float $intensity): void
        {
            $this->intensity = $intensity;
            $this->configuredIntensities[] = $intensity;
        }
    };
}
