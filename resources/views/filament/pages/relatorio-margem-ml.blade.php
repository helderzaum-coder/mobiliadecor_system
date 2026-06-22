<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                @if($this->geradoEm)
                    Última atualização: <span class="font-semibold">{{ $this->geradoEm }}</span>
                @else
                    <span class="text-yellow-600">Nenhum relatório gerado ainda. Execute: <code>php artisan ml:relatorio-margem</code></span>
                @endif
            </div>
            <div class="text-sm text-gray-500">
                {{ $this->itens->count() }} itens
            </div>
        </div>

        {{-- Filtros --}}
        <div class="flex flex-wrap gap-3 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 items-end">
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Busca</label>
                <input type="text" wire:model.live.debounce.300ms="busca" placeholder="MLB, SKU ou título..."
                    class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Conta</label>
                <select wire:model.live="filtroAccount" class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Todas</option>
                    <option value="primary">Primary (Mobília Decor)</option>
                    <option value="secondary">Secondary (HES Móveis)</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Catálogo</label>
                <select wire:model.live="filtroCatalogo" class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos</option>
                    <option value="sim">Com catálogo</option>
                    <option value="nao">Sem catálogo</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Tipo Anúncio</label>
                <select wire:model.live="filtroListingType" class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Todos</option>
                    <option value="gold_pro">Premium</option>
                    <option value="gold_special">Clássico</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Ordenar</label>
                <select wire:model.live="ordenar" class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="margem_pct_asc">Margem ↑ (menor primeiro)</option>
                    <option value="margem_pct_desc">Margem ↓ (maior primeiro)</option>
                    <option value="margem_promo_asc">Margem Promo ↑ (pior primeiro)</option>
                    <option value="preco_desc">Preço ↓</option>
                    <option value="preco_asc">Preço ↑</option>
                </select>
            </div>
        </div>

        {{-- Busca em tempo real por Family ID --}}
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">🔍 Consulta em Tempo Real (Family ID / Catalog Product ID)</label>
                    <input type="text" wire:model="familyIdBusca" placeholder="Ex: MLA12345678 ou family_id..."
                        class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        wire:keydown.enter="buscarPorFamily">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Conta</label>
                    <select wire:model="familyAccountBusca" class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="primary">Primary</option>
                        <option value="secondary">Secondary</option>
                    </select>
                </div>
                <button wire:click="buscarPorFamily" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    <span wire:loading.remove wire:target="buscarPorFamily">Buscar</span>
                    <span wire:loading wire:target="buscarPorFamily">Buscando...</span>
                </button>
                @if(!empty($this->familyResultados))
                <button wire:click="limparFamily" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300">
                    Limpar
                </button>
                @endif
            </div>

            {{-- Resultados da busca em tempo real --}}
            @if(!empty($this->familyResultados))
            <div class="mt-4 space-y-3">
                <div class="text-xs font-semibold text-green-600 dark:text-green-400">
                    ✅ {{ count($this->familyResultados) }} itens encontrados (tempo real)
                </div>
                @foreach($this->familyResultados as $fr)
                    @php
                        $frMargemCor = match(true) {
                            $fr['margem_pct'] < 15 => 'text-red-600 dark:text-red-400',
                            $fr['margem_pct'] < 25 => 'text-yellow-600 dark:text-yellow-400',
                            default => 'text-green-600 dark:text-green-400',
                        };
                        $frTipoLabel = match($fr['listing_type']) {
                            'gold_pro' => 'Premium',
                            'gold_special' => 'Clássico',
                            default => $fr['listing_type'],
                        };
                    @endphp
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="https://www.mercadolivre.com.br/anuncios/lista/promos?page=1&search={{ $fr['mlb_id'] }}" target="_blank"
                                           class="text-sm font-mono text-blue-600 dark:text-blue-400 hover:underline">{{ $fr['mlb_id'] }}</a>
                                        <span class="px-2 py-0.5 text-xs rounded-full {{ $fr['listing_type'] === 'gold_pro' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' }}">
                                            {{ $frTipoLabel }}
                                        </span>
                                        <span class="text-xs text-gray-400">SKU: {{ $fr['sku'] ?? '—' }}</span>
                                        @if($fr['free_shipping'])
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-900 text-green-300">Frete Grátis</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 truncate">{{ $fr['titulo'] }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-lg font-bold {{ $frMargemCor }}">{{ number_format($fr['margem_pct'], 1) }}%</div>
                                    <div class="text-xs text-gray-500">R$ {{ number_format($fr['margem_valor'], 2, ',', '.') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center divide-x divide-gray-200 dark:divide-gray-700 overflow-x-auto text-center">
                            <div class="flex-1 px-3 py-2"><div class="text-[10px] text-gray-500">Preço</div><div class="text-sm font-semibold">R$ {{ number_format($fr['preco_venda'], 2, ',', '.') }}</div></div>
                            <div class="flex-1 px-3 py-2"><div class="text-[10px] text-gray-500">Custo</div><div class="text-sm font-semibold">R$ {{ number_format($fr['custo_produto'], 2, ',', '.') }}</div></div>
                            <div class="flex-1 px-3 py-2"><div class="text-[10px] text-gray-500">Comissão</div><div class="text-sm font-semibold">{{ number_format($fr['comissao_pct'], 1) }}%</div><div class="text-[10px] text-gray-400">R$ {{ number_format($fr['comissao_valor'], 2, ',', '.') }}</div></div>
                            <div class="flex-1 px-3 py-2"><div class="text-[10px] text-gray-500">Frete</div><div class="text-sm font-semibold">R$ {{ number_format($fr['frete'], 2, ',', '.') }}</div></div>
                            <div class="flex-1 px-3 py-2"><div class="text-[10px] text-gray-500">Imposto</div><div class="text-sm font-semibold">{{ number_format($fr['imposto_pct'], 1) }}%</div><div class="text-[10px] text-gray-400">R$ {{ number_format($fr['imposto_valor'], 2, ',', '.') }}</div></div>
                            <div class="flex-1 px-3 py-2"><div class="text-[10px] text-gray-500">Antecipação</div><div class="text-sm font-semibold">R$ {{ number_format($fr['antecipacao_valor'], 2, ',', '.') }}</div></div>
                            <div class="flex-1 px-3 py-2"><div class="text-[10px] text-gray-500">Estoque</div><div class="text-sm font-semibold">{{ $fr['estoque'] }}</div></div>
                        </div>
                        @if(!empty($fr['promocoes']))
                        <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                            @foreach($fr['promocoes'] as $fp)
                                @php
                                    $fpMargemCor = match(true) {
                                        $fp['margem_pct'] < 15 => 'text-red-500',
                                        $fp['margem_pct'] < 25 => 'text-yellow-500',
                                        default => 'text-green-500',
                                    };
                                @endphp
                                <div class="rounded-lg border border-gray-600 p-2 bg-gray-800">
                                    <div class="text-xs font-semibold text-gray-100 truncate">{{ $fp['nome'] }}</div>
                                    <div class="flex items-center gap-1 mt-1">
                                        <span class="text-[10px] px-1 py-0.5 rounded bg-indigo-900 text-indigo-300">{{ $fp['tipo'] }}</span>
                                        <span class="text-[10px] px-1 py-0.5 rounded {{ $fp['status'] === 'started' ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-yellow-300' }}">{{ $fp['status'] }}</span>
                                        @if(($fp['meli_pct'] ?? 0) > 0)
                                            <span class="text-[10px] px-1 py-0.5 rounded bg-blue-900 text-blue-300">+{{ $fp['meli_pct'] }}%</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-center">
                                        <div class="text-[10px] text-gray-500">R$ {{ number_format($fp['preco'], 2, ',', '.') }}</div>
                                        <div class="text-sm font-bold {{ $fpMargemCor }}">{{ number_format($fp['margem_pct'], 1) }}% <span class="text-xs">(R$ {{ number_format($fp['margem_valor'], 2, ',', '.') }})</span></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Tabela --}}
        <div class="space-y-3">
            @forelse($this->itens as $item)
                @php
                    $margemCor = match(true) {
                        $item->margem_pct < 15 => 'text-red-600 dark:text-red-400',
                        $item->margem_pct < 25 => 'text-yellow-600 dark:text-yellow-400',
                        default => 'text-green-600 dark:text-green-400',
                    };
                    $tipoLabel = match($item->listing_type) {
                        'gold_pro' => 'Premium',
                        'gold_special' => 'Clássico',
                        default => $item->listing_type,
                    };
                @endphp
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Cabeçalho do item --}}
                    <div class="p-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="https://www.mercadolivre.com.br/anuncios/lista/promos?page=1&search={{ $item->mlb_id }}" target="_blank"
                                       class="text-sm font-mono text-blue-600 dark:text-blue-400 hover:underline">{{ $item->mlb_id }}</a>
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $item->listing_type === 'gold_pro' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' }}">
                                        {{ $tipoLabel }}
                                    </span>
                                    @if($item->is_catalog_listing)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300">
                                            📦 Item de Catálogo
                                            @if($item->item_relation_id)
                                                → Pai: {{ $item->item_relation_id }}
                                            @endif
                                        </span>
                                    @elseif($item->catalog_product_id)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-cyan-100 text-cyan-700 dark:bg-cyan-900 dark:text-cyan-300">
                                            🔗 Vinculado: {{ $item->catalog_product_id }}
                                            @if($item->item_relation_id)
                                                ({{ $item->item_relation_id }})
                                            @endif
                                        </span>
                                    @endif
                                    <span class="text-xs text-gray-400">SKU: {{ $item->sku ?? '—' }}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 truncate">{{ $item->titulo }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-lg font-bold {{ $margemCor }}">{{ number_format($item->margem_pct, 1) }}%</div>
                                <div class="text-xs text-gray-500">R$ {{ number_format($item->margem_valor, 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Dados financeiros --}}
                    <div class="flex items-center divide-x divide-gray-200 dark:divide-gray-700 border-t border-b border-gray-100 dark:border-gray-700 overflow-x-auto">
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Preço</div>
                            <div class="text-sm font-semibold">R$ {{ number_format($item->preco_venda, 2, ',', '.') }}</div>
                        </div>
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Custo</div>
                            <div class="text-sm font-semibold">R$ {{ number_format($item->custo_produto, 2, ',', '.') }}</div>
                        </div>
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Comissão</div>
                            <div class="text-sm font-semibold">{{ number_format($item->comissao_pct, 1) }}%</div>
                            <div class="text-[10px] text-gray-400">R$ {{ number_format($item->comissao_valor, 2, ',', '.') }}</div>
                        </div>
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Frete</div>
                            <div class="text-sm font-semibold">R$ {{ number_format($item->frete, 2, ',', '.') }}</div>
                        </div>
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Imposto</div>
                            <div class="text-sm font-semibold">{{ number_format($item->imposto_pct, 1) }}%</div>
                            <div class="text-[10px] text-gray-400">R$ {{ number_format($item->imposto_valor, 2, ',', '.') }}</div>
                        </div>
                        @php $antecPct = $this->antecipacao_pct; @endphp
                        @if($antecPct > 0)
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Antecipação</div>
                            <div class="text-sm font-semibold">{{ number_format($antecPct, 1) }}%</div>
                            <div class="text-[10px] text-gray-400">R$ {{ number_format($item->preco_venda * $antecPct / 100, 2, ',', '.') }}</div>
                        </div>
                        @endif
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Estoque</div>
                            <div class="text-sm font-semibold">{{ $item->estoque }}</div>
                        </div>
                        <div class="flex-1 px-3 py-2 text-center min-w-0">
                            <div class="text-[10px] text-gray-500 uppercase">Conta</div>
                            <div class="text-sm font-semibold">{{ $item->account_key === 'primary' ? 'Mobília' : 'HES' }}</div>
                        </div>
                    </div>

                    {{-- Promoções --}}
                    @if(!empty($item->promocoes))
                        <div class="border-t border-gray-100 dark:border-gray-700">
                            <div class="px-4 py-2 bg-gray-100 dark:bg-gray-900">
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    🏷️ PROMOÇÕES ({{ count($item->promocoes) }})
                                </span>
                            </div>
                            <div class="px-4 py-3" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                                @foreach($item->promocoes as $promo)
                                    @php
                                        $pp = (float) ($promo['preco'] ?? 0);
                                        $promoComissao = $pp > 0 ? round($pp * $item->comissao_pct / 100, 2) : 0;
                                        $promoFrete = (float) $item->frete;
                                        $promoImposto = $pp > 0 ? round($pp * $item->imposto_pct / 100, 2) : 0;
                                        $promoAntec = $pp > 0 ? round($pp * $antecPct / 100, 2) : 0;
                                        $promoMargemCor = match(true) {
                                            !isset($promo['margem_pct']) => 'text-gray-400',
                                            $promo['margem_pct'] < 15 => 'text-red-500',
                                            $promo['margem_pct'] < 25 => 'text-yellow-500',
                                            default => 'text-green-500',
                                        };
                                        $statusCor = match($promo['status'] ?? '') {
                                            'started' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                            'candidate' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                            default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                        };
                                    @endphp
                                    <div class="rounded-lg border border-gray-600 p-3 bg-gray-800">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-semibold text-gray-100 truncate">{{ $promo['nome'] }}</span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded {{ $statusCor }}">{{ $promo['status'] ?? '' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-900 text-indigo-300">{{ $promo['tipo'] ?? '' }}</span>
                                            @if(($promo['meli_pct'] ?? 0) > 0)
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-900 text-blue-300">Rebate {{ $promo['meli_pct'] }}%</span>
                                            @endif
                                        </div>

                                        @if($pp > 0)
                                        <div class="text-center mb-2 py-1.5 rounded bg-gray-900 border border-gray-700">
                                            <div class="text-[10px] text-gray-500">Preço Sugerido</div>
                                            <div class="text-sm font-bold text-white">R$ {{ number_format($pp, 2, ',', '.') }}</div>
                                        </div>

                                        <div class="space-y-0.5 text-[10px] text-gray-400 mb-2">
                                            <div class="flex justify-between">
                                                <span>Comissão ({{ number_format($item->comissao_pct, 1) }}%)</span>
                                                <span class="text-red-400">-R$ {{ number_format($promoComissao, 2, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Frete</span>
                                                <span class="text-red-400">-R$ {{ number_format($promoFrete, 2, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Imposto ({{ number_format($item->imposto_pct, 1) }}%)</span>
                                                <span class="text-red-400">-R$ {{ number_format($promoImposto, 2, ',', '.') }}</span>
                                            </div>
                                            @if($promoAntec > 0)
                                            <div class="flex justify-between">
                                                <span>Antecipação ({{ number_format($antecPct, 1) }}%)</span>
                                                <span class="text-red-400">-R$ {{ number_format($promoAntec, 2, ',', '.') }}</span>
                                            </div>
                                            @endif
                                            <div class="flex justify-between">
                                                <span>Custo</span>
                                                <span class="text-red-400">-R$ {{ number_format($item->custo_produto, 2, ',', '.') }}</span>
                                            </div>
                                            @if(($promo['meli_pct'] ?? 0) > 0)
                                            <div class="flex justify-between">
                                                <span>Rebate ({{ $promo['meli_pct'] }}%)</span>
                                                <span class="text-green-400">+R$ {{ number_format($pp * (float)($promo['meli_pct'] ?? 0) / 100, 2, ',', '.') }}</span>
                                            </div>
                                            @endif
                                        </div>

                                        <div class="text-center py-1.5 rounded {{ ($promo['margem_pct'] ?? 0) >= 15 ? 'bg-green-900/30' : (($promo['margem_pct'] ?? 0) >= 0 ? 'bg-yellow-900/30' : 'bg-red-900/30') }}">
                                            <div class="text-[10px] text-gray-500">Margem</div>
                                            <div class="text-sm font-bold {{ $promoMargemCor }}">
                                                R$ {{ number_format($promo['margem_valor'] ?? 0, 2, ',', '.') }}
                                                <span class="text-xs">({{ number_format($promo['margem_pct'] ?? 0, 1) }}%)</span>
                                            </div>
                                        </div>
                                        @else
                                        <div class="text-center py-2 text-xs text-gray-500">Preço não informado</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-xl">
                    Nenhum item encontrado. Execute o relatório primeiro:<br>
                    <code class="mt-2 inline-block px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm">php artisan ml:relatorio-margem --limit=5</code>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
