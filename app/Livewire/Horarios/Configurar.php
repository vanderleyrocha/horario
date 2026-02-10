<?php
// app/Livewire/Horarios/Configurar.php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\ConfiguracaoHorario;
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Traits\ComDadosComuns;
use Carbon\CarbonImmutable;

#[Layout('components.app-layout', ['title' => 'Configurar Horário'])]
class Configurar extends Component {
    use ComDadosComuns;

    public Horario $horario;

    // Etapas do wizard
    public int $etapaAtual = 1;
    public int $totalEtapas = 5;

    // Dados da Configuração Básica (usando snake_case para compatibilidade com suas views)
    public string $nome_escola = '';
    public int $aulas_por_dia = 5;
    public int $dias_semana = 5;
    public string $horario_inicio = '07:00';
    public string $horario_fim = '12:00';
    public int $duracao_aula_minutos = 50;
    public int $duracao_intervalo_minutos = 15;
    public array $horarios_intervalos = [2, 4];
    public array $duracoes_intervalos = [];
    public bool $permitir_janelas = false;
    public bool $agrupar_disciplinas = true;
    public int $max_aulas_seguidas = 3;

    // Novas propriedades para Configuração do Algoritmo Genético
    public int $elitism_count = 10;
    public float $target_fitness = 95.0;
    public int $max_generations_without_improvement = 50;

    // Propriedades para evitar PropertyNotFoundException (mantidas como no seu código)
    public $editandoId = null;
    public $professor_id = '';
    public $disciplina_id = '';
    public $turma_id = '';
    public $aulas_semana = 2;
    public $tipo = 'simples';
    public $aulas_consecutivas = false;
    public $max_aulas_dia = 2;
    public $min_intervalo_dias = 0;
    public $preferencia_periodo = 'qualquer';
    public $dias_preferidos = [];
    public $tempos_preferidos = [];
    public $observacoes = '';

    public function mount(Horario $horario) {
        $this->horario = $horario;

        $configuracaoHorario = ConfiguracaoHorario::where('horario_id', $horario->id)->first();

        if ($configuracaoHorario) {
            $this->nome_escola = $configuracaoHorario->nome_escola;
            $this->aulas_por_dia = $configuracaoHorario->aulas_por_dia;
            $this->dias_semana = $configuracaoHorario->dias_semana;
            $this->horario_inicio = CarbonImmutable::parse($configuracaoHorario->horario_inicio)->format('H:i');
            $this->horario_fim = CarbonImmutable::parse($configuracaoHorario->horario_fim)->format('H:i');
            $this->duracao_aula_minutos = $configuracaoHorario->duracao_aula_minutos;
            $this->duracao_intervalo_minutos = $configuracaoHorario->duracao_intervalo_minutos;
            $this->horarios_intervalos = $configuracaoHorario->horarios_intervalos ?? [2, 4];
            $this->duracoes_intervalos = $configuracaoHorario->duracoes_intervalos ?? [];
            $this->permitir_janelas = $configuracaoHorario->permitir_janelas;
            $this->agrupar_disciplinas = $configuracaoHorario->agrupar_disciplinas;
            $this->max_aulas_seguidas = $configuracaoHorario->max_aulas_seguidas;
            $this->elitism_count = $configuracaoHorario->elitism_count ?? 10;
            $this->target_fitness = $configuracaoHorario->target_fitness ?? 95.0;
            $this->max_generations_without_improvement = $configuracaoHorario->max_generations_without_improvement ?? 50;
        }
    }

    public function render() {
        return view('livewire.horarios.configurar');
    }

