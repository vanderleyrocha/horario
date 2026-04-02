<?php

declare(strict_types=1);

use App\Models\Horario;
use App\Models\User;
use App\Modules\Horarios\Application\CreateScheduleConstraintAction;
use App\Modules\Horarios\Application\DeleteScheduleConstraintAction;
use App\Modules\Horarios\Application\ListScheduleConstraintsAction;
use App\Modules\Horarios\Application\LoadActiveScheduleConstraintsAction;
use App\Modules\Horarios\Application\ToggleScheduleConstraintStatusAction;
use App\Modules\Horarios\Application\UpdateScheduleConstraintAction;
use App\Modules\Horarios\Domain\Constraints\DTO\CreateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\DTO\UpdateScheduleConstraintInput;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintType;
use App\Modules\Horarios\Domain\Constraints\Exceptions\ScheduleConstraintNotFoundException;

it('orchestrates the full constraint management cycle through application actions', function (): void {
    $actor = User::factory()->create();
    $horario = Horario::factory()->create();

    $create = app(CreateScheduleConstraintAction::class);
    $update = app(UpdateScheduleConstraintAction::class);
    $toggle = app(ToggleScheduleConstraintStatusAction::class);
    $list = app(ListScheduleConstraintsAction::class);
    $loadActive = app(LoadActiveScheduleConstraintsAction::class);
    $delete = app(DeleteScheduleConstraintAction::class);

    $created = $create->execute(new CreateScheduleConstraintInput(
        horarioId: $horario->getKey(),
        name: 'Sincronismo aplicado',
        description: 'Mantem turmas alinhadas.',
        type: ConstraintType::SYNC_SAME_TIMESLOT,
        level: ConstraintLevel::HARD,
        weight: 99,
        isActive: true,
        payload: [
            'left_group' => ['lesson_ids' => [10, 11]],
            'right_group' => ['lesson_ids' => [20, 21]],
            'occurrence_mode' => 'ALL',
            'match_mode' => 'ALL_TO_ALL',
        ],
        actorId: $actor->getKey(),
    ));

    expect($created->id())->not->toBeNull()
        ->and($created->weight())->toBe(1)
        ->and($list->execute($horario->getKey()))->toHaveCount(1)
        ->and($loadActive->execute($horario->getKey()))->toHaveCount(1);

    $updated = $update->execute(new UpdateScheduleConstraintInput(
        id: $created->id(),
        horarioId: $horario->getKey(),
        name: 'Sincronismo refinado',
        description: 'Permite coincidencia minima.',
        level: ConstraintLevel::SOFT,
        weight: 7,
        isActive: true,
        payload: [
            'left_group' => ['lesson_ids' => [10, 11]],
            'right_group' => ['lesson_ids' => [20, 21]],
            'occurrence_mode' => 'AT_LEAST_ONE',
            'match_mode' => 'ALL_TO_ALL',
        ],
        actorId: $actor->getKey(),
    ));

    expect($updated->name())->toBe('Sincronismo refinado')
        ->and($updated->level()->value)->toBe('SOFT')
        ->and($updated->weight())->toBe(7)
        ->and($updated->payload()['occurrence_mode'])->toBe('AT_LEAST_ONE');

    $disabled = $toggle->execute($updated->id(), $horario->getKey(), $actor->getKey());

    expect($disabled->isActive())->toBeFalse()
        ->and($loadActive->execute($horario->getKey()))->toHaveCount(0);

    $enabled = $toggle->execute($updated->id(), $horario->getKey(), $actor->getKey());

    expect($enabled->isActive())->toBeTrue()
        ->and($loadActive->execute($horario->getKey()))->toHaveCount(1)
        ->and($delete->execute($enabled->id(), $horario->getKey()))->toBeTrue()
        ->and($list->execute($horario->getKey()))->toHaveCount(0);
});

it('fails fast when update or toggle is requested outside the horario context', function (): void {
    $actor = User::factory()->create();
    $horario = Horario::factory()->create();
    $otherHorario = Horario::factory()->create();

    $create = app(CreateScheduleConstraintAction::class);
    $update = app(UpdateScheduleConstraintAction::class);
    $toggle = app(ToggleScheduleConstraintStatusAction::class);

    $created = $create->execute(new CreateScheduleConstraintInput(
        horarioId: $horario->getKey(),
        name: 'Restricao contextual',
        description: null,
        type: ConstraintType::TIME_PLACEMENT,
        level: ConstraintLevel::SOFT,
        weight: 3,
        isActive: true,
        payload: [
            'target_group' => ['lesson_ids' => [55]],
            'mode' => 'PREFERRED',
            'allowed_days' => [1],
        ],
        actorId: $actor->getKey(),
    ));

    expect(fn (): mixed => $update->execute(new UpdateScheduleConstraintInput(
        id: $created->id(),
        horarioId: $otherHorario->getKey(),
        name: 'Nao deveria atualizar',
        description: null,
        level: ConstraintLevel::SOFT,
        weight: 4,
        isActive: true,
        payload: [
            'target_group' => ['lesson_ids' => [55]],
            'mode' => 'PREFERRED',
            'allowed_days' => [2],
        ],
        actorId: $actor->getKey(),
    )))->toThrow(ScheduleConstraintNotFoundException::class)
        ->and(fn (): mixed => $toggle->execute($created->id(), $otherHorario->getKey(), $actor->getKey()))
        ->toThrow(ScheduleConstraintNotFoundException::class);
});
