<?php

declare(strict_types=1);

use App\Models\Horario;
use App\Models\User;
use App\Modules\Horarios\Domain\Constraints\DTO\CreateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\DTO\UpdateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Factories\ScheduleConstraintFactory;
use App\Modules\Horarios\Domain\Constraints\Repositories\ScheduleConstraintRepository;
use Illuminate\Support\Facades\DB;

it('persists and rehydrates constraints scoped to a horario context', function (): void {
    $actor = User::factory()->create();
    $horario = Horario::factory()->create();
    $otherHorario = Horario::factory()->create();

    $domainFactory = new ScheduleConstraintFactory();
    $repository = app(ScheduleConstraintRepository::class);

    $saved = $repository->create(
        $domainFactory->fromCreateInput(new CreateScheduleConstraintInput(
            horarioId: $horario->getKey(),
            name: 'Sincronismo principal',
            description: 'Mantem grupos juntos.',
            type: ConstraintType::SYNC_SAME_TIMESLOT,
            level: ConstraintLevel::HARD,
            weight: 8,
            isActive: true,
            payload: [
                'left_group' => ['lesson_ids' => [11, 12]],
                'right_group' => ['lesson_ids' => [21, 22]],
                'occurrence_mode' => 'ALL',
                'match_mode' => 'ALL_TO_ALL',
            ],
        )),
        $actor->getKey(),
    );

    $other = $repository->create(
        $domainFactory->fromCreateInput(new CreateScheduleConstraintInput(
            horarioId: $otherHorario->getKey(),
            name: 'Outro contexto',
            description: null,
            type: ConstraintType::MUTUAL_EXCLUSION,
            level: ConstraintLevel::SOFT,
            weight: 3,
            isActive: true,
            payload: [
                'left_group' => ['lesson_ids' => [31]],
                'right_group' => ['lesson_ids' => [41]],
            ],
        )),
        $actor->getKey(),
    );

    expect($saved->id())->not->toBeNull()
        ->and($repository->findById($saved->id(), $horario->getKey())?->name())->toBe('Sincronismo principal')
        ->and($repository->findById($saved->id(), $otherHorario->getKey()))->toBeNull()
        ->and($repository->listByHorario($horario->getKey()))->toHaveCount(1)
        ->and($repository->listByHorario($otherHorario->getKey()))->toHaveCount(1)
        ->and($other->horarioId())->toBe($otherHorario->getKey());

    expect(DB::table('schedule_constraints')->where([
        'id' => $saved->id(),
        'horario_id' => $horario->getKey(),
        'created_by' => $actor->getKey(),
        'updated_by' => $actor->getKey(),
        'type' => 'SYNC_SAME_TIMESLOT',
        'level' => 'HARD',
    ])->exists())->toBeTrue();
});

it('updates filters active constraints and deletes them inside the same horario context', function (): void {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $horario = Horario::factory()->create();

    $domainFactory = new ScheduleConstraintFactory();
    $repository = app(ScheduleConstraintRepository::class);

    $saved = $repository->create(
        $domainFactory->fromCreateInput(new CreateScheduleConstraintInput(
            horarioId: $horario->getKey(),
            name: 'Janela preferida',
            description: null,
            type: ConstraintType::TIME_PLACEMENT,
            level: ConstraintLevel::SOFT,
            weight: 4,
            isActive: true,
            payload: [
                'target_group' => ['lesson_ids' => [55, 56]],
                'mode' => 'PREFERRED',
                'allowed_days' => [1, 2],
                'allowed_periods' => [1, 2],
            ],
        )),
        $creator->getKey(),
    );

    $updated = $repository->update(
        $domainFactory->fromUpdateInput(
            new UpdateScheduleConstraintInput(
                id: $saved->id(),
                horarioId: $horario->getKey(),
                name: 'Janela bloqueada',
                description: 'Nao pode cair no fim do turno.',
                level: ConstraintLevel::SOFT,
                weight: 6,
                isActive: false,
                payload: [
                    'target_group' => ['lesson_ids' => [55, 56]],
                    'mode' => 'FORBIDDEN',
                    'allowed_periods' => [5, 6],
                ],
            ),
            ConstraintType::TIME_PLACEMENT,
        ),
        $updater->getKey(),
    );

    expect($updated->name())->toBe('Janela bloqueada')
        ->and($updated->isActive())->toBeFalse()
        ->and($updated->payload()['mode'])->toBe('FORBIDDEN')
        ->and($repository->listActiveByHorario($horario->getKey()))->toHaveCount(0);

    expect(DB::table('schedule_constraints')->where([
        'id' => $saved->id(),
        'updated_by' => $updater->getKey(),
        'name' => 'Janela bloqueada',
        'is_active' => false,
    ])->exists())->toBeTrue();

    expect($repository->delete($updated->id(), $horario->getKey()))->toBeTrue()
        ->and($repository->findById($updated->id(), $horario->getKey()))->toBeNull()
        ->and(DB::table('schedule_constraints')->where('id', $saved->id())->exists())->toBeFalse();
});
