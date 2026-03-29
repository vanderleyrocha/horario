<?php

namespace App\Modules\AG\UI\Livewire;

use App\Models\ScheduleExecution;
use App\Models\ScheduleGenerationMetric;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class ExecutionStatusPanel extends Component
{
    public ScheduleExecution $execution;

    public array $progress = [];

    public int $metricsCount = 0;

    public function mount(ScheduleExecution $execution): void
    {
        $this->execution = $execution;
        $this->refreshState();
    }

    public function refreshState(): void
    {
        $this->execution->refresh();
        $this->metricsCount = ScheduleGenerationMetric::where('execution_id', $this->execution->id)->count();

        $progress = Cache::get("horario_geracao_{$this->execution->horario_id}", []);

        if (($progress['execution_id'] ?? null) !== null && (int) $progress['execution_id'] !== $this->execution->id) {
            $progress = [];
        }

        $this->progress = is_array($progress) ? $progress : [];
    }

    public function getElapsedProperty(): ?string
    {
        $start = $this->execution->start_time;

        if ($start === null) {
            return null;
        }

        $end = $this->execution->end_time ?? now();

        return $start->diffForHumans($end, true);
    }

    /**
     * @return array{
     *     age_seconds:int|null,
     *     age_label:string,
     *     status_label:string,
     *     message:string,
     *     badge_classes:string,
     *     panel_classes:string,
     *     is_warning:bool
     * }
     */
    public function getHeartbeatMonitorProperty(): array
    {
        $heartbeatAt = $this->resolveHeartbeatAt();
        $phase = (string) ($this->progress['phase'] ?? '');
        $stage = (string) ($this->progress['stage'] ?? '');

        if ($heartbeatAt === null) {
            return [
                'age_seconds' => null,
                'age_label' => 'aguardando heartbeat',
                'status_label' => 'sem sinal',
                'message' => 'A execucao ainda nao publicou heartbeat de progresso para esta etapa.',
                'badge_classes' => 'border-slate-200 bg-slate-100 text-slate-700',
                'panel_classes' => 'border-slate-200 bg-slate-100 text-slate-800',
                'is_warning' => false,
            ];
        }

        ['monitoring' => $monitoringThreshold, 'warning' => $warningThreshold, 'critical' => $criticalThreshold] = $this->resolveHeartbeatThresholds($phase, $stage);

        $ageInSeconds = max(0, now()->getTimestamp() - $heartbeatAt->getTimestamp());

        if ($ageInSeconds <= $monitoringThreshold) {
            return [
                'age_seconds' => $ageInSeconds,
                'age_label' => $this->formatSeconds($ageInSeconds),
                'status_label' => 'saudavel',
                'message' => 'Heartbeat recente. O solver segue publicando progresso normalmente.',
                'badge_classes' => 'border-emerald-200 bg-emerald-100 text-emerald-700',
                'panel_classes' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                'is_warning' => false,
            ];
        }

        if ($ageInSeconds <= $warningThreshold) {
            return [
                'age_seconds' => $ageInSeconds,
                'age_label' => $this->formatSeconds($ageInSeconds),
                'status_label' => 'monitorando',
                'message' => 'O heartbeat ficou mais espacoso, mas ainda esta dentro da janela esperada para esta fase.',
                'badge_classes' => 'border-sky-200 bg-sky-100 text-sky-700',
                'panel_classes' => 'border-sky-200 bg-sky-50 text-sky-900',
                'is_warning' => false,
            ];
        }

        if ($ageInSeconds <= $criticalThreshold) {
            return [
                'age_seconds' => $ageInSeconds,
                'age_label' => $this->formatSeconds($ageInSeconds),
                'status_label' => 'atencao',
                'message' => $this->buildHeartbeatWarningMessage($phase, $stage, false),
                'badge_classes' => 'border-amber-200 bg-amber-100 text-amber-800',
                'panel_classes' => 'border-amber-200 bg-amber-50 text-amber-900',
                'is_warning' => true,
            ];
        }

        return [
            'age_seconds' => $ageInSeconds,
            'age_label' => $this->formatSeconds($ageInSeconds),
            'status_label' => 'possivel estagnacao operacional',
            'message' => $this->buildHeartbeatWarningMessage($phase, $stage, true),
            'badge_classes' => 'border-rose-200 bg-rose-100 text-rose-700',
            'panel_classes' => 'border-rose-200 bg-rose-50 text-rose-900',
            'is_warning' => true,
        ];
    }

    public function getTranslatedStatusProperty(): string
    {
        return match ($this->execution->status) {
            'running' => 'em execucao',
            'cancel_requested' => 'cancelamento solicitado',
            'cancelled' => 'cancelada',
            'failed' => 'falhou',
            'finished', 'completed', 'concluida', 'concluida_com_sucesso' => 'concluida',
            default => $this->execution->status,
        };
    }

    public function render()
    {
        return view('modules.ag.livewire.algoritmo.execution-status-panel');
    }

    /**
     * @return array<string, mixed>
     */
    public function getExecutionSummaryProperty(): array
    {
        $statusContext = is_array($this->execution->status_context_json)
            ? $this->execution->status_context_json
            : [];

        if ($statusContext !== []) {
            return $statusContext;
        }

        if (
            in_array($this->execution->status, ['running', 'cancel_requested'], true)
            && $this->heartbeatMonitor['status_label'] === 'possivel estagnacao operacional'
        ) {
            return [
                'title' => 'Possivel interrupcao operacional',
                'reason' => $this->buildHeartbeatWarningMessage(
                    (string) ($this->progress['phase'] ?? ''),
                    (string) ($this->progress['stage'] ?? ''),
                    true
                ),
                'user_message' => 'O frontend perdeu os sinais de vida da execucao e nao recebeu um encerramento formal do worker.',
                'phase' => $this->progress['phase'] ?? 'monitoramento',
                'stage' => $this->progress['stage'] ?? 'sem_heartbeat',
                'attempt' => $this->progress['attempt'] ?? null,
                'queue_size' => $this->progress['queue_size'] ?? null,
                'allocations' => $this->progress['allocations'] ?? null,
                'forced_allocations' => $this->progress['forced_allocations'] ?? null,
                'hard_conflict_allocations' => $this->progress['hard_conflict_allocations'] ?? null,
                'hard_penalty' => $this->progress['hard_penalty'] ?? ($this->progress['repair_hard_penalty_after'] ?? null),
                'suggestions' => [
                    'Verifique se o worker da fila ainda esta ativo e se o processo nao foi encerrado por timeout externo.',
                    'Abra os logs desta execucao para descobrir em qual tentativa ou repair o processamento ficou preso.',
                    'Se a causa for populacao inicial muito cara, reduza a rigidez do quality gate ou melhore a heuristica de construcao inicial.',
                ],
                'initial_population_bottlenecks' => is_array($this->progress['initial_population_bottlenecks'] ?? null)
                    ? $this->progress['initial_population_bottlenecks']
                    : [],
            ];
        }

        return [];
    }

    private function resolveHeartbeatAt(): ?CarbonInterface
    {
        $timestamp = $this->progress['timestamp'] ?? null;

        if (! is_string($timestamp) || trim($timestamp) === '') {
            $timestamp = optional($this->execution->updated_at ?? $this->execution->start_time)->toDateTimeString();

            if (! is_string($timestamp) || trim($timestamp) === '') {
                return null;
            }
        }

        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{monitoring:int, warning:int, critical:int}
     */
    private function resolveHeartbeatThresholds(string $phase, string $stage): array
    {
        if ($phase === 'initial_population' && str_contains($stage, 'quality_gate_repair')) {
            return [
                'monitoring' => 30,
                'warning' => 120,
                'critical' => 360,
            ];
        }

        if ($phase === 'initial_population') {
            return [
                'monitoring' => 20,
                'warning' => 90,
                'critical' => 240,
            ];
        }

        if ($phase === 'evolving' || $phase === 'evolution') {
            return [
                'monitoring' => 15,
                'warning' => 45,
                'critical' => 120,
            ];
        }

        return [
            'monitoring' => 20,
            'warning' => 60,
            'critical' => 180,
        ];
    }

    private function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return "{$minutes}min {$remainingSeconds}s";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}min";
    }

    private function buildHeartbeatWarningMessage(string $phase, string $stage, bool $critical): string
    {
        if ($phase === 'initial_population' && str_contains($stage, 'quality_gate_repair')) {
            return $critical
                ? 'O solver esta ha bastante tempo reparando o candidato inicial sem novo heartbeat. Vale verificar se o worker travou ou se o reparo entrou em um caso muito caro.'
                : 'O reparo do quality gate esta demorando mais que o normal. O solver ainda pode estar trabalhando, mas ja merece monitoramento.';
        }

        if ($phase === 'initial_population') {
            return $critical
                ? 'A populacao inicial ficou tempo demais sem novo heartbeat. Isso costuma indicar tentativa muito cara ou travamento operacional.'
                : 'A populacao inicial esta levando mais tempo do que o esperado para publicar novo heartbeat.';
        }

        return $critical
            ? 'A execucao ficou tempo demais sem novo heartbeat. Verifique worker, fila e logs para confirmar se o solver ainda esta vivo.'
            : 'O heartbeat da execucao esta atrasado. Vale acompanhar os proximos ciclos antes de concluir que houve travamento.';
    }
}
