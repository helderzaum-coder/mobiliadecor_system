<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Seletor de Conta --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-3">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Conta:</span>
                @foreach($this->getAccounts() as $key => $name)
                    <button wire:click="switchAccount('{{ $key }}')"
                        @class([
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition',
                            'bg-primary-500 text-white' => $accountKey === $key,
                            'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' => $accountKey !== $key,
                        ])>
                        {{ $name }}
                    </button>
                @endforeach
                <span class="ml-auto text-sm font-medium text-gray-500 dark:text-gray-400">Margem %:</span>
                <input type="number" step="0.1" wire:model.blur="margemDesejada"
                    class="w-16 px-2 py-1 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Imposto %:</span>
                <input type="number" step="0.1" wire:model.blur="impostoPercent"
                    class="w-20 px-2 py-1 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500">
            </div>
        </div>

        {{-- Abas --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-1">
            <div class="flex gap-1">
                <button wire:click="$set('abaAtiva', 'promocoes')"
                    @class(['px-3 py-1.5 rounded-lg text-sm font-medium transition',
                        'bg-primary-500 text-white' => $abaAtiva === 'promocoes',
                        'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' => $abaAtiva !== 'promocoes',
                    ])>Promoções</button>
                <button wire:click="$set('abaAtiva', 'buscar_item')"
                    @class(['px-3 py-1.5 rounded-lg text-sm font-medium transition',
                        'bg-primary-500 text-white' => $abaAtiva === 'buscar_item',
                        'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5' => $abaAtiva !== 'buscar_item',
                    ])>Buscar por Item</button>
            </div>
        </div>

        @if($abaAtiva === 'promocoes')
        <div style="display:flex; gap:1rem; align-items:flex-start;">
            {{-- Lista de Promoções --}}
            <div style="width:280px; flex-shrink:0;">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-3">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            Promoções ({{ count($promotions) }})
                        </h3>
                        <button wire:click="loadPromotions" class="text-xs text-primary-600 hover:text-primary-500">
                            Atualizar
                        </button>
                    </div>

                    <div class="max-h-[72vh] overflow-y-auto">
                        @forelse($promotions as $index => $promo)
                            @php $statusColor = match($promo['status'] ?? '') {
                                'started' => 'success',
                                'candidate' => 'warning',
                                'scheduled' => 'info',
                                default => 'gray'
                            }; @endphp
                            <button wire:click="selectPromotion({{ $index }})"
                                @class([
                                    'w-full text-left px-2 py-1.5 flex items-center gap-2 transition text-xs rounded',
                                    'bg-primary-50 dark:bg-primary-500/10 ring-1 ring-primary-500/30' => ($selectedPromotion['id'] ?? '') === $promo['id'],
                                    'hover:bg-gray-50 dark:hover:bg-white/5' => ($selectedPromotion['id'] ?? '') !== $promo['id'],
                                ])>
                                <x-filament::badge size="sm" :color="$statusColor" class="shrink-0">
                                    {{ $promo['status'] ?? '-' }}
                                </x-filament::badge>
                                <span class="truncate font-medium text-gray-900 dark:text-gray-100">
                                    {{ $promo['name'] ?? 'Sem nome' }}
                                </span>
                                @if(empty($promo['name']))
                                    <span class="ml-auto shrink-0 text-[9px] text-red-400">sem adesão</span>
                                @else
                                    <span class="ml-auto shrink-0 text-[10px] text-gray-400">{{ $promo['type'] ?? '' }}</span>
                                @endif
                            </button>
                        @empty
                            <p class="text-xs text-gray-400 text-center py-6">Nenhuma promoção</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Itens da Promoção --}}
            <div style="flex:1; min-width:0;">
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-3 relative min-h-[200px]">

                    {{-- Loading --}}
                    <div wire:loading wire:target="selectPromotion,loadItems,loadAllItems,doLoadItems,confirmarAdesao,searchItems"
                        class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 z-10 flex items-center justify-center rounded-xl backdrop-blur-sm">
                        <x-filament::loading-indicator class="h-6 w-6" />
                    </div>

                    @if($needsLoadItems)
                        <div wire:init="doLoadItems"></div>
                    @endif

                    {{-- Modal de Adesão --}}
                    @if($aderindoItemId && $aderindoInfo)
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" wire:click.self="cancelarAdesao">
                            <div class="w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
                        @php
                            $precoOriginal = $aderindoInfo['original_price'] ?? 0;
                            $frete = $aderindoInfo['frete'] ?? 0;
                            $comissaoPercent = $aderindoInfo['comissao_percent'] ?? 11.5;
                            $comissaoValorBase = $aderindoInfo['comissao_valor'] ?? 0;
                            $buyerPrice = $aderindoInfo['buyer_price'] ?? 0;
                            $impPercent = $impostoPercent;
                            $custoProduto = ($aderindoInfo['custo_produto'] ?? 0) > 0
                                ? $aderindoInfo['custo_produto']
                                : ($custoManual ?? 0);
                            $temSubsidio = $aderindoInfo['tem_subsidio'] ?? false;
                            $meliPercentage = $aderindoInfo['meli_percentage'] ?? 0;
                            $netProceedsAmount = $aderindoInfo['net_proceeds_amount'] ?? null;
                            $precoPromo = $aderindoPreco ?? 0;
                            // Comissão sobre o preço que o comprador paga
                            $precoBaseComissao = $buyerPrice > 0 ? $buyerPrice : $precoPromo;
                            $netProceedsCompativel = $netProceedsAmount !== null && abs($precoBaseComissao - $precoPromo) < 0.01;
                            // Comissão: usar percentual sobre o preço promo
                            $comissaoCheia = $precoBaseComissao * ($comissaoPercent / 100);
                            // Participacao do ML no desconto da promocao; nao e abatimento direto de tarifa.
                            $participacaoMl = $temSubsidio ? $precoOriginal * ($meliPercentage / 100) : 0;
                            $deducoesMl = $netProceedsCompativel ? max(0, $precoBaseComissao - $netProceedsAmount) : null;
                            $comissao = $deducoesMl !== null ? max(0, $deducoesMl - $frete) : $comissaoCheia;
                            $imposto = $precoBaseComissao * ($impPercent / 100);
                            $custoTotal = $frete + $comissao + $imposto + $custoProduto;
                            $margem = $precoBaseComissao - $custoTotal;
                            $margemPercent = $precoBaseComissao > 0 ? ($margem / $precoBaseComissao) * 100 : 0;
                            $desconto = $precoOriginal > 0 ? (($precoOriginal - $precoBaseComissao) / $precoOriginal) * 100 : 0;
                            $comissaoPercentEfetivo = $precoBaseComissao > 0 ? ($comissao / $precoBaseComissao) * 100 : 0;
                            $divisor = 1 - ($comissaoPercentEfetivo / 100) - ($impPercent / 100) - ($margemDesejada / 100);
                            $precoSugerido = $divisor > 0 ? ($frete + $custoProduto) / $divisor : 0;
                        @endphp
                        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-xl">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $aderindoInfo['title'] }}</p>
                                    <p class="text-xs text-gray-500 font-mono">{{ $aderindoItemId }}</p>
                                </div>
                                <button wire:click="cancelarAdesao" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-lg">&times;</button>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-xs mb-3">
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                    <span class="text-gray-500 block">Preço Original</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($precoOriginal, 2, ',', '.') }}</span>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                    <span class="text-gray-500 block">Frete</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($frete, 2, ',', '.') }}</span>
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                    <span class="text-gray-500 block">Comissão ({{ number_format($comissaoPercentEfetivo, 1) }}%)</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($comissao, 2, ',', '.') }}</span>
                                    @if($deducoesMl !== null)
                                        <span class="text-[10px] text-green-600 block">via net proceeds ML</span>
                                    @endif
                                </div>
                                <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                    <span class="text-gray-500 block">Imposto ({{ number_format($impPercent, 1) }}%)</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($imposto, 2, ',', '.') }}</span>
                                </div>
                                <div class="p-2 rounded {{ ($custoProduto > 0 || ($custoManual ?? 0) > 0) ? 'bg-gray-50 dark:bg-gray-900' : 'bg-yellow-50 dark:bg-yellow-900/20' }}">
                                    <span class="text-gray-500 block">Custo</span>
                                    @if($custoProduto > 0)
                                        <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($custoProduto, 2, ',', '.') }}</span>
                                        <span class="text-[10px] text-gray-400 block">{{ $aderindoInfo['sku'] ?? '' }}</span>
                                    @else
                                        <div class="flex items-center gap-1">
                                            <span class="text-xs text-gray-500">R$</span>
                                            <input type="number" step="0.01" wire:model.blur="custoManual"
                                                placeholder="Informar"
                                                class="w-20 px-1 py-0.5 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                        </div>
                                    @endif
                                </div>
                                @if($temSubsidio)
                                <div class="p-2 rounded bg-blue-50 dark:bg-blue-900/20">
                                    <span class="text-gray-500 block">Participação ML ({{ number_format($meliPercentage, 1) }}%)</span>
                                    <span class="font-semibold text-blue-700 dark:text-blue-400">R$ {{ number_format($participacaoMl, 2, ',', '.') }} no desconto</span>
                                </div>
                                @endif
                            </div>

                            @php
                                $comissaoAtual = $precoOriginal * ($comissaoPercentEfetivo / 100);
                                $impostoAtual = $precoOriginal * ($impPercent / 100);
                                $custoTotalAtual = $frete + $comissaoAtual + $impostoAtual + $custoProduto;
                                $margemAtual = $precoOriginal - $custoTotalAtual;
                                $margemAtualPercent = $precoOriginal > 0 ? ($margemAtual / $precoOriginal) * 100 : 0;
                            @endphp
                            <div class="p-2 rounded bg-gray-50 dark:bg-gray-900/50 mb-1 flex items-center gap-3">
                                <span class="text-xs text-gray-500">Margem atual (s/ promo):</span>
                                <span class="text-xs font-bold" style="color: {{ $margemAtualPercent >= $margemDesejada ? '#15803d' : ($margemAtualPercent >= 0 ? '#a16207' : '#dc2626') }}">R$ {{ number_format($margemAtual, 2, ',', '.') }} ({{ number_format($margemAtualPercent, 1) }}%)</span>
                            </div>

                            <div class="flex items-center gap-3 flex-wrap p-2 rounded {{ $margemPercent >= $margemDesejada ? 'bg-green-50 dark:bg-green-900/20' : ($margemPercent >= 0 ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'bg-red-50 dark:bg-red-900/20') }}">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-500">R$</span>
                                    <input type="number" step="0.01" wire:model.blur="aderindoPreco"
                                        class="w-24 px-2 py-1 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500">
                                    <x-filament::button size="xs" color="gray" wire:click="$refresh">Calc</x-filament::button>
                                </div>
                                @if($precoSugerido > 0)
                                <span class="text-xs text-primary-600 font-semibold">Sug: R$ {{ number_format($precoSugerido, 2, ',', '.') }}</span>
                                @endif
                                <span class="text-xs">Desc: {{ number_format($desconto, 1) }}%</span>
                                <span class="text-xs font-bold" style="color: {{ $margemPercent >= $margemDesejada ? '#15803d' : ($margemPercent >= 0 ? '#a16207' : '#dc2626') }}">Margem: R$ {{ number_format($margem, 2, ',', '.') }} ({{ number_format($margemPercent, 1) }}%)</span>
                                <div class="ml-auto flex gap-2">
                                    <x-filament::button size="sm" color="gray" wire:click="pularParaProximo">Pular</x-filament::button>
                                    <x-filament::button size="sm" color="gray" wire:click="cancelarAdesao">Fechar</x-filament::button>
                                    <x-filament::button size="sm" wire:click="confirmarAdesao">Aderir</x-filament::button>
                                </div>
                            </div>
                        </div>
                            </div>
                        </div>
                    @elseif($aderindoItemId && !$aderindoInfo)
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                            <div class="p-6 rounded-xl bg-white dark:bg-gray-800 flex items-center gap-3">
                                <x-filament::loading-indicator class="h-5 w-5" />
                                <span class="text-sm text-gray-500">Buscando dados do item...</span>
                            </div>
                        </div>
                    @endif

                    @if($selectedPromotion)
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $selectedPromotion['name'] ?? 'Promoção' }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    @php $filteredCount = count($this->getFilteredItems()); @endphp
                                    {{ $filteredCount }}/{{ $totalItems }} itens · {{ $selectedPromotion['type'] ?? '' }}
                                    @if($filtroStatus)
                                        · filtro: {{ $filtroStatus }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <select wire:model.live="filtroStatus"
                                    class="px-2 py-1 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500">
                                    <option value="">Todos</option>
                                    <option value="candidate">Candidate</option>
                                    <option value="started">Started</option>
                                    <option value="active">Active</option>
                                    <option value="finished">Finished</option>
                                </select>
                                <input type="text" wire:model="searchItem" wire:keydown.enter="searchItems" placeholder="Buscar MLB ou SKU..."
                                    class="px-2 py-1 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 w-44">
                                <x-filament::button size="xs" color="gray" wire:click="searchItems">
                                    Buscar
                                </x-filament::button>
                                @if($searchItem)
                                    <x-filament::button size="xs" color="gray" wire:click="$set('searchItem', '')">
                                        Limpar
                                    </x-filament::button>
                                @endif
                                @if($searchAfter)
                                    <x-filament::button size="xs" color="gray" wire:click="loadItems">
                                        + Mais
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" wire:click="loadAllItems"
                                        wire:confirm="Carregar todos pode demorar. Continuar?">
                                        Todos
                                    </x-filament::button>
                                @endif
                                @if(!empty($itensPulados))
                                    <x-filament::button size="xs" color="danger" wire:click="limparIgnorados"
                                        wire:confirm="Limpar todos os itens ignorados desta promoção?">
                                        Limpar ignorados ({{ count($itensPulados) }})
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>

                        {{-- Tabela --}}
                        <div class="overflow-auto max-h-[65vh] rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
                            <table class="fi-ta-table w-full table-auto text-start">
                                <thead class="bg-gray-50 dark:bg-white/5">
                                    <tr>
                                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">MLB ID</th>
                                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">Produto</th>
                                        <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                                        <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">Preço Original</th>
                                        <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">Preço Promo</th>
                                        <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach($this->getFilteredItems() as $item)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition">
                                            <td class="px-3 py-2">
                                                <span class="font-mono text-xs text-gray-600 dark:text-gray-300" title="{{ $item['title'] ?: $item['id'] }}">
                                                    {{ $item['id'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 max-w-[250px]">
                                                <span class="text-xs text-gray-900 dark:text-gray-100 truncate block" title="{{ $item['title'] }}">
                                                    {{ $item['title'] ?: '—' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2">
                                                @php $sc = match($item['status']) {
                                                    'candidate' => 'warning',
                                                    'active', 'started' => 'success',
                                                    'finished' => 'gray',
                                                    default => 'gray'
                                                }; @endphp
                                                <x-filament::badge size="sm" :color="$sc">
                                                    {{ $item['status'] }}
                                                </x-filament::badge>
                                            </td>
                                            <td class="px-3 py-2 text-end text-xs text-gray-700 dark:text-gray-300 tabular-nums">
                                                {{ ($item['original_price'] ?? $item['price']) ? 'R$ ' . number_format($item['original_price'] ?? $item['price'], 2, ',', '.') : '—' }}
                                            </td>
                                            <td class="px-3 py-2 text-end">
                                                @if($editingItemId === $item['id'])
                                                    <div class="inline-flex items-center gap-1">
                                                        <input type="number" step="0.01" wire:model="editingDealPrice"
                                                            class="w-20 px-1.5 py-0.5 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                                        <button wire:click="savePrice" class="text-green-600 text-xs font-bold">✓</button>
                                                        <button wire:click="cancelEdit" class="text-gray-400 text-xs">✕</button>
                                                    </div>
                                                @else
                                                    <span class="text-xs tabular-nums cursor-pointer hover:text-primary-600 transition"
                                                        wire:click="startEditPrice('{{ $item['id'] }}', {{ $item['deal_price'] ?? $item['price'] ?? 'null' }})"
                                                        title="Clique para editar">
                                                        @if($item['deal_price'])
                                                            <span class="font-semibold text-green-600 dark:text-green-400">
                                                                R$ {{ number_format($item['deal_price'], 2, ',', '.') }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                                        @endif
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-end">
                                                <div class="inline-flex items-center gap-2">
                                                    @if($item['status'] === 'candidate')
                                                        @if(!empty($selectedPromotion['name']))
                                                            <button wire:click="iniciarAdesao('{{ $item['id'] }}')"
                                                                class="text-xs text-primary-600 hover:text-primary-500 font-medium">
                                                                Aderir
                                                            </button>
                                                        @else
                                                            <span class="text-[10px] text-red-400">sem adesão</span>
                                                        @endif
                                                    @endif
                                                    <button wire:click="removeItem('{{ $item['id'] }}')"
                                                        wire:confirm="Remover {{ $item['id'] }}?"
                                                        class="text-xs text-red-500 hover:text-red-400 font-medium">
                                                        Remover
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if(empty($items) && !$loading)
                                <p class="text-center text-xs text-gray-400 py-8">Nenhum item</p>
                            @endif
                        </div>
                    @else
                        <div class="flex items-center justify-center h-48 text-gray-400 dark:text-gray-500">
                            <p class="text-sm">← Selecione uma promoção</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @if($abaAtiva === 'buscar_item')
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex items-center gap-3 mb-4">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">MLB ID:</span>
                <input type="text" wire:model="buscarItemId" wire:keydown.enter="buscarPromocoesDoItem"
                    placeholder="Ex: MLB4486776021 ou 4486776021"
                    class="px-3 py-1.5 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 w-64">
                <x-filament::button size="sm" wire:click="buscarPromocoesDoItem">
                    Buscar Promoções
                </x-filament::button>
                <div wire:loading wire:target="buscarPromocoesDoItem">
                    <x-filament::loading-indicator class="h-5 w-5" />
                </div>
            </div>

            @if(!empty($promocoesDoItem))
                {{-- Painel de Adesão (reutilizado) --}}
                @if($aderindoItemId && $aderindoInfo && $abaAtiva === 'buscar_item')
                    @php
                        $precoOriginal = $aderindoInfo['original_price'] ?? 0;
                        $frete = $aderindoInfo['frete'] ?? 0;
                        $comissaoPercent = $aderindoInfo['comissao_percent'] ?? 11.5;
                        $impPercent = $impostoPercent;
                        $custoProduto = ($aderindoInfo['custo_produto'] ?? 0) > 0
                            ? $aderindoInfo['custo_produto']
                            : ($custoManual ?? 0);
                        $temSubsidio = $aderindoInfo['tem_subsidio'] ?? false;
                        $meliPercentage = $aderindoInfo['meli_percentage'] ?? 0;
                        $netProceedsAmount = $aderindoInfo['net_proceeds_amount'] ?? null;
                        $precoPromo = $aderindoPreco ?? 0;
                        $comissaoCheia = $precoPromo * ($comissaoPercent / 100);
                        $participacaoMl = $temSubsidio ? $precoOriginal * ($meliPercentage / 100) : 0;
                        $deducoesMl = $netProceedsAmount !== null && abs($precoPromo - ($aderindoInfo['buyer_price'] ?? $precoPromo)) < 0.01
                            ? max(0, $precoPromo - $netProceedsAmount)
                            : null;
                        $comissao = $deducoesMl !== null ? max(0, $deducoesMl - $frete) : $comissaoCheia;
                        $imposto = $precoPromo * ($impPercent / 100);
                        $custoTotal = $frete + $comissao + $imposto + $custoProduto;
                        $margem = $precoPromo - $custoTotal;
                        $margemPercent = $precoPromo > 0 ? ($margem / $precoPromo) * 100 : 0;
                        $desconto = $precoOriginal > 0 ? (($precoOriginal - $precoPromo) / $precoOriginal) * 100 : 0;
                        $divisor = 1 - ($comissaoPercent / 100) - ($impPercent / 100) - ($margemDesejada / 100);
                        $precoSugerido = $divisor > 0 ? ($frete + $custoProduto) / $divisor : 0;
                    @endphp
                    <div class="mb-4 p-4 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-primary-500/30 shadow-sm">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $aderindoInfo['title'] }}</p>
                                <p class="text-xs text-gray-500 font-mono">{{ $aderindoItemId }} • {{ $selectedPromotion['name'] ?? '' }}</p>
                            </div>
                            <x-filament::button size="xs" color="gray" wire:click="cancelarAdesao">Fechar</x-filament::button>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs mb-3">
                            <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                <span class="text-gray-500 block">Preço Original</span>
                                <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($precoOriginal, 2, ',', '.') }}</span>
                            </div>
                            <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                <span class="text-gray-500 block">Frete Grátis</span>
                                <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($frete, 2, ',', '.') }}</span>
                            </div>
                            <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                <span class="text-gray-500 block">Comissão ML ({{ number_format($comissaoPercent, 1) }}%)</span>
                                <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($comissao, 2, ',', '.') }}</span>
                            </div>
                            <div class="p-2 rounded bg-gray-50 dark:bg-gray-900">
                                <span class="text-gray-500 block">Imposto ({{ number_format($impPercent, 1) }}%)</span>
                                <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($imposto, 2, ',', '.') }}</span>
                            </div>
                            <div class="p-2 rounded {{ ($custoProduto > 0 || ($custoManual ?? 0) > 0) ? 'bg-gray-50 dark:bg-gray-900' : 'bg-yellow-50 dark:bg-yellow-900/20' }}">
                                <span class="text-gray-500 block">Custo Produto</span>
                                @if(($aderindoInfo['custo_produto'] ?? 0) > 0)
                                    <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($custoProduto, 2, ',', '.') }}</span>
                                    @if($aderindoInfo['sku'] ?? null)
                                        <span class="text-[10px] text-gray-400 block">SKU: {{ $aderindoInfo['sku'] }}</span>
                                    @endif
                                @else
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs text-gray-500">R$</span>
                                        <input type="number" step="0.01" wire:model.blur="custoManual"
                                            placeholder="Informar"
                                            class="w-20 px-1 py-0.5 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                    </div>
                                @endif
                            </div>
                            @if($temSubsidio)
                            <div class="p-2 rounded bg-blue-50 dark:bg-blue-900/20">
                                <span class="text-gray-500 block">Participação ML ({{ number_format($meliPercentage, 1) }}%)</span>
                                <span class="font-semibold text-blue-700 dark:text-blue-400">R$ {{ number_format($participacaoMl, 2, ',', '.') }} no desconto</span>
                            </div>
                            @endif
                        </div>
                        @php
                            $comissaoAtual2 = $precoOriginal * ($comissaoPercent / 100);
                            $impostoAtual2 = $precoOriginal * ($impPercent / 100);
                            $custoTotalAtual2 = $frete + $comissaoAtual2 + $impostoAtual2 + $custoProduto;
                            $margemAtual2 = $precoOriginal - $custoTotalAtual2;
                            $margemAtualPercent2 = $precoOriginal > 0 ? ($margemAtual2 / $precoOriginal) * 100 : 0;
                        @endphp
                        <div class="p-2 rounded bg-gray-50 dark:bg-gray-900/50 mb-1 flex items-center gap-3">
                            <span class="text-xs text-gray-500">Margem atual (s/ promo):</span>
                            <span class="text-xs font-bold" style="color: {{ $margemAtualPercent2 >= $margemDesejada ? '#15803d' : ($margemAtualPercent2 >= 0 ? '#a16207' : '#dc2626') }}">R$ {{ number_format($margemAtual2, 2, ',', '.') }} ({{ number_format($margemAtualPercent2, 1) }}%)</span>
                        </div>

                        <div class="flex items-center gap-3 flex-wrap p-2 rounded {{ $margemPercent >= $margemDesejada ? 'bg-green-50 dark:bg-green-900/20' : ($margemPercent >= 0 ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'bg-red-50 dark:bg-red-900/20') }}">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-600 dark:text-gray-400">Preço promo:</span>
                                <span class="text-xs text-gray-500">R$</span>
                                <input type="number" step="0.01" wire:model.blur="aderindoPreco"
                                    class="w-24 px-2 py-1 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500">
                                <x-filament::button size="xs" color="gray" wire:click="$refresh">Calcular</x-filament::button>
                            </div>
                            @if($precoSugerido > 0)
                            <div class="text-xs">
                                <span class="text-gray-500">Sugerido ({{ number_format($margemDesejada, 0) }}%):</span>
                                <span class="font-semibold text-primary-600 dark:text-primary-400">R$ {{ number_format($precoSugerido, 2, ',', '.') }}</span>
                            </div>
                            @endif
                            <div class="text-xs">
                                <span class="text-gray-500">Desconto:</span>
                                <span class="font-semibold">{{ number_format($desconto, 1) }}%</span>
                            </div>
                            <div class="text-xs">
                                <span class="text-gray-500">Margem:</span>
                                <span class="font-bold" style="color: {{ $margemPercent >= $margemDesejada ? '#15803d' : ($margemPercent >= 0 ? '#a16207' : '#dc2626') }}">R$ {{ number_format($margem, 2, ',', '.') }} ({{ number_format($margemPercent, 1) }}%)</span>
                            </div>
                            <div class="ml-auto">
                                <x-filament::button size="sm" wire:click="confirmarAdesao">Confirmar Adesão</x-filament::button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="overflow-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="fi-ta-table w-full table-auto text-start">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">Promoção</th>
                                <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">Tipo</th>
                                <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">Preço Original</th>
                                <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">Preço Promo</th>
                                <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">Rebate ML</th>
                                <th class="px-3 py-2 text-start text-xs font-medium text-gray-500 dark:text-gray-400">Período</th>
                                <th class="px-3 py-2 text-end text-xs font-medium text-gray-500 dark:text-gray-400">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($promocoesDoItem as $promo)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                    <td class="px-3 py-2 text-xs text-gray-900 dark:text-gray-100">{{ $promo['name'] }}</td>
                                    <td class="px-3 py-2">
                                        <span class="text-[10px] text-gray-500">{{ $promo['type'] }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        @php $sc = match($promo['status']) {
                                            'candidate' => 'warning',
                                            'active', 'started' => 'success',
                                            'finished' => 'gray',
                                            default => 'gray'
                                        }; @endphp
                                        <x-filament::badge size="sm" :color="$sc">{{ $promo['status'] }}</x-filament::badge>
                                    </td>
                                    <td class="px-3 py-2 text-end text-xs tabular-nums text-gray-700 dark:text-gray-300">
                                        {{ $promo['original_price'] ? 'R$ ' . number_format($promo['original_price'], 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-end text-xs tabular-nums">
                                        @if($promo['price'])
                                            <span class="font-semibold text-green-600 dark:text-green-400">R$ {{ number_format($promo['price'], 2, ',', '.') }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-end text-xs tabular-nums">
                                        @if($promo['meli_percentage'] > 0)
                                            <span class="text-blue-600 dark:text-blue-400">{{ number_format($promo['meli_percentage'], 1) }}%</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-500">
                                        @if($promo['start_date'])
                                            {{ \Carbon\Carbon::parse($promo['start_date'])->format('d/m') }}
                                            @if($promo['finish_date'])
                                                - {{ \Carbon\Carbon::parse($promo['finish_date'])->format('d/m') }}
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-end">
                                        @if($promo['status'] === 'candidate' || $promo['status'] === 'started')
                                            <button wire:click="iniciarAdesaoDoItem({{ $loop->index }})"
                                                class="text-xs text-primary-600 hover:text-primary-500 font-medium">
                                                Aderir
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif($buscarItemId)
                <p class="text-sm text-gray-400 text-center py-8">Nenhuma promoção encontrada</p>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Digite um MLB ID para buscar promoções disponíveis</p>
            @endif
        </div>
        @endif
    </div>
</x-filament-panels::page>
