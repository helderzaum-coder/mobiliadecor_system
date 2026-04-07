<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    {{-- Resumo --}}
    @php $totais = $this->totais; @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-4">
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ $totais['qtd'] }}</div>
            <div class="text-xs text-gray-500">Vendas</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-gray-800 dark:text-white">R$ {{ number_format($totais['total'], 2, ',', '.') }}</div>
            <div class="text-xs text-gray-500">Faturamento</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold {{ $totais['lucro'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                R$ {{ number_format($totais['lucro'], 2, ',', '.') }}
            </div>
            <div class="text-xs text-gray-500">Lucro Total ({{ $totais['margem'] }}%)</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-green-600">{{ $totais['com_lucro'] }}</div>
            <div class="text-xs text-gray-500">Com Lucro</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 p-4 shadow text-center">
            <div class="text-2xl font-bold text-red-600">{{ $totais['com_prejuizo'] }}</div>
            <div class="text-xs text-gray-500">Com Prejuízo</div>
        </div>
    </div>

    {{-- Cards de Vendas --}}
    <div class="mt-6 space-y-3">
        @forelse($this->vendas as $venda)
            @php
                $lucro = (float) $venda->margem_venda_total;
                $margemPct = (float) $venda->margem_contribuicao;
                $margemFrete = (float) $venda->margem_frete;
                $margemProd = (float) $venda->margem_produto;
                $custoFrete = (float) $venda->valor_frete_transportadora;
                $freteCliente = (float) $venda->valor_frete_cliente;
                $comissao = (float) $venda->comissao;
                $imposto = (float) $venda->valor_imposto;
                $custoProd = (float) $venda->custo_produtos;
                $totalProd = (float) $venda->total_produtos;
                $total = (float) $venda->valor_total_venda;
                $subsidio = (float) $venda->subsidio_pix;

                $borderColor = $lucro >= 0 ? 'border-green-500' : 'border-red-500';
                $lucroBg = $lucro >= 0 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20';
                $lucroColor = $lucro >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400';

                // Alertas
                $alertas = [];
                if ($custoFrete <= 0 && $freteCliente > 0) $alertas[] = 'Frete não cotado';
                if ($custoProd <= 0) $alertas[] = 'Sem custo de produto';
                if ($imposto <= 0) $alertas[] = 'Sem imposto';

                $conta = $venda->bling_account === 'primary' ? 'Mobilia' : 'HES';
                $canal = $venda->canal?->nome_canal ?? '-';
            @endphp

            <div class="rounded-xl bg-white dark:bg-gray-800 shadow border-l-4 {{ $borderColor }} p-4">
                {{-- Header --}}
                <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-bold text-gray-800 dark:text-white">
                            #{{ $venda->numero_pedido_canal }}
                        </span>
                        <span style="background:#4b5563;color:#e5e7eb;padding:2px 8px;border-radius:4px;font-size:11px;">
                            {{ $conta }}
                        </span>
                        <span style="background:#2563eb;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">
                            {{ $canal }}
                        </span>
                        @if($venda->ml_tipo_frete)
                            <span style="background:#7c3aed;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">
                                {{ $venda->ml_tipo_frete }}
                            </span>
                        @endif
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $venda->data_venda?->format('d/m/Y') }}</span>
                    </div>
                </div>
                {{-- Cliente --}}
                <div class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                    {{ $venda->cliente_nome }}
                    @if($venda->cliente_documento)
                        <span class="text-gray-400 dark:text-gray-500">·</span>
                        <span style="cursor:pointer;text-decoration:underline dotted;" title="Clique para copiar"
                            onclick="navigator.clipboard.writeText('{{ $venda->cliente_documento }}').then(()=>{this.innerText='Copiado!';setTimeout(()=>this.innerText='{{ $venda->cliente_documento }}',1500)})">
                            {{ $venda->cliente_documento }}
                        </span>
                    @endif
                </div>

                {{-- Valores --}}
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 text-xs">
                    <div>
                        <div class="text-gray-500">Total Pedido</div>
                        <div class="font-semibold text-gray-800 dark:text-white">R$ {{ number_format($total, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Subtotal</div>
                        <div class="font-semibold text-gray-800 dark:text-white">R$ {{ number_format($totalProd, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Custo Prod.</div>
                        <div class="font-semibold {{ $custoProd > 0 ? 'text-gray-800 dark:text-white' : 'text-orange-600' }}">
                            R$ {{ number_format($custoProd, 2, ',', '.') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500">Comissão</div>
                        <div class="font-semibold text-gray-800 dark:text-white">R$ {{ number_format($comissao, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Imposto</div>
                        <div class="font-semibold text-gray-800 dark:text-white">R$ {{ number_format($imposto, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Frete (cobrado → {{ $custoFrete > 0 && !$venda->nfe_chave_acesso ? 'cotado' : 'pago' }})</div>
                        <div class="font-semibold text-gray-800 dark:text-white">
                            R$ {{ number_format($freteCliente, 2, ',', '.') }} → R$ {{ number_format($custoFrete, 2, ',', '.') }}
                            @if($custoFrete > 0 && !$venda->nfe_chave_acesso)
                                <span style="color:#d97706;font-size:10px;">⚠ estimado</span>
                            @endif
                        </div>
                    </div>
                    @if($subsidio > 0)
                    <div>
                        <div class="text-gray-500">Subsídio Pix</div>
                        <div class="font-semibold text-blue-600">R$ {{ number_format($subsidio, 2, ',', '.') }}</div>
                    </div>
                    @endif
                    <div class="rounded-lg p-2 {{ $lucroBg }}">
                        <div class="text-gray-500">Lucro Final</div>
                        <div class="font-bold text-base {{ $lucroColor }}">
                            R$ {{ number_format($lucro, 2, ',', '.') }}
                            <span class="text-xs">({{ $margemPct }}%)</span>
                        </div>
                    </div>
                </div>

                {{-- Margens detalhadas --}}
                <div class="flex flex-wrap gap-3 mt-2 text-xs">
                    <span class="{{ $margemProd >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        📦 Margem Prod: R$ {{ number_format($margemProd, 2, ',', '.') }}
                        ({{ $totalProd > 0 ? round(($margemProd / $totalProd) * 100, 1) : 0 }}%)
                    </span>
                    @if($freteCliente > 0 || $custoFrete > 0)
                    <span class="{{ $margemFrete >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        🚚 Margem Frete: R$ {{ number_format($margemFrete, 2, ',', '.') }}
                    </span>
                    @endif
                </div>

                {{-- Alertas --}}
                @if(!empty($alertas))
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach($alertas as $alerta)
                        <span class="text-xs px-2 py-0.5 rounded bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200">
                            ⚠ {{ $alerta }}
                        </span>
                    @endforeach
                </div>
                @endif

                {{-- Botões de ação --}}
                @php
                    $isML = str_contains(strtolower($canal), 'mercado');
                    $isShopee = str_contains(strtolower($canal), 'shopee');
                    $temNfe = !empty($venda->nfe_chave_acesso);
                    $temFretePago = $custoFrete > 0;
                    $temPlanilha = (bool) $venda->planilha_processada;
                @endphp
                <div class="flex flex-wrap gap-2 mt-3">
                    @if(!$venda->numero_nota_fiscal || !$temNfe)
                        <button wire:click="buscarNfe({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#2563eb;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📄 Buscar NF-e
                        </button>
                    @endif
                    @if($temNfe && !$temFretePago)
                        <button wire:click="buscarCte({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#7c3aed;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            🚚 Buscar CT-e
                        </button>
                    @endif
                    @if($isML && !$temPlanilha)
                        <button wire:click="aplicarPlanilhaML({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#d97706;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📊 Aplicar Planilha ML
                        </button>
                    @endif
                    @if($isShopee && !$temPlanilha)
                        <button wire:click="aplicarPlanilhaShopee({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#ea580c;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📊 Aplicar Planilha Shopee
                        </button>
                    @endif
                    <button wire:click="recalcular({{ $venda->id_venda }})" wire:loading.attr="disabled"
                        style="background:#059669;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                        🔄 Recalcular
                    </button>
                </div>

                {{-- Itens do pedido --}}
                @if(!empty($venda->staging_itens))
                <div class="mt-3 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-wrap gap-3 text-xs">
                        @foreach($venda->staging_itens as $item)
                            @php
                                $sku = $item['codigo'] ?? null;
                                $desc = $item['descricao'] ?? null;
                                $qtd = $item['quantidade'] ?? 1;
                            @endphp
                            @if($sku || $desc)
                            <span class="text-gray-600 dark:text-gray-400">
                                @if($sku)<span class="font-mono text-gray-800 dark:text-gray-200">{{ $sku }}</span>@endif
                                {{ $desc }}
                                <span class="font-semibold">x{{ $qtd }}</span>
                            </span>
                            @else
                            <span class="text-gray-500 dark:text-gray-500 italic">
                                Item x{{ $qtd }} — R$ {{ number_format((float)($item['valor'] ?? 0), 2, ',', '.') }}
                            </span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        @empty
            <div class="text-center text-gray-500 py-8">Nenhuma venda encontrada para o período selecionado.</div>
        @endforelse
    </div>
</x-filament-panels::page>
