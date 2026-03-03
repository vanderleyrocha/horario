<?php

namespace App\Modules\Horarios\UI\Livewire;

use App\Models\Horario;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.app-layout', ['title' => 'Gerenciar Horário'])]
class Manage extends Component {
    public Horario $horario;

    public string $tab = 'overview';

    protected $queryString = ['tab'];

    public function mount(Horario $horario) {
        $this->horario = $horario;
    }

    public function setTab(string $tab) {
        $this->tab = $tab;
    }

    public function render() {
        dd($this->horario);
        return view('livewire.horarios.manage');
    }
}
