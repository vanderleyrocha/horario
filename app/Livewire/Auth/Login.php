<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Login extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas não correspondem aos nossos registros.',
            ]);
        }

        session()->regenerate();

        // Remover navigate: true - não é necessário
        $this->redirect(route('dashboard'));
    }

    #[Layout('components.guest-layout')]
    public function render()
    {
        return view('livewire.auth.login');
    }
}
