<x-filament-panels::page>
    @php $totais = $this->totais; @endphp

    {{-- Resumo --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $totais['total'] }}</div>
            <div class="text-xs text-gray-500">Total CT-es</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-green-600">{{ $totais['utilizados'] }}</div>
            <div class="text-xs text-gray-500">Utilizados</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-orange-600">{{ $totais['nao_utilizados'] }}</div>
            <div class="text-xs text-gray-500">Não Utilizados</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-orange-600">R$ {{ number_format($totais['valor_nao_utilizado'], 2, ',', '.') }}</div>
            <div class="text-xs text-gray-500">Valor Não Utilizado</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-4 space-y-3">
        {{-- Status --}}
        <div class="flex gap-2">
            <button wire:click="$set('filtro', 'nao_utilizados')"
                style="{{ $filtro === 'nao_utilizados' ? 'background:#d97706;color:#fff;' : 'background:#374151;color:#d1d5db;' }}padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                Não Utilizados
            </button>
            <button wire:click="$set('filtro', 'utilizados')"
                style="{{ $filtro === 'utilizados' ? 'background:#059669;color:#fff;' : 'background:#374151;color:#d1d5db;' }}padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                Utilizados
            </button>
            <button wire:click="$set('filtro', 'todos')"
                style="{{ $filtro === 'todos' ? 'background:#2563eb;color:#fff;' : 'background:#374151;color:#d1d5db;' }}padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                Todos
            </button>
        </div>

        {{-- Busca + Transportadora + Período --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Buscar (nº CT-e, NF-e, destinatário)</label>
                <input type="text" wire:model.live.debounce.500ms="busca" placeholder="Buscar..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Transportadora</label>
                <select wire:model.live="transportadora"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
                    <option value="">Todas</option>
                    @foreach($this->transportadoras as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Período</label>
                <select wire:model.live="periodo"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
                    <option value="">Todos</option>
                    <option value="hoje">Hoje</option>
                    <option value="esta_semana">Esta semana</option>
                    <option value="este_mes">Este mês</option>
                    <option value="mes_passado">Mês passado</option>
                    <option value="customizado">Customizado</option>
                </select>
            </div>
            @if($periodo === 'customizado')
            <div class="flex gap-2">
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">De</label>
                    <input type="date" wire:model.live="data_inicio"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Até</label>
                    <input type="date" wire:model.live="data_fim"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Resultado --}}
    <div class="text-xs text-gray-500 mb-2">{{ $this->ctes->count() }} CT-e(s) encontrado(s)</div>

    {{-- Tabela --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b border-gray-300 dark:border-gray-600 text-gray-500 text-xs">
                    <th class="text-left p-2">Nº CT-e</th>
                    <th class="text-left p-2">Chave NF-e</th>
                    <th class="text-right p-2">Valor Frete</th>
                    <th class="text-left p-2">Transportadora</th>
                    <th class="text-left p-2">Destinatário</th>
                    <th class="text-center p-2">Status</th>
                    <th class="text-left p-2">Data Emissão</th>
                    <th class="text-center p-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->ctes as $cte)
                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="p-2 font-mono text-gray-800 dark:text-white">{{ $cte->numero_cte }}</td>
                        <td class="p-2 font-mono text-xs text-gray-600 dark:text-gray-400"
                            style="cursor:pointer;" title="{{ $cte->chave_nfe }}"
                            onclick="navigator.clipboard.writeText('{{ $cte->chave_nfe }}').then(()=>{this.innerText='Copiado!';setTimeout(()=>this.innerText='{{ substr($cte->chave_nfe, 0, 20) }}...',1500)})">
                            {{ substr($cte->chave_nfe, 0, 20) }}...
                        </td>
                        <td class="p-2 text-right font-semibold text-gray-800 dark:text-white">R$ {{ number_format($cte->valor_frete, 2, ',', '.') }}</td>
                        <td class="p-2 text-gray-600 dark:text-gray-400">{{ $cte->transportadora }}</td>
                        <td class="p-2 text-gray-600 dark:text-gray-400">{{ $cte->destinatario }}</td>
                        <td class="p-2 text-center">
                            @if($cte->utilizado)
                                <span style="background:#059669;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">Utilizado</span>
                            @else
                                <span style="background:#d97706;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">Pendente</span>
                            @endif
                        </td>
                        <td class="p-2 text-xs text-gray-500">{{ $cte->data_emissao?->format('d/m/Y') ?? $cte->created_at?->format('d/m/Y') }}</td>
                        <td class="p-2 text-center">
                            @if(!$cte->utilizado)
                                <div class="flex items-center gap-1 justify-center" x-data="{ open: false, pedido: '' }">
                                    <button wire:click="vincularManual({{ $cte->id }})"
                                        style="background:#2563eb;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;"
                                        title="Vincular automaticamente pelo nome do destinatário">
                                        Auto
                                    </button>
                                    <button @click="open = !open"
                                        style="background:#7c3aed;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;"
                                        title="Vincular por número do pedido">
                                        Pedido
                                    </button>
                                    <div x-show="open" x-cloak class="flex items-center gap-1">
                                        <input type="text" x-model="pedido" placeholder="Nº pedido"
                                            class="rounded border border-gray-400 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs px-2 py-1 w-36 text-gray-800 dark:text-white">
                                        <button @click="$wire.vincularPorPedido({{ $cte->id }}, pedido); open = false"
                                            style="background:#059669;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;">
                                            OK
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-4 text-center text-gray-500">Nenhum CT-e encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
