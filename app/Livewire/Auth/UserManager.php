<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder; // Importante para tipagem se necessário
use Illuminate\Support\Facades\Auth;

#[Layout('components.app-layout', ['title' => 'Gerenciar Usuários'])]
class UserManager extends Component
{
    use WithPagination;

    public bool $isModalOpen = false;
    public ?User $editingUser = null;

    // --- NOVA PROPRIEDADE DE BUSCA ---
    public string $search = '';

    #[Validate]
    public string $name = '';

    #[Validate]
    public string $email = '';

    public string $password = '';

    // --- RESETAR PAGINAÇÃO AO BUSCAR ---
    // Isso impede que você busque algo e fique preso na página 10 vazia
    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function rules()
    {
        $rules = [
            'name'  => 'required|min:3',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->editingUser?->id),
            ],
        ];

        if ($this->editingUser) {
            $rules['password'] = 'nullable|min:6';
        } else {
            $rules['password'] = 'required|min:6';
        }

        return $rules;
    }

    public function render()
    {
        // --- QUERY COM FILTRO ---
        $users = User::query()
            ->when($this->search, function (Builder $query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc') // Opcional: mostrar os mais novos primeiro
            ->paginate(10);

        return view('livewire.auth.user-manager', [
            'users' => $users
        ]);
    }

    // ... (Mantenha o resto dos métodos create, edit, save, delete, closeModal exatamente iguais) ...
    
    public function create(): void
    {
        $this->reset(['editingUser', 'name', 'email', 'password']);
        $this->resetValidation();
        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->editingUser = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = ''; 

        $this->resetValidation();
        $this->isModalOpen = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingUser) {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
            ];
            
            if (!empty($this->password)) {
                $data['password'] = Hash::make($this->password);
            }

            $this->editingUser->update($data);
            $msg = 'Usuário atualizado com sucesso!';
        } else {
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ]);
            $msg = 'Usuário criado com sucesso!';
        }

        $this->closeModal();
        session()->flash('message', $msg);
    }

    public function delete(int $id): void
    {
        if ($id === Auth::user()->id) {
            session()->flash('error', 'Você não pode excluir sua própria conta.');
            return;
        }

        User::findOrFail($id)->delete();
        session()->flash('message', 'Usuário excluído com sucesso.');
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->editingUser = null;
        $this->reset(['name', 'email', 'password']);
    }
}