    public function proximaEtapa() {
        if ($this->etapaAtual === 1) {
            $this->salvarConfiguracaoBasica();
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        } elseif ($this->etapaAtual === 4) { // Etapa de Configuração do AG
            $this->salvarConfiguracaoAlgoritmoGenetico();
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        if ($this->etapaAtual < $this->totalEtapas) {
            $this->etapaAtual++;
        }
    }

    public function etapaAnterior() {
        if ($this->etapaAtual > 1) {
            $this->etapaAtual--;
        }
    }

    public function irParaEtapa(int $etapa) {
        if ($etapa > 1) {
            if (!$this->horario->configuracaoHorario()->exists()) {
                session()->flash('error', 'Por favor, salve a Configuração Básica (Etapa 1) antes de prosseguir.');
                $this->etapaAtual = 1;
                return;
            }
        }
        $this->etapaAtual = $etapa;
    }

    public function salvarConfiguracaoBasica() {
        // ✅ REMOVIDO: As variáveis $horarioInicioParaValidacao e $horarioFimParaValidacao
        // A validação e o salvamento agora operam diretamente nas propriedades do componente.
        $this->validate([
            'nome_escola' => 'required|string|max:255',
            'aulas_por_dia' => 'required|integer|min:1|max:10',
            'dias_semana' => 'required|integer|min:1|max:7',
            'horario_inicio' => ['required', 'date_format:H:i'],
            'horario_fim' => ['required', 'date_format:H:i', 'after:horario_inicio'],
            'duracao_aula_minutos' => 'required|integer|min:10|max:120',
            'duracao_intervalo_minutos' => 'required|integer|min:0|max:60',
            'max_aulas_seguidas' => 'required|integer|min:1|max:5',
        ], [
            'horario_inicio.date_format' => 'O formato do Horário de Início deve ser HH:MM.',
            'horario_fim.date_format' => 'O formato do Horário de Fim deve ser HH:MM.',
            'horario_fim.after' => 'O Horário de Fim deve ser posterior ao Horário de Início.',
        ]);

        $configuracaoHorario = ConfiguracaoHorario::firstOrNew(['horario_id' => $this->horario->id]);
        $configuracaoHorario->fill([
            'nome_escola' => $this->nome_escola,
            'aulas_por_dia' => $this->aulas_por_dia,
            'dias_semana' => $this->dias_semana,
            'horario_inicio' => $this->horario_inicio, // ✅ USANDO DIRETAMENTE A PROPRIEDADE
            'horario_fim' => $this->horario_fim,       // ✅ USANDO DIRETAMENTE A PROPRIEDADE
            'duracao_aula_minutos' => $this->duracao_aula_minutos,
            'duracao_intervalo_minutos' => $this->duracao_intervalo_minutos,
            'horarios_intervalos' => $this->horarios_intervalos,
            'duracoes_intervalos' => $this->duracoes_intervalos,
            'permitir_janelas' => $this->permitir_janelas,
            'agrupar_disciplinas' => $this->agrupar_disciplinas,
            'max_aulas_seguidas' => $this->max_aulas_seguidas,
        ])->save();

        $this->horario->load('configuracaoHorario');

        session()->flash('success', 'Configuração básica salva com sucesso!');
    }

    public function salvarConfiguracaoAlgoritmoGenetico() {
        $this->validate([
            'elitism_count' => 'required|integer|min:0',
            'target_fitness' => 'required|numeric|min:0|max:100',
            'max_generations_without_improvement' => 'required|integer|min:0',
        ]);

        $configuracaoHorario = ConfiguracaoHorario::firstOrNew(['horario_id' => $this->horario->id]);
        $configuracaoHorario->fill([
            'elitism_count' => $this->elitism_count,
            'target_fitness' => $this->target_fitness,
            'max_generations_without_improvement' => $this->max_generations_without_improvement,
        ])->save();

        $this->horario->load('configuracaoHorario');

        session()->flash('success', 'Configurações do Algoritmo Genético salvas com sucesso!');
    }

    public function addIntervalo() {
        $this->horarios_intervalos[] = count($this->horarios_intervalos) + 1;
        $this->duracoes_intervalos[] = $this->duracao_intervalo_minutos;
    }

    public function removeIntervalo(int $index) {
        unset($this->horarios_intervalos[$index]);
        unset($this->duracoes_intervalos[$index]);
        $this->horarios_intervalos = array_values($this->horarios_intervalos);
        $this->duracoes_intervalos = array_values($this->duracoes_intervalos);
    }

    public function fecharModal() {
        // Este método pode ser vazio ou apenas logar um aviso,
        // pois o modal de aula não deveria ser aberto por este componente.
    }
}
