<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Header --}}
        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
            <div>
                @if($this->geradoEm)
                    Última atualização: <span class="font-semibold">{{ $this->geradoEm }}</span>
                @endif
            </div>
            <div>{{ count($this->familias) }} família(s)</div>
        </div>

        {{-- Busca em tempo real --}}
        <div class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">🔍 Buscar família em tempo real (API)</div>
            <div class="flex gap-2 items-end">
                <input type="text" wire:model="buscaFamiliaRealtime" placeholder="MLB ou MLBU..."
                    class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2">
                <select wire:model="filtroAccount" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2">
                    <option value="">Primary</option>
                    <option value="primary">Primary</option>
                    <option value="secondary">Secondary</option>
                </select>
                <button wire:click="buscarFamiliaAgora" wire:loading.attr="disabled"
                    class="px-4 py-2 text-sm font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700">
                    <span wire:loading.remove wire:target="buscarFamiliaAgora">Buscar</span>
                    <span wire:loading wire:target="buscarFamiliaAgora">Buscando...</span>
                </button>
                @if($resultadoRealtime)
                    <button wire:click="limparRealtime" class="px-3 py-2 text-sm rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">✖</button>
                @endif
            </div>
        </div>

        {{-- Resultado em tempo real --}}
        @if($resultadoRealtime)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-gray-100 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">📦 {{ $resultadoRealtime['family_name'] }}</span>
                    <span class="ml-2 text-xs text-gray-600 dark:text-gray-400">Family: {{ $resultadoRealtime['family_id'] }}</span>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">MLB</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">Tipo</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">SKU / Cor</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Preço</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Menor Promo</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Custo</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Comissão</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Frete</th>
                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-700 dark:text-gray-200">Est</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">Logística</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resultadoRealtime['ups'] as $upIdx => $up)
                            @foreach($up['items'] as $mlb)
                                @php
                                    $statusIcon = match($mlb['status']) { 'active' => '🟢', 'paused' => '🟡', 'closed' => '🔴', default => '⚪' };
                                    $tipoLabel = match($mlb['listing_type']) { 'gold_pro' => 'Premium', 'gold_special' => 'Clássico', default => $mlb['listing_type'] };
                                    $tipoBg = $mlb['listing_type'] === 'gold_pro' ? 'background:#7c3aed;color:#fff;' : 'background:#3b82f6;color:#fff;';
                                    $isFirst = $loop->first;
                                    $borderTop = $isFirst && $upIdx > 0 ? 'border-top:3px solid #3b82f6;' : '';
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-700/30" style="{{ $borderTop }}">
                                    <td class="px-3 py-2">{{ $statusIcon }}</td>
                                    <td class="px-3 py-2 font-mono text-gray-900 dark:text-white">
                                        {{ $mlb['mlb_id'] }}
                                        @if($mlb['catalog_listing'])
                                            <span class="ml-1 px-1 py-0.5 rounded text-[9px] font-bold" style="background:#ea580c;color:#fff;">CAT</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium" style="{{ $tipoBg }}">{{ $tipoLabel }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-900 dark:text-white">
                                        @if($isFirst)
                                            <span class="font-medium">{{ $up['sku'] }}</span>
                                            <span class="text-gray-500 dark:text-gray-400 ml-1">{{ $up['cor'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">R$ {{ number_format($mlb['price'], 2, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @php $menorPromo = !empty($mlb['promocoes']) ? collect($mlb['promocoes'])->min('preco') : null; @endphp
                                        @if($menorPromo)
                                            <span class="font-semibold" style="color:#f59e0b;">R$ {{ number_format($menorPromo, 2, ',', '.') }}</span>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $mlb['custo'] > 0 ? 'R$ ' . number_format($mlb['custo'], 2, ',', '.') : '—' }}</td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                                        @if($mlb['comissao_pct'] > 0)
                                            {{ number_format($mlb['comissao_pct'], 1) }}%
                                            <div class="text-[10px] text-gray-500">R$ {{ number_format($mlb['comissao_valor'], 2, ',', '.') }}</div>
                                        @else
                                            <span class="text-gray-500">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $mlb['frete'] > 0 ? 'R$ ' . number_format($mlb['frete'], 2, ',', '.') : '—' }}</td>
                                    <td class="px-3 py-2 text-center text-gray-900 dark:text-white">{{ $mlb['estoque'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $mlb['logistic_type'] }}</td>
                                </tr>
                                @if(!empty($mlb['promocoes']))
                                    <tr class="bg-gray-100 dark:bg-gray-900/70">
                                        <td colspan="11" class="px-6 py-1.5">
                                            <div class="flex flex-wrap gap-3 text-[11px]">
                                                @foreach($mlb['promocoes'] as $promo)
                                                    <span class="text-gray-800 dark:text-gray-200">
                                                        🏷️ {{ $promo['nome'] }}
                                                        <span class="font-semibold" style="color:#f59e0b;">R$ {{ number_format($promo['preco'], 2, ',', '.') }}</span>
                                                        @if($promo['meli_pct'] > 0)
                                                            <span style="color:#60a5fa;">ML:{{ $promo['meli_pct'] }}%</span>
                                                        @endif
                                                        @if($promo['seller_pct'] > 0)
                                                            <span style="color:#fb923c;">Seller:{{ $promo['seller_pct'] }}%</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Filtros do relatório --}}
        <div class="flex flex-wrap gap-3 p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 items-end">
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Busca</label>
                <input type="text" wire:model.live.debounce.300ms="busca" placeholder="MLB, SKU, título, família..."
                    class="w-full mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-1.5">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Conta</label>
                <select wire:model.live="filtroAccount" class="mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-1.5">
                    <option value="">Todas</option>
                    <option value="primary">Primary (Mobília)</option>
                    <option value="secondary">Secondary (HES)</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Status</label>
                <select wire:model.live="filtroStatus" class="mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-1.5">
                    <option value="">Todos</option>
                    <option value="active">🟢 Ativo</option>
                    <option value="paused">🟡 Pausado</option>
                    <option value="closed">🔴 Fechado</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Catálogo</label>
                <select wire:model.live="filtroCatalogo" class="mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-1.5">
                    <option value="">Todos</option>
                    <option value="sim">Com catálogo</option>
                    <option value="nao">Sem catálogo</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Margem</label>
                <select wire:model.live="filtroMargem" class="mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-1.5">
                    <option value="">Todas</option>
                    <option value="negativa">🔴 Negativa (< 0%)</option>
                    <option value="baixa">🟡 Baixa (0-15%)</option>
                    <option value="media">🟠 Média (15-30%)</option>
                    <option value="boa">🟢 Boa (≥30%)</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Ordenar</label>
                <select wire:model.live="ordenar" class="mt-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-1.5">
                    <option value="family_name">Nome Família</option>
                    <option value="margem_asc">Pior margem primeiro</option>
                    <option value="margem_desc">Melhor margem primeiro</option>
                </select>
            </div>
        </div>

        {{-- Famílias --}}
        <div class="space-y-4">
            @forelse($this->familias as $familyKey => $familia)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Header da família --}}
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">📦 {{ $familia['family_name'] }}</span>
                                @if($familia['family_id'])
                                    <span class="ml-2 text-[10px] text-gray-500 font-mono">Family: {{ $familia['family_id'] }}</span>
                                @endif
                            </div>
                            <span class="text-xs text-gray-500">{{ count($familia['ups']) }} variação(ões)</span>
                        </div>
                    </div>

                    {{-- Tabela --}}
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">MLB</th>
                                <th class="px-3 py-2 text-left">Tipo</th>
                                <th class="px-3 py-2 text-left">SKU / Cor</th>
                                <th class="px-3 py-2 text-right">Preço</th>
                                <th class="px-3 py-2 text-right">Custo</th>
                                <th class="px-3 py-2 text-right">Frete</th>
                                <th class="px-3 py-2 text-center">Com%</th>
                                <th class="px-3 py-2 text-center">Est</th>
                                <th class="px-3 py-2 text-right font-semibold">Margem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($familia['ups'] as $upIdx => $up)
                                @foreach($up['items'] as $item)
                                    @php
                                        $margemCor = match(true) {
                                            $item->margem_pct < 0 => '#ef4444',
                                            $item->margem_pct < 15 => '#f97316',
                                            $item->margem_pct < 25 => '#eab308',
                                            default => '#22c55e',
                                        };
                                        $statusIcon = match($item->status_ml) { 'active' => '🟢', 'paused' => '🟡', 'closed' => '🔴', default => '⚪' };
                                        $tipoLabel = match($item->listing_type) { 'gold_pro' => 'Premium', 'gold_special' => 'Clássico', default => $item->listing_type };
                                        $tipoBg = $item->listing_type === 'gold_pro' ? 'background:#7c3aed;color:#fff;' : 'background:#3b82f6;color:#fff;';
                                        $isFirst = $loop->first;
                                        $borderTop = $isFirst && $upIdx > 0 ? 'border-top:3px solid #3b82f6;' : '';
                                    @endphp
                                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/30" style="{{ $borderTop }}">
                                        <td class="px-3 py-2">{{ $statusIcon }}</td>
                                        <td class="px-3 py-2">
                                            <a href="https://www.mercadolivre.com.br/anuncios/lista/promos?page=1&search={{ $item->mlb_id }}" target="_blank"
                                               class="font-mono text-blue-600 dark:text-blue-400 hover:underline">{{ $item->mlb_id }}</a>
                                            @if($item->is_catalog_listing)
                                                <span class="ml-1 px-1 py-0.5 rounded text-[9px] font-bold" style="background:#ea580c;color:#fff;">CAT</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium" style="{{ $tipoBg }}">{{ $tipoLabel }}</span>
                                        </td>
                                        <td class="px-3 py-2">
                                            @if($isFirst)
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $up['sku'] ?? '—' }}</span>
                                                @if($up['cor'])
                                                    <span class="text-gray-500 ml-1">{{ $up['cor'] }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">R$ {{ number_format($item->preco_venda, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">R$ {{ number_format($item->custo_produto, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">R$ {{ number_format($item->frete, 2, ',', '.') }}</td>
                                        <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ number_format($item->comissao_pct, 1) }}%</td>
                                        <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ $item->estoque }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <span class="font-bold" style="color:{{ $margemCor }}">{{ number_format($item->margem_pct, 1) }}%</span>
                                            <div class="text-[10px]" style="color:{{ $margemCor }}">R$ {{ number_format($item->margem_valor, 2, ',', '.') }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-xl">
                    Nenhuma família encontrada.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
