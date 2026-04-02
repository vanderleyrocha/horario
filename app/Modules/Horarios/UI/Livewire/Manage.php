<?php

namespace App\Modules\Horarios\UI\Livewire;

use App\Models\Horario;
use App\Models\ScheduleExecution;
use App\Modules\Horarios\Domain\Risk\RiskClassification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.app-layout', ['title' => 'Gerenciar Horário'])]
class Manage extends Component
{
    private const ALLOWED_TABS = [
        'overview',
        'constraints',
        'config',
        'aulas',
        'restricoes',
        'algoritmo',
        'diagnostico',
    ];

    public Horario $horario;

    public string $tab = 'overview';

    protected $queryString = ['tab'];

    public function mount(Horario $horario)
    {
        $this->horario = $horario;
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function setTab(string $tab)
    {
        $this->tab = $this->normalizeTab($tab);
    }

    public function render()
    {
        return view('modules.horarios.livewire.manage');
    }

    public function diagnosticoData(): array
    {
        $diagnostico = $this->horario->diagnostico_json ?? [];

        if (is_array($diagnostico)) {
            return $diagnostico;
        }

        if (is_string($diagnostico) && $diagnostico !== '') {
            $decoded = json_decode($diagnostico, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function overviewStats(): array
    {
        $diagnostico = $this->diagnosticoData();
        $resumoDiagnostico = $this->extractDiagnosticoResumo($diagnostico);

        $aulas = $this->horario->aulas()
            ->selectRaw('COUNT(*) as total_registros')
            ->selectRaw('COALESCE(SUM(aulas_semana), 0) as total_aulas_semana')
            ->selectRaw("COALESCE(SUM(aulas_semana * CASE tipo WHEN 'dupla' THEN 2 WHEN 'tripla' THEN 3 ELSE 1 END), 0) as total_tempos")
            ->selectRaw('COUNT(DISTINCT turma_id) as total_turmas')
            ->selectRaw('COUNT(DISTINCT professor_id) as total_professores')
            ->selectRaw('COUNT(DISTINCT disciplina_id) as total_disciplinas')
            ->selectRaw('COALESCE(SUM(CASE WHEN ativa = 1 THEN 1 ELSE 0 END), 0) as total_ativas')
            ->first();

        $execucoes = $this->horario->executions()
            ->selectRaw('COUNT(*) as total_execucoes')
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('finished', 'completed', 'concluida', 'concluida_com_sucesso') THEN 1 ELSE 0 END), 0) as total_concluidas")
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('running', 'cancel_requested') THEN 1 ELSE 0 END), 0) as total_em_execucao")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as total_falhas")
            ->selectRaw('MAX(best_fitness) as melhor_fitness_execucao')
            ->selectRaw('AVG(best_fitness) as media_fitness_execucao')
            ->selectRaw('AVG(execution_time_ms) as tempo_medio_execucao_ms')
            ->first();

        $ultimaExecucao = $this->horario->executions()
            ->latest('start_time')
            ->first([
                'id',
                'status',
                'start_time',
                'end_time',
                'best_fitness',
                'execution_time_ms',
                'generations',
                'population_size',
                'island_count',
            ]);

        $execucoesRecentes = $this->horario->executions()
            ->latest('start_time')
            ->limit(5)
            ->get([
                'id',
                'status',
                'start_time',
                'end_time',
                'best_fitness',
                'execution_time_ms',
                'generations',
                'population_size',
                'island_count',
            ])
            ->map(fn (ScheduleExecution $execution): array => $this->mapExecutionSummary($execution))
            ->all();

        $indiceRisco = (int) ($this->horario->indice_risco ?? data_get($resumoDiagnostico, 'indice_risco', 0));
        $classificacaoRisco = (new RiskClassification())->classify($indiceRisco);
        $conflitosHard = (int) ($this->horario->conflitos_hard ?? 0);
        $conflitosSoft = (int) ($this->horario->conflitos_soft ?? 0);

