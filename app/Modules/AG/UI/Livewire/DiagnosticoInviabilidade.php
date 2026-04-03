<?php

namespace App\Modules\AG\UI\Livewire;

use Livewire\Component;

class DiagnosticoInviabilidade extends Component
{
    public array $diagnostico = [];

    public function mount(array $diagnostico)
    {
        $this->diagnostico = $diagnostico;
    }

    public function render()
    {
        return view('modules.ag.livewire.ag.diagnostico-inviabilidade');
    }
}
