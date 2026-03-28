<?php

namespace App\Livewire\Algoritmo;

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Execução do Solver'])]
class ExecutionDashboard extends Component
{
    public ScheduleExecution $execution;

    public int $selectedExecutionId;

    public array $metrics = [];

    public function mount(ScheduleExecution $execution)
    {
        $this->execution = $execution;
        $this->selectedExecutionId = $execution->id;

        $this->loadMetrics();
    }

    public function cancelExecution(): void
    {
        if (! in_array($this->execution->status, ['running', 'cancel_requested'], true)) {
            return;
        }

        $this->execution->update([
            'status' => 'cancel_requested',
        ]);

        $this->execution->refresh();
        session()->flash('success', 'Cancelamento solicitado. O solver será interrompido assim que atingir um ponto seguro.');
    }

    public function loadMetrics()
    {
        $this->metrics = ScheduleGenerationMetric::where('execution_id', $this->execution->id)
            ->orderBy('generation')
            ->get()
            ->toArray();
    }

    public function updatedSelectedExecutionId(): void
    {
        if ($this->selectedExecutionId === $this->execution->id) {
            return;
        }

        $targetExecution = ScheduleExecution::query()
            ->whereKey($this->selectedExecutionId)
            ->where('horario_id', $this->execution->horario_id)
            ->first();

        if ($targetExecution === null) {
            $this->selectedExecutionId = $this->execution->id;

            return;
        }

        $this->redirect(
            route('algoritmo.execution', ['execution' => $targetExecution->id]),
            navigate: true
        );
    }

    public function getExecutionInfoProperty()
    {
        return [
            'status' => $this->translateStatus($this->execution->status),
            'population' => $this->execution->population_size,
            'islands' => $this->execution->island_count,
            'generations' => $this->execution->generations,
            'bestFitness' => $this->execution->best_fitness,
            'avgFitness' => $this->execution->avg_fitness,
            'runtime' => $this->execution->execution_time_ms,
        ];
    }

    public function getExecutionOptionsProperty()
    {
        return ScheduleExecution::query()
            ->where('horario_id', $this->execution->horario_id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ScheduleExecution $execution): array => [
                'id' => $execution->id,
                'label' => sprintf(
                    '#%d · %s · %s',
                    $execution->id,
                    $this->translateStatus($execution->status),
                    optional($execution->start_time)->format('d/m/Y H:i') ?? 'sem início'
                ),
            ])
            ->all();
    }

    public function render()
    {
        $this->execution->refresh();

        return view('livewire.algoritmo.execution-dashboard', [
            'metrics' => $this->metrics,
            'executionInfo' => $this->executionInfo,
            'executionOptions' => $this->executionOptions,
        ]);
    }

    private function translateStatus(?string $status): string
    {
        return match ($status) {
            'running' => 'em execução',
            'cancel_requested' => 'cancelamento solicitado',
            'cancelled' => 'cancelada',
            'failed' => 'falhou',
            'finished', 'completed', 'concluida', 'concluida_com_sucesso' => 'concluída',
            default => $status ?? 'desconhecido',
        };
    }
}
