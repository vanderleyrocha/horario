<?php

declare(strict_types=1);

use App\Modules\AG\Domain\Intensification\LNS\ALNS\AdaptiveLargeNeighborhoodSearch;
use App\Modules\AG\Domain\Intensification\LNS\ALNS\OperatorSelectionStrategy;
use App\Modules\AG\Domain\Intensification\LNS\Destroy\DestroyOperatorInterface;
use App\Modules\AG\Domain\Intensification\LNS\DTO\PartialSolution;
use App\Modules\AG\Domain\Intensification\LNS\Repair\RepairOperatorInterface;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;

it('publishes destroy and repair telemetry with improvement after alns intensification', function (): void {
    $destroy = new FakeDestroyOperator('ConflictDestroy');
    $repair = new FakeRepairOperator('RegretInsertion', 9.5);

    $selector = new FixedAlnsSelectionStrategy([
        'ConflictDestroy',
        'RegretInsertion',
    ]);

    $alns = new AdaptiveLargeNeighborhoodSearch(
        destroyOperators: [$destroy],
        repairOperators: [$repair],
        selector: $selector
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
        ]);
});

final class FixedAlnsSelectionStrategy implements OperatorSelectionStrategy
{
    /**
     * @param  string[]  $selectionOrder
     */
    public function __construct(private array $selectionOrder) {}

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
}

final class FakeDestroyOperator implements DestroyOperatorInterface
{
    public function __construct(private readonly string $name) {}

    public function destroy(Cromossomo $solution): PartialSolution
    {
        return new PartialSolution($solution->genes(), []);
    }

    public function getName(): string
    {
        return $this->name;
    }
}

final class FakeRepairOperator implements RepairOperatorInterface
{
    public function __construct(
        private readonly string $name,
        private readonly float $resultFitness
    ) {}

    public function repair(PartialSolution $partial): Cromossomo
    {
        $candidate = new Cromossomo($partial->assigned());
        $candidate->setFitness($this->resultFitness);

        return $candidate;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
