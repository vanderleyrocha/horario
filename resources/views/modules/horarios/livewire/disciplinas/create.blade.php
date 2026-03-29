<div>
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <a href="{{ route('disciplinas.index') }}" wire:navigate 
               class="text-blue-600 hover:text-blue-700 flex items-center mb-4 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Voltar para listagem
            </a>
            <h2 class="text-2xl font-bold text-gray-900">Nova Disciplina</h2>
            <p class="text-gray-600 mt-1">Cadastre uma nova disciplina no sistema</p>
        </div>

        <!-- Form -->
        <form wire:submit="save" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">

            <!-- Informações Básicas -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Informações Básicas
                </h3>

                <!-- Nome e Código -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">
                            Nome da Disciplina <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="nome"
                            wire:model="nome"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('nome') border-red-500 @enderror"
                            placeholder="Ex: Matemática"
                        >
                        @error('nome')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="codigo" class="block text-sm font-medium text-gray-700 mb-2">
                            Código <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="codigo"
                            wire:model="codigo"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('codigo') border-red-500 @enderror"
                            placeholder="Ex: MAT"
                            maxlength="20"
                        >
                        @error('codigo')
                            <p class="mt-1 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <!-- Carga Horária -->
                <div class="mb-6">
                    <label for="carga_horaria_semanal" class="block text-sm font-medium text-gray-700 mb-2">
                        Carga Horária Semanal <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input 
                            type="number" 
                            id="carga_horaria_semanal"
                            wire:model="carga_horaria_semanal"
                            min="1"
                            max="40"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('carga_horaria_semanal') border-red-500 @enderror"
                        >
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">horas/semana</span>
                        </div>
                    </div>
                    @error('carga_horaria_semanal')
                        <p class="mt-1 text-sm text-red-600 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">Número de horas semanais (entre 1 e 40)</p>
                    @enderror
                </div>

                <!-- Descrição -->
                <div class="mb-6">
                    <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">
                        Descrição
                    </label>
                    <textarea 
                        id="descricao"
                        wire:model="descricao"
                        rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('descricao') border-red-500 @enderror"
                        placeholder="Descrição opcional da disciplina..."
                        maxlength="500"
                    ></textarea>
                    @error('descricao')
                        <p class="mt-1 text-sm text-red-600 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">{{ strlen($descricao) }}/500 caracteres</p>
                    @enderror
                </div>
            </div>

            <!-- Personalização -->
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                    Personalização
                </h3>

                <!-- Cor -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">
                        Cor da Disciplina <span class="text-red-500">*</span>
                    </label>

                    <!-- Cores Pré-definidas -->
                    <div class="grid grid-cols-5 md:grid-cols-10 gap-3 mb-4">
                        @foreach($coresPreDefinidas as $corPredefinida)
                            <button 
                                type="button"
                                wire:click="$set('cor', '{{ $corPredefinida }}')"
                                class="w-12 h-12 rounded-lg border-2 transition-all {{ $cor === $corPredefinida ? 'border-gray-900 scale-110' : 'border-gray-300 hover:scale-105' }}"
                                style="background-color: {{ $corPredefinida }};"
                            ></button>
                        @endforeach
                    </div>

                    <!-- Cor Personalizada -->
                    <div class="flex items-center space-x-4">
                        <div class="flex-1">
                            <label for="cor" class="block text-xs text-gray-600 mb-1">Cor Personalizada</label>
                            <input 
                                type="text" 
                                id="cor"
                                wire:model="cor"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all @error('cor') border-red-500 @enderror"
                                placeholder="#3B82F6"
                                maxlength="7"
                            >
                        </div>
                        <div class="flex-shrink-0">
                            <label class="block text-xs text-gray-600 mb-1">Preview</label>
                            <div class="w-12 h-12 rounded-lg border-2 border-gray-300" 
                                 style="background-color: {{ $cor }};"></div>
                        </div>
                    </div>
                    @error('cor')
                        <p class="mt-1 text-sm text-red-600 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $message }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-gray-500">Use formato hexadecimal (ex: #3B82F6)</p>
                    @enderror
                </div>

                <!-- Preview Card -->
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                    <p class="text-xs font-medium text-gray-700 mb-2">Preview da Disciplina</p>
                    <div class="flex items-center space-x-3 p-3 bg-white rounded-lg border border-gray-200">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                             style="background-color: {{ $cor }}20;">
                            <svg class="w-6 h-6" style="color: {{ $cor }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $nome ?: 'Nome da Disciplina' }}</p>
                            <p class="text-xs text-gray-500">{{ $codigo ?: 'COD' }} • {{ $carga_horaria_semanal }}h/semana</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <button 
                    type="button"
                    wire:click="cancel"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Cancelar
                </button>
                <button 
                    type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Salvar Disciplina
                </button>
            </div>
        </form>
    </div>
</div>
