{{-- resources/views/livewire/layout/sidebar.blade.php --}}

<aside class="bg-gray-900 text-white transition-all duration-300 {{ $isOpen ? 'w-64' : 'w-20' }}">
    <div class="flex flex-col h-full">
        <!-- Logo -->
        <div class="flex items-center justify-between p-4 border-b border-gray-800">
            @if($isOpen)
                <div class="flex items-center space-x-3">
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-xl font-bold">Horários</span>
                </div>
            @endif
            <button 
                wire:click="toggle"
                class="p-2 rounded-lg hover:bg-gray-800 transition-colors"
                type="button"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <!-- Menu Items -->
        <nav class="flex-1 overflow-y-auto py-4">
            @foreach($menuItems as $item)
                <a 
                    href="{{ route($item['route']) }}"
                    wire:navigate
                    class="flex items-center px-4 py-3 transition-colors {{ $item['active'] ? 'bg-blue-600 text-white' : 'hover:bg-gray-800' }}"
                >
                    <svg class="w-6 h-6 flex-shrink-0 {{ $isOpen ? '' : 'mx-auto' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                    </svg>
                    @if($isOpen)
                        <span class="ml-3">{{ $item['label'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
</aside>
