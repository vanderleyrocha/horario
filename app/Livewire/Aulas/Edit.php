<?php

namespace App\Livewire\Aulas;

use App\Models\Aula;
use App\Models\Professor;
use App\Models\Disciplina;
use App\Models\Turma;
use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Traits\ComDadosComuns; // Reutilizando o trait para dados de dropdowns

#[Layout('components.app-layout', ['title' => 'Editar Aula'])]
class Edit extends Component
{
    use ComDadosComuns; // Para carregar professores, disciplinas, turmas

    public Aula $aula; // A instância da aula que será editada

    // Propriedades para o formulário
    public $professor_id = '';
    public $disciplina_id = '';
    public $turma_id = '';
    public $aulas_semana = 2;
    public $tipo = 'simples'; // 'simples', 'dupla', 'tripla'
    public $aulas_consecutivas = false;
    public $max_aulas_dia = 2;
    public $min_intervalo_dias = 0;
    public $preferencia_periodo = 'qualquer'; // 'qualquer', 'manha', 'tarde', 'noite'
    public $dias_preferidos = []; // Array de dias da semana (1=Seg, 7=Dom)
    public $tempos_preferidos = []; // Array de tempos de aula (1, 2, 3...)
    public $observacoes = '';

    // Propriedades auxiliares para a view (checkboxes de dias/tempos)
    public array $diasDaSemanaOpcoes = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public array $temposDeAulaOpcoes = [
        1 => '1º Tempo', 2 => '2º Tempo', 3 => '3º Tempo', 4 => '4º Tempo', 5 => '5º Tempo',
        6 => '6º Tempo', 7 => '7º Tempo', 8 => '8º Tempo', 9 => '9º Tempo', 10 => '10º Tempo',
    ];

    protected $rules = [
        'professor_id' => 'required|exists:professores,id',
        'disciplina_id' => 'required|exists:disciplinas,id',
        'turma_id' => 'required|exists:turmas,id',
        'aulas_semana' => 'required|integer|min:1|max:10',
        'tipo' => 'required|in:simples,dupla,tripla',
        'aulas_consecutivas' => 'boolean',
        'max_aulas_dia' => 'required|integer|min:1|max:5',
        'min_intervalo_dias' => 'nullable|integer|min:0|max:6',
        'preferencia_periodo' => 'required|in:qualquer,manha,tarde,noite',
        'dias_preferidos' => 'nullable|array',
        'dias_preferidos.*' => 'integer|min:1|max:7',
        'tempos_preferidos' => 'nullable|array',
        'tempos_preferidos.*' => 'integer|min:1|max:10',
        'observacoes' => 'nullable|string|max:500',
    ];

    /**
     * Monta o componente, carregando os dados da aula para edição.
     */
    public function mount(Aula $aula)
    {
        $this->aula = $aula;

        $this->professor_id = $aula->professor_id;
        $this->disciplina_id = $aula->disciplina_id;
        $this->turma_id = $aula->turma_id;
        $this->aulas_semana = $aula->aulas_semana;
        $this->tipo = $aula->tipo;
        $this->aulas_consecutivas = $aula->aulas_consecutivas;
        $this->max_aulas_dia = $aula->max_aulas_dia;
        $this->min_intervalo_dias = $aula->min_intervalo_dias ?? 0;
        $this->preferencia_periodo = $aula->preferencia_periodo ?? 'qualquer';

        // Decodificar arrays JSON se necessário
        $this->dias_preferidos = is_array($aula->dias_preferidos)
            ? $aula->dias_preferidos
            : (json_decode($aula->dias_preferidos, true) ?? []);

        $this->tempos_preferidos = is_array($aula->tempos_preferidos)
            ? $aula->tempos_preferidos
            : (json_decode($aula->tempos_preferidos, true) ?? []);

        $this->observacoes = $aula->observacoes ?? '';
    }

    /**
     * Salva as alterações na aula.
     */
    public function salvarAula()
    {
        $this->validate();

        $this->aula->update([
            'professor_id' => $this->professor_id,
            'disciplina_id' => $this->disciplina_id,
            'turma_id' => $this->turma_id,
            'aulas_semana' => $this->aulas_semana,
            'tipo' => $this->tipo,
            'aulas_consecutivas' => $this->aulas_consecutivas,
            'max_aulas_dia' => $this->max_aulas_dia,
            'min_intervalo_dias' => $this->min_intervalo_dias,
            'preferencia_periodo' => $this->preferencia_periodo,
            'dias_preferidos' => !empty($this->dias_preferidos) ? $this->dias_preferidos : null,
            'tempos_preferidos' => !empty($this->tempos_preferidos) ? $this->tempos_preferidos : null,
            'observacoes' => $this->observacoes,
        ]);

        session()->flash('success', 'Aula atualizada com sucesso!');

        // Redirecionar de volta para a página de gerenciamento de aulas
        return redirect()->route('turmas.aulas', $this->aula->turma);
    }

    public function render()
    {
        return view('livewire.aulas.edit', [
            'professores' => $this->professores, // Do trait ComDadosComuns
            'disciplinas' => $this->disciplinas, // Do trait ComDadosComuns
            'turmas' => $this->turmas,           // Do trait ComDadosComuns
        ]);
    }
}
