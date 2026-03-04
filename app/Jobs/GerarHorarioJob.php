<?php

namespace App\Jobs;

use App\Models\Horario;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Modules\AG\Application\RunGeneticAlgorithm;
use App\Modules\AG\Infrastructure\Progress\CacheProgressReporter;

class GerarHorarioJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Horario $horario) {
    }

    public function handle(): void {
        $progress = new CacheProgressReporter($this->horario->id);

        $runner = new RunGeneticAlgorithm();

        $result = $runner->execute($this->horario, $progress);

        $best = $result['best'];

        DB::transaction(function () use ($best) {

            $this->horario->alocacoes()->delete();

            foreach ($best->genes() as $gene) {

                $this->horario->alocacoes()->create([
                    'aula_id' => $gene->aulaId(),
                    'professor_id' => $gene->professorId(),
                    'turma_id' => $gene->turmaId(),
                    'disciplina_id' => $gene->disciplinaId(),
                    'dia_semana' => $gene->diaSemana(),
                    'periodo' => $gene->periodoDia(),
                    'duracao' => $gene->duracaoTempos(),
                ]);
            }
        });
    }
}
