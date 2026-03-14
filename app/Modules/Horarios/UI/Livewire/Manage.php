<?php

namespace App\Modules\Horarios\UI\Livewire;

use App\Models\Horario;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.app-layout', ['title' => 'Gerenciar Horário'])]
class Manage extends Component
{
    private const ALLOWED_TABS = [
        'overview',
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
        return view('livewire.horarios.manage');
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

    public function tabs(): array
    {
        return [
            'overview' => 'Visão Geral',
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
}
