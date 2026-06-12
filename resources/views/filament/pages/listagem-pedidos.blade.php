<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Período</label>
                    <select wire:model.live="periodo" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="hoje">Hoje</option>
                        <option value="esta_semana">Esta Semana</option>
                        <option value="este_mes">Este Mês</option>
                        <option value="mes_passado">Mês Passado</option>
                        <option value="customizado">Customizado</option>
                    </select>
                </div>

                <div x-show="$wire.periodo === 'customizado'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">De</label>
                    <input type="date" wire:model="data_inicio" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm" />
                </div>
                <div x-show="$wire.periodo === 'customizado'">
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Até</label>
                    <input type="date" wire:model="data_fim" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Canal</label>
                    <select wire:model="filtro_canal" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Models\PedidoBlingStaging::distinct()->orderBy('canal')->pluck('canal')->filter() as $canal)
                            <option value="{{ $canal }}">{{ $canal }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Conta</label>
                    <select wire:model="filtro_conta" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="">Todas</option>
                        <option value="primary">Mobilia Decor</option>
                        <option value="secondary">HES Móveis</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white mb-1">Situação Bling</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @foreach(['Em aberto', 'Verificado', 'Em andamento', 'Enviado', 'Atendido', 'Entregue', 'Cancelado', 'Faturado – Pendente de Cotação', 'Em Cotação', 'ML Etiqueta', 'Shopee Xpress', 'Lançado Envio'] as $sit)
                            <label class="inline-flex items-center gap-1 text-xs text-gray-700 dark:text-white cursor-pointer">
                                <input type="checkbox" value="{{ $sit }}" wire:model="filtro_situacao" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5" />
                                {{ $sit }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <x-filament::button wire:click="consultar" wire:loading.attr="disabled" class="w-full">
                        <span wire:loading.remove wire:target="consultar">Consultar</span>
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
                Carregando pedidos...
            </div>
        </div>

        {{-- Resultados --}}
        @if($this->consultaRealizada)
            <div wire:loading.remove wire:target="consultar" class="space-y-4">

                {{-- Resumo --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-white">
                        Linhas: <strong class="text-gray-900 dark:text-white">{{ number_format(count($this->resultados), 0, ',', '.') }}</strong>
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
                        <div style="max-height: 650px; overflow-y: auto;">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/80 sticky top-0">
                                    <tr>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Data</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Status Bling</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">CNPJ</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Canal</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Produto</th>
                                        <th class="text-center px-3 py-2 text-gray-700 dark:text-white font-semibold">Qtd</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Pedido Bling</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Pedido Canal</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Cliente</th>
                                        <th class="text-left px-3 py-2 text-gray-700 dark:text-white font-semibold">Liberação Etiqueta</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($this->resultados as $r)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $r['data'] }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $r['situacao_bling'] }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $r['cnpj'] }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $r['canal'] }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white" title="{{ $r['produto'] }}">{{ \Illuminate\Support\Str::limit($r['produto'], 60) }}</td>
                                            <td class="px-3 py-2 text-center text-gray-900 dark:text-white">{{ $r['quantidade'] }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $r['pedido_bling'] }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $r['pedido_canal'] }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white" title="{{ $r['cliente'] }}">{{ \Illuminate\Support\Str::limit($r['cliente'], 30) }}</td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white whitespace-nowrap">{{ $r['is_ml'] ? $r['liberacao_etiqueta'] : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Nenhum pedido encontrado no período.
                    </div>
                @endif
            </div>
        @endif

    </div>
</x-filament-panels::page>
