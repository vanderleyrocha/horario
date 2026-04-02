<?php

declare(strict_types=1);

use App\Modules\Horarios\Domain\Constraints\Entities\MutualExclusionConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\SyncSameTimeslotConstraint;
use App\Modules\Horarios\Domain\Constraints\Entities\TimePlacementConstraint;
use App\Modules\Horarios\Domain\Constraints\Enums\ConstraintLevel;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncMatchMode;
use App\Modules\Horarios\Domain\Constraints\Enums\SyncOccurrenceMode;
use App\Modules\Horarios\Domain\Constraints\Enums\TimePlacementMode;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\ConstraintTargetGroup;
use App\Modules\Horarios\Domain\Constraints\ValueObjects\TimePlacementWindow;

it('generates readable labels and descriptions for the initial constraint types', function (): void {
    $humanizer = new \App\Modules\Horarios\Domain\Constraints\ScheduleConstraintHumanizer();

    $sync = new SyncSameTimeslotConstraint(
        id: 1,
        horarioId: 10,
        name: 'Sincronismo de laboratorios',
        description: null,
        level: ConstraintLevel::HARD,
        weight: 1,
        isActive: true,
        leftGroup: new ConstraintTargetGroup([101, 102]),
        rightGroup: new ConstraintTargetGroup([201, 202]),
        occurrenceMode: SyncOccurrenceMode::AT_LEAST_ONE,
        matchMode: SyncMatchMode::ALL_TO_ALL,
    );

    $exclusion = new MutualExclusionConstraint(
        id: 2,
        horarioId: 10,
        name: 'Separar laboratorios',
        description: null,
        level: ConstraintLevel::SOFT,
        weight: 5,
        isActive: false,
        leftGroup: new ConstraintTargetGroup([301]),
        rightGroup: new ConstraintTargetGroup([401, 402]),
    );

    $placement = new TimePlacementConstraint(
        id: 3,
        horarioId: 10,
        name: 'Primeiros tempos',
        description: null,
        level: ConstraintLevel::SOFT,
        weight: 4,
        isActive: true,
        targetGroup: new ConstraintTargetGroup([501]),
        mode: TimePlacementMode::FORBIDDEN,
        window: new TimePlacementWindow(days: [5], periods: [5, 6]),
    );

    expect($humanizer->summarize($sync))->toMatchArray([
        'name' => 'Sincronismo de laboratorios',
        'type_label' => 'Sincronismo no mesmo horário',
        'level_label' => 'Obrigatória',
        'status_label' => 'Ativa',
    ]);

    expect($humanizer->describe($sync))->toContain('Sincroniza')
        ->toContain('ocorrencia at least one')
        ->toContain('correspondencia all to all');

    expect($humanizer->describe($exclusion))->toContain('Impede coincidencia de horario')
        ->and($humanizer->summarize($exclusion)['status_label'])->toBe('Inativa')
        ->and($humanizer->summarize($exclusion)['level_label'])->toBe('Preferencial');

    expect($humanizer->describe($placement))->toContain('Impede')
        ->toContain('dias 5')
        ->toContain('tempos 5, 6');
});
