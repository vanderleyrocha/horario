<?php

use App\Models\Horario;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Application\PersistBestSolutionService;

it('bloqueia persistencia quando houver conflito de turma no mesmo slot', function () {
    $service = app(PersistBestSolutionService::class);

    $cromossomoComConflito = new Cromossomo([
        new Gene(
            aulaId: 1001,
            professorId: 501,
            turmaId: 301,
            disciplinaId: 701,
            diaSemana: 4,
            periodoDia: 7,
            duracaoTempos: 1
        ),
        new Gene(
            aulaId: 1002,
            professorId: 502,
            turmaId: 301,
            disciplinaId: 702,
            diaSemana: 4,
            periodoDia: 7,
            duracaoTempos: 1
        ),
    ]);

    expect(function () use ($service, $cromossomoComConflito): void {
        $service->persist(Horario::factory()->make(), $cromossomoComConflito, 999);
    })->toThrow(\RuntimeException::class, 'Conflito de turma');
});
