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
            </div>
        </div>

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
                                <span class="ml-auto shrink-0 text-[10px] text-gray-400">{{ $promo['type'] ?? '' }}</span>
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

                    {{-- Painel de Adesão com Simulação --}}
                    @if($aderindoItemId && $aderindoInfo)
                        @php
                            $precoOriginal = $aderindoInfo['original_price'] ?? 0;
                            $frete = $aderindoInfo['frete'] ?? 0;
                            $comissaoPercent = $aderindoInfo['comissao_percent'] ?? 11.5;
                            $impPercent = $aderindoInfo['imposto_percent'] ?? 17.8;
                            $custoProduto = $aderindoInfo['custo_produto'] ?? 0;
                            $temSubsidio = $aderindoInfo['tem_subsidio'] ?? false;
                            $precoPromo = $aderindoPreco ?? 0;
                            $comissao = $precoPromo * ($comissaoPercent / 100);
                            $imposto = $precoPromo * ($impPercent / 100);
                            // Subsídio estimado: diferença entre preço original e preço promo (o ML cobre parte)
                            $subsidioEstimado = $temSubsidio && $precoOriginal > $precoPromo ? $precoOriginal - $precoPromo : 0;
                            $custoTotal = $frete + $comissao + $imposto + $custoProduto;
                            $margem = $precoPromo - $custoTotal;
                            $margemPercent = $precoPromo > 0 ? ($margem / $precoPromo) * 100 : 0;
                            $desconto = $precoOriginal > 0 ? (($precoOriginal - $precoPromo) / $precoOriginal) * 100 : 0;
                        @endphp
                        <div class="mb-4 p-4 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-primary-500/30 shadow-sm">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $aderindoInfo['title'] }}</p>
                                    <p class="text-xs text-gray-500 font-mono">{{ $aderindoItemId }}</p>
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
                                <div class="p-2 rounded {{ $custoProduto > 0 ? 'bg-gray-50 dark:bg-gray-900' : 'bg-yellow-50 dark:bg-yellow-900/20' }}">
                                    <span class="text-gray-500 block">Custo Produto</span>
                                    @if($custoProduto > 0)
                                        <span class="font-semibold text-gray-900 dark:text-white">R$ {{ number_format($custoProduto, 2, ',', '.') }}</span>
                                        @if($aderindoInfo['sku'] ?? null)
                                            <span class="text-[10px] text-gray-400 block">SKU: {{ $aderindoInfo['sku'] }}</span>
                                        @endif
                                    @else
                                        <span class="font-semibold text-yellow-600 dark:text-yellow-400">Sem custo no Bling</span>
                                        @if($aderindoInfo['sku'] ?? null)
                                            <span class="text-[10px] text-gray-400 block">SKU: {{ $aderindoInfo['sku'] }}</span>
                                        @else
                                            <span class="text-[10px] text-gray-400 block">SKU não encontrado</span>
                                        @endif
                                    @endif
                                </div>
                                @if($temSubsidio)
                                <div class="p-2 rounded bg-blue-50 dark:bg-blue-900/20">
                                    <span class="text-gray-500 block">Subsídio ML (est.)</span>
                                    <span class="font-semibold text-blue-700 dark:text-blue-400">~R$ {{ number_format($subsidioEstimado, 2, ',', '.') }}</span>
                                    <span class="text-[10px] text-gray-400 block">{{ $aderindoInfo['promo_type'] }}</span>
                                </div>
                                @endif
                            </div>

                            <div class="flex items-center gap-3 flex-wrap p-2 rounded {{ $margemPercent >= 15 ? 'bg-green-50 dark:bg-green-900/20' : ($margemPercent >= 0 ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'bg-red-50 dark:bg-red-900/20') }}">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-600 dark:text-gray-400">Preço promo:</span>
                                    <span class="text-xs text-gray-500">R$</span>
                                    <input type="number" step="0.01" wire:model.blur="aderindoPreco"
                                        class="w-24 px-2 py-1 text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500">
                                    <x-filament::button size="xs" color="gray" wire:click="$refresh">
                                        Calcular
                                    </x-filament::button>
                                </div>
                                <div class="text-xs">
                                    <span class="text-gray-500">Desconto:</span>
                                    <span class="font-semibold">{{ number_format($desconto, 1) }}%</span>
                                </div>
                                <div class="text-xs">
                                    <span class="text-gray-500">Margem:</span>
                                    <span @class([
                                        'font-bold',
                                        'text-green-700 dark:text-green-400' => $margemPercent >= 15,
                                        'text-yellow-700 dark:text-yellow-400' => $margemPercent >= 0 && $margemPercent < 15,
                                        'text-red-700 dark:text-red-400' => $margemPercent < 0,
                                    ])>
                                        R$ {{ number_format($margem, 2, ',', '.') }} ({{ number_format($margemPercent, 1) }}%)
                                    </span>
                                </div>
                                <div class="ml-auto">
                                    <x-filament::button size="sm" wire:click="confirmarAdesao">
                                        Confirmar Adesão
                                    </x-filament::button>
                                </div>
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">* Margem = Preço promo - Frete - Comissão - Imposto{{ $custoProduto > 0 ? ' - Custo Produto' : ' (sem custo do produto)' }}</p>
                        </div>
                    @elseif($aderindoItemId && !$aderindoInfo)
                        <div class="mb-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 flex items-center gap-2">
                            <x-filament::loading-indicator class="h-4 w-4" />
                            <span class="text-sm text-gray-500">Buscando dados do item...</span>
                        </div>
                    @endif

                    @if($selectedPromotion)
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $selectedPromotion['name'] ?? 'Promoção' }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ count($items) }}/{{ $totalItems }} itens · {{ $selectedPromotion['type'] ?? '' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
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
                                                        <button wire:click="iniciarAdesao('{{ $item['id'] }}')"
                                                            class="text-xs text-primary-600 hover:text-primary-500 font-medium">
                                                            Aderir
                                                        </button>
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
    </div>
</x-filament-panels::page>
