<?php

declare(strict_types=1);

use App\Models\Horario;
use App\Models\User;
use App\Modules\AG\Application\RunGeneticAlgorithm;
use App\Modules\Horarios\Application\CreateScheduleConstraintAction;
use App\Modules\Horarios\Domain\Constraints\DTO\CreateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\ValueObjects\CustomConstraintData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('loads active custom constraints into solver snapshots and logs telemetry', function (): void {
    $actor = User::factory()->create();
    $horario = Horario::factory()->create();
    $create = app(CreateScheduleConstraintAction::class);

    $create->execute(new CreateScheduleConstraintInput(
        horarioId: $horario->id,
        name: 'Sync principal',
        description: null,
        type: ConstraintType::SYNC_SAME_TIMESLOT,
        level: ConstraintLevel::HARD,
        weight: 1,
        isActive: true,
        payload: [
            'left_group' => ['lesson_ids' => [10, 11]],
            'right_group' => ['lesson_ids' => [20, 21]],
            'occurrence_mode' => 'ALL',
            'match_mode' => 'ALL_TO_ALL',
        ],
        actorId: $actor->id,
    ));

    $create->execute(new CreateScheduleConstraintInput(
        horarioId: $horario->id,
        name: 'Janela preferida',
        description: null,
        type: ConstraintType::TIME_PLACEMENT,
        level: ConstraintLevel::SOFT,
        weight: 3,
        isActive: true,
        payload: [
            'target_group' => ['lesson_ids' => [30]],
            'mode' => 'PREFERRED',
            'allowed_days' => [1, 3],
        ],
        actorId: $actor->id,
    ));

    $create->execute(new CreateScheduleConstraintInput(
        horarioId: $horario->id,
        name: 'Inativa nao entra',
        description: null,
        type: ConstraintType::MUTUAL_EXCLUSION,
        level: ConstraintLevel::SOFT,
        weight: 2,
        isActive: false,
        payload: [
            'left_group' => ['lesson_ids' => [40, 41]],
            'right_group' => ['lesson_ids' => [50]],
        ],
        actorId: $actor->id,
    ));

    Log::spy();

    $runner = app(RunGeneticAlgorithm::class);
    $resolver = new ReflectionMethod(RunGeneticAlgorithm::class, 'loadConstraintSnapshots');
    $resolver->setAccessible(true);

    $snapshots = $resolver->invoke($runner, $horario, 999);

    $snapshotTypes = array_map(static fn (CustomConstraintData $snapshot): string => $snapshot->type, $snapshots);
    sort($snapshotTypes);

    expect($snapshots)
        ->toHaveCount(2)
        ->and($snapshots[0])->toBeInstanceOf(CustomConstraintData::class)
        ->and($snapshotTypes)
        ->toBe(['SYNC_SAME_TIMESLOT', 'TIME_PLACEMENT']);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($horario): bool {
            return $message === 'solver.custom_constraints.loaded'
                && $context['horario_id'] === $horario->id
                && $context['execution_id'] === 999
                && $context['constraint_count'] === 2
                && ($context['type_breakdown']['SYNC_SAME_TIMESLOT'] ?? 0) === 1
                && ($context['type_breakdown']['TIME_PLACEMENT'] ?? 0) === 1;
        })
        ->once();
});
