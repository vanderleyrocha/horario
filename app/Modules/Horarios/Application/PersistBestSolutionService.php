<?php

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\AG\Domain\Representation\Entities\Gene;
use App\Modules\Horarios\Domain\Builders\GeneMapper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PersistBestSolutionService
{
    public function __construct(private GeneMapper $mapper) {}

    public function persist(Horario $horario, Cromossomo $best, int $executionId): void
    {
        $this->assertNoStructuralConflicts($best);

        DB::transaction(function () use ($horario, $best, $executionId) {

            $horario->alocacoes()->delete();

            foreach ($best->genes() as $gene) {
                $alocacaoData = $this->mapper->toArray($horario, $gene);
                $alocacaoData['execution_id'] = $executionId;

                $horario->alocacoes()->create($alocacaoData);
            }
        });
    }

    private function assertNoStructuralConflicts(Cromossomo $best): void
    {
        $classSlots = [];
        $teacherSlots = [];
        $conflicts = [];

        foreach ($best->genes() as $gene) {
            /** @var Gene $gene */
            foreach ($gene->timeslots() as $periodo) {
                $classKey = implode('|', [$gene->turmaId(), $gene->diaSemana(), $periodo]);
                $teacherKey = implode('|', [$gene->professorId(), $gene->diaSemana(), $periodo]);

                if (isset($classSlots[$classKey])) {
                    $conflicts[] = sprintf(
                        'Conflito de turma: turma=%d dia=%d tempo=%d aulas=%d/%d',
                        $gene->turmaId(),
                        $gene->diaSemana(),
                        $periodo,
                        $classSlots[$classKey],
                        $gene->aulaId()
                    );
                } else {
                    $classSlots[$classKey] = $gene->aulaId();
                }

                if (isset($teacherSlots[$teacherKey])) {
                    $conflicts[] = sprintf(
                        'Conflito de professor: professor=%d dia=%d tempo=%d aulas=%d/%d',
                        $gene->professorId(),
                        $gene->diaSemana(),
                        $periodo,
                        $teacherSlots[$teacherKey],
                        $gene->aulaId()
                    );
                } else {
                    $teacherSlots[$teacherKey] = $gene->aulaId();
                }
            }
        }

        if (! empty($conflicts)) {
            throw new RuntimeException(
                'Solução inválida detectada antes de persistir: '.implode(' ; ', array_slice($conflicts, 0, 10))
            );
        }
    }
}
