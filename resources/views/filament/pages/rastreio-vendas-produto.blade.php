<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU *</label>
                    <input
                        type="text"
                        wire:model="sku"
                        wire:keydown.enter="consultar"
                        placeholder="Ex: 10061817"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Período</label>
                    <select wire:model="periodo" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="hoje">Hoje</option>
                        <option value="esta_semana">Esta Semana</option>
                        <option value="este_mes">Este Mês</option>
                        <option value="mes_passado">Mês Passado</option>
                        <option value="customizado">Customizado</option>
                    </select>
                </div>

                @if($this->periodo === 'customizado')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">De</label>
                        <input type="date" wire:model="data_inicio" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Até</label>
                        <input type="date" wire:model="data_fim" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm" />
                    </div>
                @endif

                <div>
                    <x-filament::button wire:click="consultar" wire:loading.attr="disabled" class="w-full">
                        <span wire:loading.remove wire:target="consultar">Rastrear</span>
                        <span wire:loading wire:target="consultar">...</span>
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Loading --}}
        <div wire:loading wire:target="consultar" class="text-center py-4">
            <div class="inline-flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Rastreando vendas...
            </div>
        </div>

        {{-- Resultados --}}
        @if($this->consultaRealizada)
            <div wire:loading.remove wire:target="consultar" class="space-y-4">

                {{-- Resumo --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm space-y-1">
                            @if($this->nomeProduto)
                                <p class="text-gray-600 dark:text-gray-300">
                                    Produto: <strong class="text-gray-900 dark:text-white">{{ $this->sku }}</strong> — {{ $this->nomeProduto }}
                                </p>
                            @endif
                            <div class="flex gap-6">
                                <span class="text-gray-600 dark:text-gray-300">
                                    Vendas encontradas: <strong class="text-gray-900 dark:text-white">{{ count($this->resultados) }}</strong>
                                </span>
                                <span class="text-gray-600 dark:text-gray-300">
                                    Total unidades: <strong class="text-gray-900 dark:text-white">{{ $this->totalUnidades }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tabela --}}
                @if(!empty($this->resultados))
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div style="max-height: 600px; overflow-y: auto;">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                    <tr>
                                        <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Data</th>
                                        <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Pedido</th>
                                        <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">NF-e</th>
                                        <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Canal</th>
                                        <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Tipo</th>
                                        <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Kit SKU</th>
                                        <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Qtd</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($this->resultados as $r)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ \Carbon\Carbon::parse($r['data'])->format('d/m/Y') }}</td>
                                            <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $r['pedido'] }}</td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $r['nfe'] }}</td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $r['canal'] }}</td>
                                            <td class="px-4 py-2 text-center">
                                                @if($r['tipo'] === 'Via Kit')
                                                    <span class="text-xs bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 px-2 py-0.5 rounded font-medium">Via Kit</span>
                                                @else
                                                    <span class="text-xs bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 px-2 py-0.5 rounded">Direta</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $r['kit_sku'] }}</td>
                                            <td class="px-4 py-2 text-center font-bold text-gray-900 dark:text-white">{{ $r['qtd'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Nenhuma venda encontrada para o SKU <strong>{{ $this->sku }}</strong> no período selecionado.
                    </div>
                @endif
            </div>
        @endif

    </div>
</x-filament-panels::page>
