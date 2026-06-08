<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modo</label>
                    <select wire:model="filtro" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="divergencias">Somente Divergências</option>
                        <option value="todos">Todos os Produtos</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo</label>
                    <select wire:model="filtroTipo" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="todos">Todos</option>
                        <option value="simples">Simples</option>
                        <option value="kit">Kit / Composto</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Buscar (SKU ou Nome)</label>
                    <input
                        type="text"
                        wire:model="buscaSku"
                        wire:keydown.enter="consultar"
                        placeholder="Busca específica (rápido) ou vazio para todos (background)..."
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm"
                    />
                </div>
                <div class="flex gap-2">
                    <x-filament::button wire:click="consultar" wire:loading.attr="disabled" class="flex-1">
                        <span wire:loading.remove wire:target="consultar">Consultar</span>
                        <span wire:loading wire:target="consultar">...</span>
                    </x-filament::button>
                    @if($this->consultaRealizada)
                        <x-filament::button wire:click="recarregar" color="gray" size="sm">
                            ↻
                        </x-filament::button>
                    @endif
                </div>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                💡 Com busca preenchida: consulta instantânea (máx 20 produtos). Sem busca: roda em background e notifica ao concluir.
            </p>

            @if($this->jobRodando)
                <div class="mt-3 flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Comparação em andamento... Você receberá uma notificação ao concluir. Clique em ↻ para atualizar.
                </div>
            @endif
        </div>

        {{-- Loading inline --}}
        <div wire:loading wire:target="consultar" class="text-center py-4">
            <div class="inline-flex items-center gap-2 text-gray-500 dark:text-gray-400 text-sm">
                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Consultando...
            </div>
        </div>

        {{-- Resultados --}}
        @if($this->consultaRealizada)
            <div wire:loading.remove wire:target="consultar" class="space-y-4">

                {{-- Resumo --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="flex gap-6 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">
                            Total: <strong class="text-gray-900 dark:text-white">{{ $this->totalProdutos }}</strong>
                        </span>
                        <span class="{{ $this->totalDivergencias > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                            Divergências: <strong>{{ $this->totalDivergencias }}</strong>
                        </span>
                    </div>
                    @if(!empty($this->resultados))
                        <x-filament::button wire:click="exportarCsv" color="gray" size="sm">
                            ⬇ Exportar CSV
                        </x-filament::button>
                    @endif
                </div>

                {{-- Tabela --}}
                @if(!empty($this->resultados))
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div style="max-height: 600px; overflow-y: auto;">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                    <tr>
                                        <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">SKU</th>
                                        <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Nome</th>
                                        <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Sistema</th>
                                        <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Primary</th>
                                        <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Secondary</th>
                                        <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($this->resultados as $r)
                                        <tr class="{{ $r['divergente'] ? 'bg-red-50 dark:bg-red-900/10' : '' }} hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $r['sku'] }}</td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Str::limit($r['nome'], 45) }}</td>
                                            <td class="px-4 py-2 text-center text-gray-500 dark:text-gray-400">{{ $r['sistema'] }}</td>
                                            <td class="px-4 py-2 text-center font-medium {{ $r['divergente'] ? 'text-orange-600 dark:text-orange-400' : 'text-gray-700 dark:text-gray-200' }}">
                                                {{ $r['primary'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-4 py-2 text-center font-medium {{ $r['divergente'] ? 'text-orange-600 dark:text-orange-400' : 'text-gray-700 dark:text-gray-200' }}">
                                                {{ $r['secondary'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if($r['divergente'])
                                                    <span class="text-xs bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300 px-2 py-0.5 rounded font-medium">DIVERGENTE</span>
                                                @else
                                                    <span class="text-xs bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 px-2 py-0.5 rounded">OK</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        @if($this->filtro === 'divergencias')
                            ✅ Nenhuma divergência encontrada! Estoques iguais nos dois Blings.
                        @else
                            Nenhum produto encontrado.
                        @endif
                    </div>
                @endif
            </div>
        @endif

    </div>
</x-filament-panels::page>
