<x-filament-panels::page>
    <div class="space-y-6">

        @if(!$this->contagemFinalizada)
            {{-- Input de leitura --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Código de Barras / SKU
                        </label>
                        <input
                            type="text"
                            wire:model="codigoInput"
                            wire:keydown.enter="bipar"
                            autofocus
                            placeholder="Bipe ou digite o código..."
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-lg px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">O leitor envia Enter automaticamente após a leitura</p>
                    </div>
                    <div class="pt-6">
                        <x-filament::button wire:click="bipar" size="lg">
                            Adicionar
                        </x-filament::button>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-bold text-lg text-primary-600 dark:text-primary-400">{{ count($this->itensContados) }}</span> produto(s) contado(s)
                        —
                        <span class="font-bold text-lg text-primary-600 dark:text-primary-400">{{ collect($this->itensContados)->sum('quantidade') }}</span> unidade(s) total
                    </p>
                    <div class="flex gap-2">
                        @if(!empty($this->itensContados))
                            <x-filament::button wire:click="finalizarContagem" color="success" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="finalizarContagem">Finalizar Contagem</span>
                                <span wire:loading wire:target="finalizarContagem">Processando...</span>
                            </x-filament::button>
                            <x-filament::button wire:click="novaContagem" color="gray">
                                Limpar
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tabela de itens contados --}}
            @if(!empty($this->itensContados))
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Itens Contados</h3>
                    </div>
                    <div style="max-height: 500px; overflow-y: auto;">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                <tr>
                                    <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">SKU</th>
                                    <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Cód. Barras</th>
                                    <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Produto</th>
                                    <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Grupo</th>
                                    <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Saldo Sistema</th>
                                    <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Contagem</th>
                                    <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach(array_reverse($this->itensContados, true) as $sku => $item)
                                    <tr wire:key="item-{{ $sku }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $item['sku'] }}</td>
                                        <td class="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $item['codigo_barras'] ?? '-' }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200 max-w-xs truncate" title="{{ $item['nome'] }}">{{ $item['nome'] }}</td>
                                        <td class="px-4 py-2">
                                            @if($item['grupo_tampo'] ?? null)
                                                <span class="text-xs bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 px-2 py-0.5 rounded">{{ $item['grupo_tampo'] }}</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center text-gray-500 dark:text-gray-400">{{ $item['saldo_sistema'] }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <input
                                                type="number"
                                                min="1"
                                                wire:model.lazy="itensContados.{{ $sku }}.quantidade"
                                                class="w-16 text-center rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm"
                                            />
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button wire:click="removerItem('{{ $sku }}')" class="text-red-500 hover:text-red-700 text-xs font-medium">
                                                Remover
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        @else
            @php
                $comAlteracao = array_filter($this->divergencias, fn($d) => $d['diferenca'] !== 0);
                $semAlteracao = array_filter($this->divergencias, fn($d) => $d['diferenca'] === 0);
            @endphp

            {{-- Resumo --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-700 dark:text-gray-100">{{ count($this->divergencias) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total contados</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-red-200 dark:border-red-800 p-4 text-center">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ count($comAlteracao) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Com divergência</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-green-200 dark:border-green-800 p-4 text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ count($semAlteracao) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sem alteração</div>
                </div>
            </div>

            @php
                $tableHead = '
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                        <tr>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">SKU</th>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Produto</th>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Grupo</th>
                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Sistema</th>
                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Contagem</th>
                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Diferença</th>
                        </tr>
                    </thead>';
            @endphp

            {{-- Com divergência --}}
            @if(!empty($comAlteracao))
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-red-200 dark:border-red-800 overflow-hidden">
                    <div class="px-4 py-3 border-b border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20">
                        <h3 class="text-sm font-semibold text-red-700 dark:text-red-300">⚠️ Com Divergência ({{ count($comAlteracao) }})</h3>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="w-full text-sm">
                            {!! $tableHead !!}
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($comAlteracao as $div)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $div['sku'] }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200 max-w-xs truncate" title="{{ $div['nome'] }}">{{ $div['nome'] }}</td>
                                        <td class="px-4 py-2">
                                            @if($div['grupo_tampo'] ?? null)
                                                <span class="text-xs bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 px-2 py-0.5 rounded">{{ $div['grupo_tampo'] }}</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center text-gray-500 dark:text-gray-400">{{ $div['saldo_sistema'] }}</td>
                                        <td class="px-4 py-2 text-center font-bold text-gray-700 dark:text-gray-200">{{ $div['contagem'] }}</td>
                                        <td class="px-4 py-2 text-center font-bold {{ $div['diferenca'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $div['diferenca'] > 0 ? '+' : '' }}{{ $div['diferenca'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Sem alteração --}}
            @if(!empty($semAlteracao))
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400">✅ Sem Alteração ({{ count($semAlteracao) }})</h3>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="w-full text-sm">
                            {!! $tableHead !!}
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($semAlteracao as $div)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 opacity-70">
                                        <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $div['sku'] }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200 max-w-xs truncate" title="{{ $div['nome'] }}">{{ $div['nome'] }}</td>
                                        <td class="px-4 py-2">
                                            @if($div['grupo_tampo'] ?? null)
                                                <span class="text-xs bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 px-2 py-0.5 rounded">{{ $div['grupo_tampo'] }}</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center text-gray-500 dark:text-gray-400">{{ $div['saldo_sistema'] }}</td>
                                        <td class="px-4 py-2 text-center font-bold text-gray-700 dark:text-gray-200">{{ $div['contagem'] }}</td>
                                        <td class="px-4 py-2 text-center text-gray-400">✓ 0</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <x-filament::button wire:click="novaContagem" color="gray">
                    Nova Contagem
                </x-filament::button>
            </div>
        @endif

    </div>
</x-filament-panels::page>
