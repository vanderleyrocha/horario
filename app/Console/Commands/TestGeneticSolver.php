<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Horario;
use App\Modules\AG\Application\RunGeneticAlgorithm;

class TestGeneticSolver extends Command
{
    protected $signature = 'solver:test 
                            {--population=30}
                            {--generations=20}';

    protected $description = 'Executa teste do motor do algoritmo genético';

    public function handle()
    {
        $this->info("=================================");
        $this->info(" TESTE DO SOLVER ");
        $this->info("=================================");

        $horario = Horario::first();

        if (!$horario) {

            $this->error("Nenhum horário encontrado no banco.");

            return Command::FAILURE;
        }

        $this->info("Executando solver para horário ID {$horario->id}");

        $start = microtime(true);

        try {

            $runner = app(RunGeneticAlgorithm::class);

            $runner->execute($horario);

            $time = round(microtime(true) - $start, 2);

            $this->info("Execução concluída.");
            $this->info("Tempo total: {$time}s");

        } catch (\Throwable $e) {

            $this->error("Erro na execução:");
            $this->error($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
