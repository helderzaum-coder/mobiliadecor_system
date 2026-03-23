<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    @php
        $data = $this->getCalendarioData();
    @endphp

    <div class="mt-4 space-y-4">
        {{-- Legenda --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap gap-4 text-sm">
                <span class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-red-600"></span> Não comprar (vencimento cai em dia crítico)
                </span>
                <span class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-green-600"></span> Pode comprar
                </span>
                <span class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-gray-600"></span> Fim de semana
                </span>
            </div>
            <div class="mt-3 text-xs text-gray-400">
                Dias críticos de vencimento:
                @foreach($data['dias_bloqueados_info'] as $info)
                    <span class="inline-block bg-red-900/30 text-red-300 px-2 py-0.5 rounded mr-1">{{ $info }}</span>
                @endforeach
            </div>
        </div>

        {{-- Tabela calendário --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-600 text-gray-400">
                        <th class="p-3 text-left">Dia</th>
                        <th class="p-3 text-center">Dia Sem.</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-center">Venc. 14d</th>
                        <th class="p-3 text-center">Venc. 28d</th>
                        <th class="p-3 text-center">Venc. 42d</th>
                        <th class="p-3 text-left">Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['dias'] as $dia)
                        <tr class="border-b border-gray-700/50
                            {{ $dia['bloqueado'] ? 'bg-red-900/20' : ($dia['fim_semana'] ? 'bg-gray-800/50' : 'hover:bg-gray-700/30') }}">
                            <td class="p-3 font-bold {{ $dia['bloqueado'] ? 'text-red-400' : 'text-white' }}">
                                {{ str_pad($dia['dia'], 2, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="p-3 text-center text-gray-400 capitalize">{{ $dia['dia_semana'] }}</td>
                            <td class="p-3 text-center">
                                @if($dia['fim_semana'])
                                    <span class="text-gray-500">—</span>
                                @elseif($dia['bloqueado'])
                                    <span class="text-red-400 font-bold">🚫 Evitar</span>
                                @else
                                    <span class="text-green-400">✅ OK</span>
                                @endif
                            </td>
                            @foreach($dia['vencimentos'] as $venc)
                                <td class="p-3 text-center {{ $venc['bloqueado'] ? 'text-red-400 font-bold' : 'text-gray-300' }}">
                                    {{ $venc['data'] }}
                                    @if($venc['bloqueado'])
                                        ⚠️
                                    @endif
                                </td>
                            @endforeach
                            <td class="p-3 text-xs text-red-300">
                                {{ implode(', ', $dia['motivos']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
