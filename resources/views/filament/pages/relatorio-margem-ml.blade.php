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
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
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
                                    <a href="https://www.mercadolivre.com.br/p/{{ $item->mlb_id }}" target="_blank"
                                       class="text-sm font-mono text-blue-600 dark:text-blue-400 hover:underline">{{ $item->mlb_id }}</a>
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $item->listing_type === 'gold_pro' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' }}">
                                        {{ $tipoLabel }}
                                    </span>
                                    @if($item->is_catalog_listing)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300" title="{{ $item->catalog_product_id }}">
                                            📦 Catálogo {{ $item->catalog_product_id }}
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
                            <div class="divide-y divide-gray-50 dark:divide-gray-700">
                                @foreach($item->promocoes as $promo)
                                    @php
                                        $promoMargemCor = match(true) {
                                            !isset($promo['margem_pct']) => 'text-gray-500',
                                            $promo['margem_pct'] < 15 => 'text-red-600 dark:text-red-400',
                                            $promo['margem_pct'] < 25 => 'text-yellow-600 dark:text-yellow-400',
                                            default => 'text-green-600 dark:text-green-400',
                                        };
                                    @endphp
                                    <div class="px-4 py-2.5 flex items-center justify-between gap-4 text-sm">
                                        <div class="flex-1 min-w-0 flex items-center gap-2 flex-wrap">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $promo['nome'] }}</span>
                                            @if(!empty($promo['tipo']))
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">{{ $promo['tipo'] }}</span>
                                            @endif
                                            @if(!empty($promo['status']))
                                                <span class="text-xs px-1.5 py-0.5 rounded {{ $promo['status'] === 'started' ? 'bg-green-200 dark:bg-green-800 text-green-800 dark:text-green-200' : 'bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200' }}">
                                                    {{ $promo['status'] }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-4 shrink-0">
                                            @if($promo['preco'])
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    R$ {{ number_format($promo['preco'], 2, ',', '.') }}
                                                </span>
                                            @endif
                                            @if($promo['meli_pct'] > 0)
                                                <span class="text-sm font-medium text-blue-500 dark:text-blue-400" title="Rebate ML">
                                                    ML: {{ $promo['meli_pct'] }}%
                                                </span>
                                            @endif
                                            @if($promo['seller_pct'] > 0)
                                                <span class="text-sm font-medium text-orange-500 dark:text-orange-400" title="Desconto Seller">
                                                    Seller: {{ $promo['seller_pct'] }}%
                                                </span>
                                            @endif
                                            @if(isset($promo['margem_pct']))
                                                <span class="text-sm font-bold {{ $promoMargemCor }}">
                                                    {{ number_format($promo['margem_pct'], 1) }}%
                                                </span>
                                            @endif
                                        </div>
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
