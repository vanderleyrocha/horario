<?php

use App\Modules\Horarios\Domain\Analysis\ScheduleFeasibilityAnalyzer;

it('uses the consolidated risk model when building the feasibility report', function () {
    $aulas = [
        fakeLesson(1, 1, 1, 'dupla', 8),
        fakeLesson(2, 1, 2, 'simples', 6),
        fakeLesson(3, 2, 2, 'tripla', 4),
    ];

    $report = (new ScheduleFeasibilityAnalyzer())->analisar($aulas, dias: 5, temposPorDia: 5);
    $payload = $report->toArray();

    expect($report->riskIndex())->toBeGreaterThan(0)
        ->and($report->riskLevel())->toBeString()
        ->and($report->structuralEntropy())->toBeFloat()
        ->and($payload)->toHaveKeys([
            'risk_index',
            'risk_level',
            'structural_entropy',
            'gargalos_estruturais',
        ])
        ->and($payload['gargalos_estruturais'])->toBeArray();
});

function fakeLesson(int $id, int $turmaId, int $professorId, string $tipo, int $aulasSemana): object
{
    return (object) [
        'id' => $id,
        'tipo' => $tipo,
        'aulas_semana' => $aulasSemana,
        'turma' => (object) ['id' => $turmaId],
        'professor' => (object) ['id' => $professorId],
    ];
}
