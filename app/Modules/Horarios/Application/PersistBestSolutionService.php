<?php

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\Horarios\Domain\Builders\GeneMapper;
use Illuminate\Support\Facades\DB;

final class PersistBestSolutionService {
    public function __construct(private GeneMapper $mapper) {
    }

    public function persist(Horario $horario, Cromossomo $best, int $executionId): void {

        DB::transaction(function () use ($horario, $best, $executionId) {

            $horario->alocacoes()->delete();

            foreach ($best->genes() as $gene) {
                $alocacaoData = $this->mapper->toArray($horario, $gene);
                $alocacaoData['execution_id'] = $executionId;

                $horario->alocacoes()->create($alocacaoData);
            }
        });
    }
}
