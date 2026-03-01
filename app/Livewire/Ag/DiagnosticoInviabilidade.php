<?php

namespace App\Livewire\Ag;

use Livewire\Component;

class DiagnosticoInviabilidade extends Component {
    public array $diagnostico = [];

    public function mount(array $diagnostico) {
        $this->diagnostico = $diagnostico;
    }

    public function render() {
        return view('livewire.ag.diagnostico-inviabilidade');
    }
}
