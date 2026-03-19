<x-filament-panels::page>
    <div class="max-w-5xl space-y-6">

        {{-- Formulário --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <form wire:submit="simular">
                {{ $this->form }}
                <div class="mt-5 flex items-center gap-3">
                    <x-filament::button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simular">Simular Frete</span>
                        <span wire:loading wire:target="simular">Calculando...</span>
                    </x-filament::button>
                    @if($this->simulado)
                        <span class="text-sm text-gray-400">
                            {{ count($this->cotacoes) }} transportadora(s) encontrada(s)
                        </span>
                        <span class="text-sm px-2 py-1 rounded-lg {{ $this->pesoTipo === 'cubado' ? 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                            Peso usado: {{ number_format($this->pesoUsado, 3, ',', '.') }} kg
                            ({{ $this->pesoTipo === 'cubado' ? 'cubado — ' . number_format($this->pesoCubado, 3, ',', '.') . ' kg' : 'real' }})
                        </span>
                    @endif
                </div>
            </form>
        </div>

        {{-- Resultados --}}
        @if($this->simulado)
            @if(empty($this->cotacoes))
                <div class="bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-700 rounded-xl p-5 text-warning-700 dark:text-warning-400 text-sm">
                    Nenhuma transportadora encontrada para este destino/peso. Verifique se há tabelas importadas para a UF informada.
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-200">#</th>
                                <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-200">Transportadora</th>
                                <th class="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-200">Região / UF</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">Frete</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">Despacho</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">Pedágio</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">Ad Valorem</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">GRIS</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">TAS</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">Taxas Esp.</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600 dark:text-gray-200">ICMS</th>
                                <th class="text-right px-4 py-3 font-bold text-gray-800 dark:text-white">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($this->cotacoes as $i => $c)
                                @php
                                    $isConsulta = !empty($c['somente_consulta']);
                                    // Calcular posição real (só cotações com cálculo)
                                    $posReal = 0;
                                    if (!$isConsulta) {
                                        $posReal = collect(array_slice($this->cotacoes, 0, $i + 1))->filter(fn($x) => empty($x['somente_consulta']))->count();
                                    }
                                @endphp
                                <tr class="{{ !$isConsulta && $posReal === 1 ? 'bg-success-50 dark:bg-success-900/10' : ($isConsulta ? 'bg-blue-50 dark:bg-blue-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30') }} transition">
                                    <td class="px-4 py-3 text-gray-400">
                                        @if($isConsulta)
                                            <span class="text-blue-500 dark:text-blue-400 text-xs font-medium">—</span>
                                        @elseif($posReal === 1)
                                            <span class="inline-flex items-center gap-1 text-success-600 dark:text-success-400 font-medium">
                                                <x-heroicon-s-trophy class="w-4 h-4" /> 1º
                                            </span>
                                        @else
                                            {{ $posReal }}º
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">
                                        {{ $c['nome'] }}
                                        @if($isConsulta)
                                            <span class="text-xs text-blue-500 dark:text-blue-400">(consultar)</span>
                                        @endif
                                    </td>
                                    @if($isConsulta)
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-300">{{ $c['uf_faixa'] }}</td>
                                        <td colspan="7" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400 text-xs">
                                            Atende a região — solicitar cotação direta
                                            @if(!empty($c['taxas_especiais']))
                                                <span class="ml-2 text-xs bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded">
                                                    TDA: R$ {{ number_format($c['taxas_especiais_total'], 2, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="ml-2 text-xs bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 px-2 py-0.5 rounded">
                                                    Sem TDA
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-400">-</td>
                                        <td class="px-4 py-3 text-right font-bold text-blue-600 dark:text-blue-400">
                                            Consultar
                                        </td>
                                    @else
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-300">
                                        {{ $c['regiao'] }} / {{ $c['uf_faixa'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        R$ {{ number_format($c['frete_peso'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        R$ {{ number_format($c['despacho'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        R$ {{ number_format($c['pedagio'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        R$ {{ number_format($c['advalorem'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        R$ {{ number_format($c['gris'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        R$ {{ number_format($c['tas'] ?? 0, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        @if(!empty($c['taxas_especiais']))
                                            <span title="{{ collect($c['taxas_especiais'])->map(fn($t) => $t['tipo'].': R$ '.number_format($t['valor'],2,',','.'))->join(' | ') }}"
                                                  class="cursor-help underline decoration-dotted text-amber-600 dark:text-amber-400">
                                                R$ {{ number_format($c['taxas_especiais_total'], 2, ',', '.') }}
                                            </span>
                                        @else
                                            R$ 0,00
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-100">
                                        @if(($c['icms_percentual'] ?? 0) > 0)
                                            <span title="ICMS {{ $c['icms_percentual'] }}%" class="cursor-help">
                                                R$ {{ number_format($c['icms_valor'], 2, ',', '.') }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white text-base">
                                        R$ {{ number_format($c['total'], 2, ',', '.') }}
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Detalhes das taxas especiais --}}
                @php $comTaxas = collect($this->cotacoes)->filter(fn($c) => !empty($c['taxas_especiais'])); @endphp
                @if($comTaxas->isNotEmpty())
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide mb-3">Detalhamento de Taxas Especiais</p>
                        @foreach($comTaxas as $c)
                            <div class="mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $c['nome'] }}:</span>
                                @foreach($c['taxas_especiais'] as $t)
                                    <span class="ml-2 text-xs bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded">
                                        {{ $t['tipo'] }}: R$ {{ number_format($t['valor'], 2, ',', '.') }}
                                        @if($t['obs']) — {{ $t['obs'] }} @endif
                                    </span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        @endif

    </div>
</x-filament-panels::page>
