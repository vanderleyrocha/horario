<div class="space-y-6">

    @if (empty($diagnostico))
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
            Nenhum diagnostico disponivel para este horario.
        </div>
    @else

    {{-- Barra de Saturacao --}}
    <div>
        <h3 class="text-lg font-semibold mb-2">Saturacao Global</h3>

        @php
            $globalSaturation = (string) ($diagnostico['global_saturation'] ?? '0%');
            $percent = (float) str_replace('%', '', $globalSaturation);
        @endphp

        <div class="w-full bg-gray-200 rounded-full h-6">
            <div class="h-6 rounded-full text-white text-sm flex items-center justify-center
                {{ $percent > 100 ? 'bg-red-600' : ($percent > 90 ? 'bg-yellow-500' : 'bg-green-600') }}"
                style="width: {{ min($percent, 100) }}%">
                {{ $globalSaturation }}
            </div>
        </div>
    </div>

    {{-- Turmas criticas --}}
    @if (!empty($diagnostico['turmas']))
        <div>
            <h3 class="text-lg font-semibold mb-2 text-red-600">Turmas Criticas</h3>

            <table class="w-full text-sm border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 border">Turma</th>
                        <th class="p-2 border">Excesso (aulas)</th>
                        <th class="p-2 border">Excesso (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($diagnostico['turmas'] as $turma)
                        <tr>
                            <td class="p-2 border">{{ $turma['turma_id'] }}</td>
                            <td class="p-2 border text-red-600">{{ $turma['excesso_aulas'] }}</td>
                            <td class="p-2 border text-red-600">{{ $turma['excesso_percentual'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Professores criticos --}}
    @if (!empty($diagnostico['professores']))
        <div>
            <h3 class="text-lg font-semibold mb-2 text-red-600">Professores Criticos</h3>

            <table class="w-full text-sm border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 border">Professor</th>
                        <th class="p-2 border">Excesso (aulas)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($diagnostico['professores'] as $prof)
                        <tr>
                            <td class="p-2 border">{{ $prof['professor_id'] }}</td>
                            <td class="p-2 border text-red-600">{{ $prof['excesso_aulas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Gargalo estrutural --}}
    @if (!empty($diagnostico['aulas_duplas']))
        <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
            <h3 class="font-semibold text-yellow-700">Gargalo Estrutural - Blocos Continuos</h3>

            @foreach ($diagnostico['aulas_duplas'] as $g)
                <p>
                    Necessarios: {{ $g['necessarios'] }} |
                    Disponiveis: {{ $g['disponiveis'] }} |
                    Deficit: <strong>{{ $g['deficit'] }}</strong>
                </p>
            @endforeach
        </div>
    @endif

    {{-- Sugestoes --}}
    @if (!empty($diagnostico['sugestoes']))
        <div class="bg-green-50 border-l-4 border-green-600 p-4">
            <h3 class="font-semibold text-green-700">Sugestoes</h3>
            <ul class="list-disc ml-6 text-sm">
                @foreach ($diagnostico['sugestoes'] as $s)
                    <li>{{ $s }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @endif

</div>