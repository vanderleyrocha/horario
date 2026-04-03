<?php

namespace App\Console\Commands;

use App\Models\Horario;
use App\Models\ScheduleExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RepairScheduleExecutionIntegrity extends Command
{
    protected $signature = 'horarios:repair-execution-integrity {--dry-run : Apenas exibe o que seria corrigido}';

    protected $description = 'Corrige inconsistencias entre alocacoes.execution_id e schedule_executions.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $mode = $dryRun ? 'DRY RUN' : 'APPLY';

        $this->info("[{$mode}] Iniciando auditoria de integridade de execucoes...");

        $orphanGroups = DB::table('alocacoes as a')
            ->leftJoin('schedule_executions as se', 'se.id', '=', 'a.execution_id')
            ->whereNotNull('a.execution_id')
            ->whereNull('se.id')
            ->select('a.horario_id', 'a.execution_id', DB::raw('COUNT(*) as total'))
            ->groupBy('a.horario_id', 'a.execution_id')
            ->orderBy('a.horario_id')
            ->orderBy('a.execution_id')
            ->get();

        $mismatchGroups = DB::table('alocacoes as a')
            ->join('schedule_executions as se', 'se.id', '=', 'a.execution_id')
            ->whereColumn('a.horario_id', '!=', 'se.horario_id')
            ->select(
                'a.horario_id as aloc_horario_id',
                'se.horario_id as exec_horario_id',
                'a.execution_id',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('a.horario_id', 'se.horario_id', 'a.execution_id')
            ->orderBy('a.horario_id')
            ->orderBy('a.execution_id')
            ->get();

        if ($orphanGroups->isEmpty() && $mismatchGroups->isEmpty()) {
            $this->info('Nenhuma inconsistencia encontrada.');

            return self::SUCCESS;
        }

        $this->line("Orfaos encontrados: {$orphanGroups->count()} grupo(s).");
        $this->line("Mismatches encontrados: {$mismatchGroups->count()} grupo(s).");

        DB::transaction(function () use ($orphanGroups, $mismatchGroups, $dryRun): void {
            foreach ($orphanGroups as $group) {
                $stats = DB::table('alocacoes')
                    ->where('horario_id', $group->horario_id)
                    ->where('execution_id', $group->execution_id)
                    ->selectRaw('MIN(created_at) as min_created_at, MAX(updated_at) as max_updated_at')
                    ->first();

                $horario = Horario::query()->find($group->horario_id);
                $config = is_array($horario?->configuracao) ? $horario->configuracao : [];
                $startAt = $stats->min_created_at ? Carbon::parse($stats->min_created_at) : now();
                $endAt = $stats->max_updated_at ? Carbon::parse($stats->max_updated_at) : $startAt;

                $payload = [
                    'id' => (int) $group->execution_id,
                    'horario_id' => (int) $group->horario_id,
                    'start_time' => $startAt,
                    'end_time' => $endAt,
                    'status' => 'finished',
                    'population_size' => isset($config['populacao']) ? (int) $config['populacao'] : null,
                    'generations' => isset($config['geracoes']) ? (int) $config['geracoes'] : null,
                    'parameters_json' => ! empty($config) ? json_encode($config) : null,
                    'created_at' => $startAt,
                    'updated_at' => $endAt,
                ];

                $this->warn(sprintf(
                    'Orfao: horario_id=%d execution_id=%d alocacoes=%d',
                    $group->horario_id,
                    $group->execution_id,
                    $group->total
                ));

                if (! $dryRun) {
                    DB::table('schedule_executions')->insert($payload);
                }
            }

            foreach ($mismatchGroups as $group) {
                $sourceExecution = ScheduleExecution::query()->find((int) $group->execution_id);

                if (! $sourceExecution) {
                    continue;
                }

                $this->warn(sprintf(
                    'Mismatch: execution_id=%d aloc_horario=%d exec_horario=%d alocacoes=%d',
                    $group->execution_id,
                    $group->aloc_horario_id,
                    $group->exec_horario_id,
                    $group->total
                ));

                if ($dryRun) {
                    continue;
                }

                $clonedExecution = ScheduleExecution::query()->create([
                    'horario_id' => (int) $group->aloc_horario_id,
                    'start_time' => $sourceExecution->start_time,
                    'end_time' => $sourceExecution->end_time,
                    'status' => 'finished',
                    'population_size' => $sourceExecution->population_size,
                    'island_count' => $sourceExecution->island_count,
                    'generations' => $sourceExecution->generations,
                    'generations_without_improvement' => $sourceExecution->generations_without_improvement,
                    'parameters_json' => $sourceExecution->parameters_json,
                    'best_fitness' => $sourceExecution->best_fitness,
                    'avg_fitness' => $sourceExecution->avg_fitness,
                    'execution_time_ms' => $sourceExecution->execution_time_ms,
                    'created_at' => $sourceExecution->created_at ?? now(),
                    'updated_at' => $sourceExecution->updated_at ?? now(),
                ]);

                DB::table('alocacoes')
                    ->where('horario_id', (int) $group->aloc_horario_id)
                    ->where('execution_id', (int) $group->execution_id)
                    ->update(['execution_id' => $clonedExecution->id]);
            }
        });

        if ($dryRun) {
            $this->info('Dry run concluido. Nenhum dado foi alterado.');

            return self::SUCCESS;
        }

        $this->info('Correcao concluida com sucesso.');

        return self::SUCCESS;
    }
}
