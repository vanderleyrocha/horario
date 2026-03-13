<?php

namespace App\Modules\Horarios\Application;

use App\Models\Horario;
use App\Modules\AG\Domain\Representation\Entities\Cromossomo;
use App\Modules\Horarios\Domain\Builders\GeneMapper;
use Illuminate\Support\Facades\DB;

final class PersistBestSolutionService {
    public function __construct(private GeneMapper $mapper) {
    }

    public function persist(Horario $horario, Cromossomo $best): void {

        DB::transaction(function () use ($horario, $best) {

            $horario->alocacoes()->delete();

            foreach ($best->genes() as $gene) {

                $horario->alocacoes()->create(
                    $this->mapper->toArray($horario, $gene)
                );
            }
        });
    }
}
