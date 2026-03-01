<?php

namespace App\Livewire\Algoritmo;

use App\Models\Horario;
use App\Jobs\GerarHorarioJob;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Layout('components.app-layout', ['title' => 'Gerar Horário'])]
class Index extends Component {
    public Horario $horario;

    // Estados principais
    public bool $emGeracao = false;
    public bool $temErro = false;
    public bool $concluido = false;

    public int $progressoPercentual = 0;
    public string $faseProgresso = '';

    public string $statusGeracao = 'pronto';

    public ?string $mensagemStatus = null;
    public ?string $mensagemErro = null;

    public array $erroDetalhes = [];
    public array $configuracao = [];
    public array $mapaSaturacao = [];

    protected array $rules = [
        'configuracao.populacao' => 'required|integer|min:10',
        'configuracao.geracoes' => 'required|integer|min:1',
        'configuracao.taxa_mutacao' => 'required|numeric|between:0,1',
        'configuracao.taxa_crossover' => 'required|numeric|between:0,1',
    ];

    /* ============================================================
     |  LIFECYCLE
     ============================================================ */

    public function mount(Horario $horario): void {
        // dd($horario->id);
        $this->horario = $horario->loadMissing([
            'aulas.professor',
            'aulas.turma',
            'configuracaoHorario'
        ]);

        $this->configuracao = $this->horario->configuracao ?? [
            'populacao' => 100,
            'geracoes' => 500,
            'taxa_mutacao' => 0.3,
            'taxa_crossover' => 0.7,
        ];
        $this->sincronizarComCache();
        $this->gerarMapaSaturacao();
    }

    /* ============================================================
     |  GERAÇÃO
     ============================================================ */

    public function iniciarGeracao(): void {
        $this->validate();

        $this->resetEstado();

        $this->emGeracao = true;
        $this->statusGeracao = 'executando';
        $this->mensagemStatus = 'Iniciando geração...';
        Log::info("Iniciando geração de horário disparando Job", [
            'horario_id' => $this->horario->id,
            'horario_nome' => $this->horario->nome,
        ]);

        GerarHorarioJob::dispatch($this->horario);

        $this->dispatch('startPolling');
    }

    public function atualizarStatus(): void {
        $cache = Cache::get($this->cacheKey());

        if (!$cache) {
            return;
        }

        if (($cache['status'] ?? null) === 'erro') {
            $this->aplicarErro($cache['erro'] ?? []);
            return;
        }

        if (($cache['status'] ?? null) === 'concluido') {
            $this->emGeracao = false;
            $this->concluido = true;
            $this->statusGeracao = 'concluido';
            $this->mensagemStatus = 'Horário gerado com sucesso.';
            $this->dispatch('stopPolling');
        }

        if (($cache['status'] ?? null) === 'executando') {

            $this->progressoPercentual = $cache['percentual'] ?? 0;
            $this->faseProgresso = $cache['fase'] ?? '';
            $this->mensagemStatus = $cache['mensagem'] ?? '';

            return;
        }
    }

    public function cancelarGeracao(): void {
        Cache::forget($this->cacheKey());

        $this->resetEstado();
        $this->statusGeracao = 'cancelado';
        $this->mensagemStatus = 'Geração cancelada.';
    }

    public function gerarNovamente(): void {
        $this->resetEstado();
        $this->iniciarGeracao();
    }

    /* ============================================================
     |  ESTADO
     ============================================================ */

    private function aplicarErro(array $erro): void {
        $this->resetEstado();

        $this->temErro = true;
        $this->statusGeracao = 'erro';

        $this->mensagemErro = $erro['mensagem'] ?? 'Erro desconhecido.';
        $this->erroDetalhes = $erro;

        /*
        |--------------------------------------------------------------------------
        | SALVAR DIAGNÓSTICO AUTOMATICAMENTE
        |--------------------------------------------------------------------------
        */

        if (!empty($erro['dados'])) {

            $this->horario->diagnostico_json = $erro['dados'];

            $this->horario->indice_risco =
                $erro['dados']['risk_index']
                ?? $this->calcularRiscoFallback($erro['dados']);

            $this->horario->status = 'rascunho';
            $this->horario->save();
        }

        $this->dispatch('stopPolling');
    }

    private function resetEstado(): void {
        $this->emGeracao = false;
        $this->temErro = false;
        $this->concluido = false;
        $this->mensagemErro = null;
        $this->erroDetalhes = [];
    }

    private function sincronizarComCache(): void {
        $cache = Cache::get($this->cacheKey());

        if (!$cache) {
            $this->statusGeracao = 'pronto';
            $this->mensagemStatus = 'Aguardando geração.';
            return;
        }

        if (($cache['status'] ?? null) === 'erro') {
            $this->aplicarErro($cache['erro'] ?? []);
        }
    }

    private function cacheKey(): string {
        return "horario_geracao_{$this->horario->id}";
    }

    /* ============================================================
     |  MAPA SATURAÇÃO
     ============================================================ */

    private function gerarMapaSaturacao(): void {
        if (!$this->horario->configuracaoHorario) {
            $this->mapaSaturacao = [];
            return;
        }

        $dias = $this->horario->configuracaoHorario->dias_semana ?? 0;
        $tempos = $this->horario->configuracaoHorario->aulas_por_dia ?? 0;

        if ($dias <= 0 || $tempos <= 0) {
            $this->mapaSaturacao = [];
            return;
        }

        $capacidade = $dias * $tempos;

        $professores = [];
        $turmas = [];

        foreach ($this->horario->aulas as $aula) {

            $duracao = match ($aula->tipo) {
                'simples' => 1,
                'dupla' => 2,
                'tripla' => 3,
                default => 1,
            };

            $carga = $aula->aulas_semana * $duracao;

            if ($aula->professor) {
                $professores[$aula->professor->nome] =
                    ($professores[$aula->professor->nome] ?? 0) + $carga;
            }

            if ($aula->turma) {
                $turmas[$aula->turma->nome] =
                    ($turmas[$aula->turma->nome] ?? 0) + $carga;
            }
        }

        $this->mapaSaturacao = [
            'professores' => collect($professores)->map(fn($c, $n) => [
                'nome' => $n,
                'percentual' => round(($c / $capacidade) * 100, 1)
            ])->values()->toArray(),

            'turmas' => collect($turmas)->map(fn($c, $n) => [
                'nome' => $n,
                'percentual' => round(($c / $capacidade) * 100, 1)
            ])->values()->toArray(),
        ];
    }

    private function calcularRiscoFallback(array $dados): int {
        $saturacao = isset($dados['global_saturation']) ? (float) str_replace('%', '', $dados['global_saturation']) : 0;

        $turmas = count($dados['turmas'] ?? []);
        $professores = count($dados['professores'] ?? []);

        $deficit = 0;
        foreach ($dados['aulas_duplas'] ?? [] as $b) {
            $deficit += $b['deficit'] ?? 0;
        }

        $risco = $saturacao + ($turmas * 10) + ($professores * 8) + ($deficit * 5);

        return (int) min(100, round($risco));
    }

    public function render() {
        return view('livewire.algoritmo.index');
    }
}
