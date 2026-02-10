<?php

namespace App\Livewire\Layout;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Navbar extends Component
{
    #[On('logout')]
    public function logout(): void
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        // Remover navigate: true
        $this->redirect(route('login'));
    }

    public function render()
    {
        return view('livewire.layout.navbar');
    }
}
