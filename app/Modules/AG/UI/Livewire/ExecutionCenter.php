<?php

namespace App\Modules\AG\UI\Livewire;

use App\Jobs\GerarHorarioJob;
use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Modules\AG\Domain\Landscape\SearchResponseActivationImpactReportBuilder;
use App\Modules\AG\Domain\Landscape\SearchResponseGlobalPolicyReadinessReportBuilder;
use App\Modules\AG\Domain\Landscape\SearchResponseHistoricalReadinessReportBuilder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Solver Center'])]
class ExecutionCenter extends Component
{
    public Horario $horario;

    public array $config = [];

    public function mount(Horario $horario)
    {
        $this->horario = $horario;

        $savedConfig = $this->horario->configuracao ?? [];

        $this->config = [
            'population' => (int) ($savedConfig['populacao'] ?? 120),
            'generations' => (int) ($savedConfig['geracoes'] ?? 500),
            // ✅ AÇÃO 06: Adicionar island_count configurável
            'islands' => (int) ($savedConfig['islands'] ?? config('ag.islands', 2)),
        ];
    }

    public function runSolver()
    {
        $execution = ScheduleExecution::create([
            'horario_id' => $this->horario->id,
            'status' => 'running',
            'start_time' => now(),
            'population_size' => $this->config['population'],
            'generations' => $this->config['generations'],
            // ✅ AÇÃO 06: Usar island_count da configuração
            'island_count' => $this->config['islands'] ?? 2,
            'parameters_json' => $this->config,
        ]);

        GerarHorarioJob::dispatch($this->horario, $execution->id);

        return redirect()->route('algoritmo.execution', $execution);
    }

    public function cancelExecution(int $executionId): void
    {
        $execution = ScheduleExecution::query()
            ->where('id', $executionId)
            ->where('horario_id', $this->horario->id)
            ->first();

        if (! $execution || ! in_array($execution->status, ['running', 'cancel_requested'], true)) {
            return;
        }

        $execution->update([
            'status' => 'cancel_requested',
        ]);
    }

    public function getExecutionsProperty()
    {
        return ScheduleExecution::where('horario_id', $this->horario->id)
            ->with([
                'latestMetric',
                'metrics' => static fn ($query) => $query
                    ->select(['id', 'execution_id', 'generation', 'best_fitness', 'avg_fitness', 'landscape_observation'])
                    ->orderBy('generation'),
            ])
            ->latest()
            ->limit(20)
            ->get();
    }

    public function getHistoricalReadinessReportProperty(): array
    {
        return (new SearchResponseHistoricalReadinessReportBuilder())
            ->build($this->executions)
            ->toArray();
    }

    public function getGlobalPolicyReadinessReportProperty(): array
    {
        return (new SearchResponseGlobalPolicyReadinessReportBuilder())
            ->build($this->executions)
            ->toArray();
    }

    public function getActivationImpactReportProperty(): array
    {
        return (new SearchResponseActivationImpactReportBuilder())
            ->build($this->executions, windowSize: 2)
            ->toArray();
    }

    public function render()
    {
        return view('modules.ag.livewire.algoritmo.execution-center', [
            'executions' => $this->executions,
            'historicalReadinessReport' => $this->historicalReadinessReport,
            'globalPolicyReadinessReport' => $this->globalPolicyReadinessReport,
            'activationImpactReport' => $this->activationImpactReport,
        ]);
    }
}
