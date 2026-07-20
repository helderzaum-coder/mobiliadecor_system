<x-filament-panels::page>
    <div class="space-y-4">

        @forelse($this->getContagens() as $contagem)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Cabeçalho clicável --}}
                <button
                    wire:click="abrirContagem({{ $contagem->id }})"
                    class="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 text-left"
                >
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            #{{ $contagem->id }} — {{ $contagem->created_at->format('d/m/Y H:i') }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            por {{ $contagem->user->name ?? '—' }}
                        </span>
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded">
                            {{ $contagem->total_itens }} itens
                        </span>
                        @if($contagem->com_divergencia > 0)
                            <span class="text-xs bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 px-2 py-0.5 rounded">
                                ⚠️ {{ $contagem->com_divergencia }} com divergência
                            </span>
                        @endif
                        <span class="text-xs bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 px-2 py-0.5 rounded">
                            ✅ {{ $contagem->sem_alteracao }} sem alteração
                        </span>
                    </div>
                    <span class="text-gray-400 text-xs">
                        {{ $this->contagemAberta === $contagem->id ? '▲' : '▼' }}
                    </span>
                </button>

                {{-- Detalhe expandido --}}
                @if($this->contagemAberta === $contagem->id)
                    @php
                        $itens = $contagem->itens;
                        $comDiv = $itens->filter(fn($i) => $i->diferenca !== 0);
                        $semDiv = $itens->filter(fn($i) => $i->diferenca === 0);
                    @endphp

                    @if($comDiv->isNotEmpty())
                        <div class="border-t border-red-200 dark:border-red-800">
                            <div class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-xs font-semibold text-red-700 dark:text-red-300">
                                ⚠️ Com Divergência ({{ $comDiv->count() }})
                            </div>
                            <div style="max-height: 350px; overflow-y: auto;">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                        <tr>
                                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">SKU</th>
                                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Produto</th>
                                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Grupo</th>
                                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Sistema</th>
                                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Contagem</th>
                                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Diferença</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($comDiv as $item)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $item->sku }}</td>
                                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $item->nome }}</td>
                                                <td class="px-4 py-2">
                                                    @if($item->grupo_tampo)
                                                        <span class="text-xs bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 px-2 py-0.5 rounded">{{ $item->grupo_tampo }}</span>
                                                    @else
                                                        <span class="text-xs text-gray-400">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-center text-gray-500">{{ $item->saldo_sistema }}</td>
                                                <td class="px-4 py-2 text-center font-bold text-gray-700 dark:text-gray-200">{{ $item->contagem }}</td>
                                                <td class="px-4 py-2 text-center font-bold {{ $item->diferenca > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                    {{ $item->diferenca > 0 ? '+' : '' }}{{ $item->diferenca }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    @if($semDiv->isNotEmpty())
                        <div class="border-t border-gray-200 dark:border-gray-700">
                            <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                ✅ Sem Alteração ({{ $semDiv->count() }})
                            </div>
                            <div style="max-height: 250px; overflow-y: auto;">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
                                        <tr>
                                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">SKU</th>
                                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Produto</th>
                                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Sistema</th>
                                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Contagem</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($semDiv as $item)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 opacity-70">
                                                <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $item->sku }}</td>
                                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $item->nome }}</td>
                                                <td class="px-4 py-2 text-center text-gray-500">{{ $item->saldo_sistema }}</td>
                                                <td class="px-4 py-2 text-center font-bold text-gray-700 dark:text-gray-200">{{ $item->contagem }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                Nenhuma contagem registrada ainda.
            </div>
        @endforelse

    </div>
</x-filament-panels::page>
