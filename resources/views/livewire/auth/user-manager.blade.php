<div class="p-6">
    {{-- Mensagens de Feedback --}}
    @if (session()->has('message'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 shadow-sm relative" role="alert">
            <p class="font-bold">Sucesso</p>
            <p>{{ session('message') }}</p>
        </div>
    @endif
    
    @if (session()->has('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm relative" role="alert">
            <p class="font-bold">Erro</p>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- CABEÇALHO COM BUSCA E BOTÃO --}}
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        
        {{-- Título --}}
        <div class="w-full md:w-auto">
            <h2 class="text-2xl font-bold text-gray-800">Gerenciar Usuários</h2>
            <p class="text-sm text-gray-500">Administre o acesso ao sistema</p>
        </div>
        
        {{-- BARRA DE BUSCA --}}
        <div class="w-full md:w-96 relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                </svg>
            </div>
            <input 
                wire:model.live.debounce.250ms="search" 
                type="text" 
                placeholder="Buscar por nome ou email..." 
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 sm:text-sm transition duration-150 ease-in-out"
            >
        </div>

        {{-- Botão Novo Usuário --}}
        <div class="w-full md:w-auto flex justify-end">
            <button wire:click="create" 
                    class="group flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-5 rounded-lg shadow-md transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5 group-hover:rotate-90 transition-transform duration-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>Novo Usuário</span>
            </button>
        </div>
    </div>

    {{-- Tabela (O restante permanece igual, mas agora reage à $users filtrado) --}}
    <div class="overflow-x-auto bg-white shadow-lg rounded-xl border border-gray-100 mb-6">
        <table class="min-w-full leading-normal">
            <thead>
                <tr class="bg-gray-50 text-gray-600 uppercase text-xs leading-normal">
                    <th class="py-3 px-6 text-left font-bold border-b border-gray-200">ID</th>
                    <th class="py-3 px-6 text-left font-bold border-b border-gray-200">Nome</th>
                    <th class="py-3 px-6 text-left font-bold border-b border-gray-200">Email</th>
                    <th class="py-3 px-6 text-center font-bold border-b border-gray-200">Ações</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                @forelse($users as $user)
                    <tr wire:key="{{ $user->id }}" class="border-b border-gray-200 hover:bg-gray-50 transition-colors duration-150">
                        <td class="py-3 px-6 text-left whitespace-nowrap">
                            <span class="font-medium">{{ $user->id }}</span>
                        </td>
                        <td class="py-3 px-6 text-left">
                            <div class="flex items-center">
                                <span class="font-medium">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-6 text-left">
                            <span>{{ $user->email }}</span>
                        </td>
                        <td class="py-3 px-6 text-center">
                            <div class="flex item-center justify-center gap-2">
                                <button wire:click="edit({{ $user->id }})" 
                                        class="transform hover:text-blue-600 hover:scale-110 transition duration-150" title="Editar">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </button>
                                <button wire:click="delete({{ $user->id }})"
                                        wire:confirm="Tem certeza que deseja excluir o usuário {{ $user->name }}?"
                                        class="transform hover:text-red-600 hover:scale-110 transition duration-150" title="Excluir">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456-1.293A11.95 11.95 0 0 0 20.25 5.378m-14.456-1.293A11.95 11.95 0 0 0 3.75 5.378m14.741 0H4.755" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-gray-500 bg-gray-50">
                            Nenhum usuário encontrado para "{{ $search }}".
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    {{-- MODAL (O mesmo de antes) --}}
    @if($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-60 transition-opacity backdrop-blur-sm" wire:click="closeModal"></div>
            <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-100">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800">{{ $editingUser ? 'Editar Usuário' : 'Novo Usuário' }}</h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form wire:submit="save">
                    <div class="p-6 space-y-5">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2" for="name">Nome Completo</label>
                            <input wire:model="name" type="text" id="name" placeholder="Ex: João da Silva" class="w-full px-4 py-2 border rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all @error('name') border-red-500 @enderror">
                            @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2" for="email">Endereço de Email</label>
                            <input wire:model="email" type="email" id="email" placeholder="nome@exemplo.com" class="w-full px-4 py-2 border rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all @error('email') border-red-500 @enderror">
                            @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2" for="password">Senha @if($editingUser) <span class="text-xs font-normal text-gray-400 ml-1">(Opcional na edição)</span> @endif</label>
                            <input wire:model="password" type="password" id="password" placeholder="********" class="w-full px-4 py-2 border rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all @error('password') border-red-500 @enderror">
                            @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 flex flex-row-reverse gap-3">
                        <button type="submit" class="inline-flex justify-center rounded-lg shadow-sm px-5 py-2 bg-emerald-600 text-base font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:text-sm transition-colors">Salvar Dados</button>
                        <button type="button" wire:click="closeModal" class="inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-5 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-200 sm:text-sm transition-colors">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>