        return [
            'aulas' => [
                'total_registros' => (int) ($aulas?->total_registros ?? 0),
                'total_aulas_semana' => (int) ($aulas?->total_aulas_semana ?? 0),
                'total_tempos' => (int) ($aulas?->total_tempos ?? 0),
                'total_turmas' => (int) ($aulas?->total_turmas ?? 0),
                'total_professores' => (int) ($aulas?->total_professores ?? 0),
                'total_disciplinas' => (int) ($aulas?->total_disciplinas ?? 0),
                'total_ativas' => (int) ($aulas?->total_ativas ?? 0),
            ],
            'execucoes' => [
                'total' => (int) ($execucoes?->total_execucoes ?? 0),
                'concluidas' => (int) ($execucoes?->total_concluidas ?? 0),
                'em_execucao' => (int) ($execucoes?->total_em_execucao ?? 0),
                'falhas' => (int) ($execucoes?->total_falhas ?? 0),
                'melhor_fitness' => $execucoes?->melhor_fitness_execucao,
                'media_fitness' => $execucoes?->media_fitness_execucao,
                'tempo_medio' => $this->formatMilliseconds($execucoes?->tempo_medio_execucao_ms),
            ],
            'saude' => [
                'indice_risco' => $indiceRisco,
                'nivel_risco' => $classificacaoRisco,
                'conflitos_hard' => $conflitosHard,
                'conflitos_soft' => $conflitosSoft,
                'total_conflitos' => $conflitosHard + $conflitosSoft,
                'saturacao_global' => data_get($resumoDiagnostico, 'saturacao_global'),
                'turmas_criticas' => (int) data_get($resumoDiagnostico, 'turmas_criticas', 0),
                'professores_criticos' => (int) data_get($resumoDiagnostico, 'professores_criticos', 0),
                'aulas_duplas_problema' => (int) data_get($resumoDiagnostico, 'aulas_duplas_problema', 0),
                'diagnostico_disponivel' => $resumoDiagnostico !== [],
            ],
            'restricoes' => $this->horario->restricoes()->count(),
            'alocacoes' => $this->horario->alocacoes()->count(),
            'tempo_processamento' => $this->formatSeconds($this->horario->tempo_processamento_segundos),
            'ultima_execucao' => $this->mapExecutionSummary($ultimaExecucao),
            'execucoes_recentes' => $execucoesRecentes,
        ];
    }

    public function tabs(): array
    {
        return [
            'overview' => 'Visão Geral',
            'constraints' => 'Constraints',
            'config' => 'Configuração',
            'aulas' => 'Aulas',
            'restricoes' => 'Restrições',
            'algoritmo' => 'Algoritmo',
            'diagnostico' => 'Diagnóstico',
        ];
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, self::ALLOWED_TABS, true) ? $tab : 'overview';
    }

    private function extractDiagnosticoResumo(array $diagnostico): array
    {
        $resumo = data_get($diagnostico, 'resumo');

        if (is_array($resumo)) {
            return $resumo;
        }

        $nestedResumo = data_get($diagnostico, 'dados.resumo');

        return is_array($nestedResumo) ? $nestedResumo : [];
    }

    private function mapExecutionSummary(?ScheduleExecution $execution): ?array
    {
        if (! $execution) {
            return null;
        }

        return [
            'id' => $execution->id,
            'status' => $this->statusLabel($execution->status),
            'status_raw' => $execution->status,
            'iniciada_em' => $execution->start_time?->format('d/m/Y H:i'),
            'finalizada_em' => $execution->end_time?->format('d/m/Y H:i'),
            'tempo_execucao' => $this->formatMilliseconds($execution->execution_time_ms),
            'best_fitness' => $execution->best_fitness,
            'generations' => $execution->generations,
            'population_size' => $execution->population_size,
            'island_count' => $execution->island_count,
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'running' => 'Em execução',
            'cancel_requested' => 'Cancelamento solicitado',
            'cancelled' => 'Cancelada',
            'failed' => 'Falhou',
            'finished', 'completed', 'concluida', 'concluida_com_sucesso' => 'Concluída',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Não informada',
        };
    }

    private function formatSeconds(?int $seconds): string
    {
        if (! $seconds) {
            return '-';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dmin', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dmin %02ds', $minutes, $remainingSeconds);
        }

        return sprintf('%ds', $remainingSeconds);
    }

    private function formatMilliseconds(float|int|null $milliseconds): string
    {
        if (! $milliseconds) {
            return '-';
        }

        $totalSeconds = (int) round($milliseconds / 1000);

        return $this->formatSeconds($totalSeconds);
    }
}
