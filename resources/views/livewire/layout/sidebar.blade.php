<aside class="bg-gray-900 text-white transition-all duration-300 {{ $isOpen ? 'w-72' : 'w-20' }}">
    <div class="flex h-full flex-col">
        <div class="flex items-center justify-between border-b border-gray-800 p-4">
            @if ($isOpen)
                <div class="flex items-center space-x-3">
                    <svg class="h-8 w-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <div>
                        <span class="block text-xl font-bold">Horarios</span>
                        <span class="text-xs text-gray-400">Modulo de configuracao e execucao</span>
                    </div>
                </div>
            @endif

            <button
                wire:click="toggle"
                class="rounded-lg p-2 transition-colors hover:bg-gray-800"
                type="button"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            @foreach ($menuGroups as $group)
                <div class="{{ $loop->first ? '' : 'mt-6' }}">
                    @if ($isOpen)
                        <div class="px-4 pb-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-400">
                                {{ $group['heading'] }}
                            </p>

                            @if (!empty($group['description']))
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $group['description'] }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <div class="space-y-1 px-2">
                        @foreach ($group['items'] as $item)
                            <a
                                href="{{ route($item['route']) }}"
                                wire:navigate
                                class="flex items-center rounded-xl px-3 py-3 transition-colors {{ $item['active'] ? 'bg-blue-600 text-white shadow-sm shadow-blue-900/30' : 'text-gray-200 hover:bg-gray-800 hover:text-white' }}"
                            >
                                <svg class="h-6 w-6 flex-shrink-0 {{ $isOpen ? '' : 'mx-auto' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}" />
                                </svg>

                                @if ($isOpen)
                                    <span class="ml-3 text-sm font-medium">{{ $item['label'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </div>
</aside>
