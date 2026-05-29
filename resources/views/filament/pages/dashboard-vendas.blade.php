<x-filament-panels::page>
    {{-- Botões de ação ao lado do título --}}
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:-40px;margin-bottom:16px;justify-content:flex-end;">
        <button wire:click="$refresh" style="background:#2563eb;color:#fff;padding:6px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;white-space:nowrap;">
            🔄 Atualizar
        </button>
        <button wire:click="buscarNfeLote" wire:loading.attr="disabled" wire:confirm="Buscar NF-e para todas as vendas sem nota no período filtrado?"
            style="background:#2563eb;color:#fff;padding:6px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;white-space:nowrap;">
            📄 NF-e Lote
        </button>
        <button wire:click="buscarCteLote" wire:loading.attr="disabled" wire:confirm="Buscar CT-e para todas as vendas sem frete no período filtrado?"
            style="background:#7c3aed;color:#fff;padding:6px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;white-space:nowrap;">
            🚚 CT-e Lote
        </button>
        <button wire:click="buscarCustosLote" wire:loading.attr="disabled" wire:confirm="Buscar custos para todas as vendas sem custo no período filtrado?"
            style="background:#d97706;color:#fff;padding:6px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;white-space:nowrap;">
            💰 Custos Lote
        </button>
        <button wire:click="aplicarPlanilhaShopeeLote" wire:loading.attr="disabled" wire:confirm="Aplicar planilha Shopee para todas as vendas Shopee sem planilha no período?"
            style="background:#ea580c;color:#fff;padding:6px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;white-space:nowrap;">
            📊 Shopee Lote
        </button>
        <a href="#" wire:click.prevent="exportarPlanilha"
            style="background:#065f46;color:#fff;padding:6px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-block;">
            📥 Exportar CSV
        </a>
    </div>

    {{-- Filtros ocupando toda a largura --}}
    <div>
        <form wire:submit.prevent="">
            {{ $this->form }}
        </form>
    </div>

    {{-- Resumo horizontal compacto --}}
    @php
        $totais = $this->totais;
        $grafico = $this->graficoVendasDiarias;
        $porCanal = $this->vendasPorCanal;
        $ticketMedio = $totais['qtd'] > 0 ? $totais['total'] / $totais['qtd'] : 0;
    @endphp

    {{-- KPI Cards --}}
    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:16px;">
        <div style="flex:1;min-width:170px;background:var(--kpi-bg,#fff);border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:4px solid #3b82f6;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:11px;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:.5px;">Vendas</span>
                <span style="width:32px;height:32px;border-radius:8px;background:rgba(59,130,246,.15);display:flex;align-items:center;justify-content:center;font-size:16px;">🛒</span>
            </div>
            <div style="font-size:28px;font-weight:800;color:var(--kpi-text,#1f2937);">{{ $totais['qtd'] }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:4px;">pedidos no período</div>
        </div>
        <div style="flex:1;min-width:170px;background:var(--kpi-bg,#fff);border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:4px solid #6366f1;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:.5px;">Faturamento</span>
                <span style="width:32px;height:32px;border-radius:8px;background:rgba(99,102,241,.15);display:flex;align-items:center;justify-content:center;font-size:16px;">💰</span>
            </div>
            <div style="font-size:28px;font-weight:800;color:var(--kpi-text,#1f2937);">R$ {{ number_format($totais['total'], 2, ',', '.') }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:4px;">ticket médio R$ {{ number_format($ticketMedio, 2, ',', '.') }}</div>
        </div>
        <div style="flex:1;min-width:170px;background:var(--kpi-bg,#fff);border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:4px solid {{ $totais['lucro'] >= 0 ? '#10b981' : '#ef4444' }};">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:11px;font-weight:700;color:{{ $totais['lucro'] >= 0 ? '#10b981' : '#ef4444' }};text-transform:uppercase;letter-spacing:.5px;">Lucro</span>
                <span style="width:32px;height:32px;border-radius:8px;background:{{ $totais['lucro'] >= 0 ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)' }};display:flex;align-items:center;justify-content:center;font-size:16px;">📈</span>
            </div>
            <div style="font-size:28px;font-weight:800;color:{{ $totais['lucro'] >= 0 ? '#059669' : '#dc2626' }};">R$ {{ number_format($totais['lucro'], 2, ',', '.') }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:4px;">margem {{ $totais['margem'] }}%</div>
        </div>
        <div style="flex:1;min-width:170px;background:var(--kpi-bg,#fff);border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:4px solid #10b981;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:11px;font-weight:700;color:#10b981;text-transform:uppercase;letter-spacing:.5px;">Com Lucro</span>
                <span style="width:32px;height:32px;border-radius:8px;background:rgba(16,185,129,.15);display:flex;align-items:center;justify-content:center;font-size:16px;">✅</span>
            </div>
            <div style="font-size:28px;font-weight:800;color:#059669;">{{ $totais['com_lucro'] }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:4px;">{{ $totais['qtd'] > 0 ? round(($totais['com_lucro'] / $totais['qtd']) * 100, 1) : 0 }}% do total</div>
        </div>
        <div style="flex:1;min-width:170px;background:var(--kpi-bg,#fff);border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:4px solid #ef4444;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:11px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:.5px;">Com Prejuízo</span>
                <span style="width:32px;height:32px;border-radius:8px;background:rgba(239,68,68,.15);display:flex;align-items:center;justify-content:center;font-size:16px;">⚠️</span>
            </div>
            <div style="font-size:28px;font-weight:800;color:#dc2626;">{{ $totais['com_prejuizo'] }}</div>
            <div style="font-size:11px;color:#9ca3af;margin-top:4px;">{{ $totais['qtd'] > 0 ? round(($totais['com_prejuizo'] / $totais['qtd']) * 100, 1) : 0 }}% do total</div>
        </div>
    </div>
    <style>
        .dark { --kpi-bg: #1f2937; --kpi-text: #f9fafb; }
        :root { --kpi-bg: #fff; --kpi-text: #1f2937; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
        {{-- Gráfico de barras - Faturamento/Lucro diário --}}
        <div class="lg:col-span-2 rounded-2xl bg-white dark:bg-gray-800 shadow-md p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Faturamento & Lucro Diário</h3>
            </div>
            <div style="position:relative;height:220px;">
                <canvas id="chartVendasDiarias"></canvas>
            </div>
        </div>

        {{-- Donut margem + breakdown por canal --}}
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-md p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Vendas por Canal</h3>
            <div style="position:relative;height:180px;" class="mb-4">
                <canvas id="chartCanais"></canvas>
            </div>
            <div class="space-y-2">
                @php
                    $canalCores = ['#3b82f6','#f59e0b','#10b981','#8b5cf6','#ef4444','#06b6d4','#ec4899','#6366f1'];
                @endphp
                @foreach($porCanal as $i => $c)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $canalCores[$i % count($canalCores)] }}"></span>
                            <span class="text-gray-600 dark:text-gray-300">{{ $c['canal'] }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-gray-800 dark:text-white">{{ $c['qtd'] }}</span>
                            <span class="text-gray-400 w-24 text-right">R$ {{ number_format($c['total'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div id="chartDataHolder" class="hidden" data-grafico='@json($grafico)' data-canais='@json($porCanal)'></div>

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

                // Dados do canal
                $conta = $venda->bling_account === 'primary' ? 'Mobilia' : 'HES';
                $canal = $venda->canal?->nome_canal ?? '-';
                $isCancelada = (bool) $venda->cancelada;

                // Status da venda
                $temNfeChave = !empty($venda->nfe_chave_acesso);
                $fretePagoFlag = (bool) $venda->frete_pago;
                $planilhaOk = (bool) $venda->planilha_processada;
                $mlTipoFrete = $venda->ml_tipo_frete ?? null;
                $isMlMe2Full = in_array($mlTipoFrete, ['ME2', 'FULL']);
                $isML = str_contains(strtolower($canal), 'mercado');
                $isShopee = str_contains(strtolower($canal), 'shopee');
                $isMagalu = str_contains(strtolower($canal), 'magalu');
                $isWebcontinental = str_contains(strtolower($canal), 'webcontinental');
                $isMadeiraMadeira = str_contains(strtolower($canal), 'madeira');
                $precisaPlanilha = $isML || $isShopee || $isMagalu || $isWebcontinental || $isMadeiraMadeira;
                $precisaAfiliado = $isShopee;
                $afiliadoOk = (bool) $venda->planilha_afiliado_processada;
                $freteOk = $fretePagoFlag || $isMlMe2Full || ($freteCliente == 0 && $custoFrete == 0);
                $completo = $temNfeChave && $freteOk && (!$precisaPlanilha || $planilhaOk) && (!$precisaAfiliado || $afiliadoOk);
            @endphp

            <div class="rounded-xl shadow border-l-4 {{ $borderColor }} p-4 {{ $isCancelada ? 'bg-red-50 dark:bg-red-950/40' : 'bg-white dark:bg-gray-800' }}">
                {{-- Header + Status Cards em layout horizontal --}}
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 mb-3">
                    {{-- Lado esquerdo: Info do pedido --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
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
                            @if($isCancelada)
                                <span style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">🚫 Cancelado</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                            {{ $venda->cliente_nome }}
                            @if($venda->cliente_documento)
                                <span class="text-gray-400 dark:text-gray-500">·</span>
                                <span style="cursor:pointer;text-decoration:underline dotted;" title="Clique para copiar"
                                    onclick="navigator.clipboard.writeText('{{ $venda->cliente_documento }}').then(()=>{this.innerText='Copiado!';setTimeout(()=>this.innerText='{{ $venda->cliente_documento }}',1500)})">
                                    {{ $venda->cliente_documento }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Lado direito: Cards de Status (checklist visual) --}}
                    <div class="flex flex-wrap gap-2 shrink-0">
                        {{-- NF-e --}}
                        @if($temNfeChave)
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
                                <span class="text-green-600 dark:text-green-400 text-sm">✓</span>
                                <span class="text-xs font-medium text-green-700 dark:text-green-300">NF-e</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800">
                                <span class="text-red-500 dark:text-red-400 text-sm">✗</span>
                                <span class="text-xs font-medium text-red-700 dark:text-red-300">NF-e</span>
                                @if($venda->data_prevista_envio)
                                    <span class="text-[10px] text-purple-600 dark:text-purple-400 ml-1">📦 {{ $venda->data_prevista_envio->format('d/m') }}</span>
                                @endif
                            </div>
                        @endif

                        {{-- Frete --}}
                        @if($freteOk)
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
                                <span class="text-green-600 dark:text-green-400 text-sm">✓</span>
                                <span class="text-xs font-medium text-green-700 dark:text-green-300">Frete</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800">
                                <span class="text-amber-500 dark:text-amber-400 text-sm">✗</span>
                                <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Frete</span>
                            </div>
                        @endif

                        {{-- Planilha (só marketplaces) --}}
                        @if($precisaPlanilha)
                            @if($planilhaOk)
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
                                    <span class="text-green-600 dark:text-green-400 text-sm">✓</span>
                                    <span class="text-xs font-medium text-green-700 dark:text-green-300">Planilha</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-50 dark:bg-purple-900/30 border border-purple-200 dark:border-purple-800">
                                    <span class="text-purple-500 dark:text-purple-400 text-sm">✗</span>
                                    <span class="text-xs font-medium text-purple-700 dark:text-purple-300">Planilha</span>
                                </div>
                            @endif
                        @endif

                        {{-- Afiliado (só Shopee) --}}
                        @if($precisaAfiliado)
                            @if($afiliadoOk)
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
                                    <span class="text-green-600 dark:text-green-400 text-sm">✓</span>
                                    <span class="text-xs font-medium text-green-700 dark:text-green-300">Afiliado</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-pink-50 dark:bg-pink-900/30 border border-pink-200 dark:border-pink-800">
                                    <span class="text-pink-500 dark:text-pink-400 text-sm">✗</span>
                                    <span class="text-xs font-medium text-pink-700 dark:text-pink-300">Afiliado</span>
                                </div>
                            @endif
                        @endif

                        {{-- Custos --}}
                        @if($custoProd > 0)
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
                                <span class="text-green-600 dark:text-green-400 text-sm">✓</span>
                                <span class="text-xs font-medium text-green-700 dark:text-green-300">Custos</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800">
                                <span class="text-orange-500 dark:text-orange-400 text-sm">✗</span>
                                <span class="text-xs font-medium text-orange-700 dark:text-orange-300">Custos</span>
                            </div>
                        @endif

                        {{-- Imposto --}}
                        @if($imposto > 0)
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800">
                                <span class="text-green-600 dark:text-green-400 text-sm">✓</span>
                                <span class="text-xs font-medium text-green-700 dark:text-green-300">Imposto</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-50 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800">
                                <span class="text-orange-500 dark:text-orange-400 text-sm">✗</span>
                                <span class="text-xs font-medium text-orange-700 dark:text-orange-300">Imposto</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Valores em layout horizontal --}}
                @php
                    $mlSaleFee = (float) ($venda->ml_sale_fee ?? 0);
                    $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
                    $mlRebate = (float) ($venda->ml_valor_rebate ?? 0);
                    $temDadosML = $mlSaleFee > 0 || $mlFreteCusto > 0;
                    $isMagaluRepasse = str_contains(strtolower($canal), 'magalu');
                    $repasse = $isMagaluRepasse
                        ? $total - $comissao + $subsidio
                        : $totalProd + $freteCliente - $comissao;
                    $fretePago = (bool) $venda->frete_pago;
                    $freteCotado = (float) ($venda->frete_cotado ?? 0);
                    $alertaCte = false;
                    $diffCtePercent = 0;
                    if ($fretePago && $freteCotado > 0 && $custoFrete > 0 && $freteCotado != $custoFrete) {
                        $diffCtePercent = round((($custoFrete - $freteCotado) / $freteCotado) * 100, 1);
                        $alertaCte = abs($diffCtePercent) > 5;
                    }
                    $alertaCobrado = false;
                    $diffCobradoPercent = 0;
                    if ($freteCotado > 0 && $freteCliente > 0 && $freteCotado != $freteCliente) {
                        $diffCobradoPercent = round((($freteCliente - $freteCotado) / $freteCotado) * 100, 1);
                        $alertaCobrado = abs($diffCobradoPercent) > 5;
                    }
                    $isMagaluCard = str_contains(strtolower($canal), 'magalu');
                @endphp
                <div class="flex flex-wrap items-stretch gap-2 text-xs">
                    <div class="flex items-center gap-4 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Total</div>
                            <div class="font-semibold text-gray-800 dark:text-white">R$ {{ number_format($total, 2, ',', '.') }}</div>
                        </div>
                        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600"></div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Subtotal</div>
                            <div class="font-semibold text-gray-800 dark:text-white">R$ {{ number_format($totalProd, 2, ',', '.') }}</div>
                        </div>
                        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600"></div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Custo</div>
                            <div class="font-semibold {{ $custoProd > 0 ? 'text-gray-800 dark:text-white' : 'text-orange-600' }}">R$ {{ number_format($custoProd, 2, ',', '.') }}</div>
                        </div>
                        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600"></div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Comissão</div>
                            <div class="font-semibold text-gray-800 dark:text-white">
                                R$ {{ number_format($comissao, 2, ',', '.') }}
                                @if((float) ($venda->comissao_afiliado ?? 0) > 0)
                                    <span style="font-size:9px;color:#db2777;">+{{ number_format((float) $venda->comissao_afiliado, 2, ',', '.') }}</span>
                                @endif
                            </div>
                            @if($temDadosML)
                                <div style="font-size:9px;color:#9ca3af;">
                                    Tarifa: {{ number_format($mlSaleFee + $mlRebate, 2, ',', '.') }}
                                    @if($mlRebate > 0) <span style="color:#10b981;">(-{{ number_format($mlRebate, 2, ',', '.') }})</span> @endif
                                    @if($isMlMe2Full && $mlFreteCusto > 0) | Envio: {{ number_format($mlFreteCusto, 2, ',', '.') }} @endif
                                </div>
                            @endif
                        </div>
                        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600"></div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Imposto</div>
                            <div class="font-semibold text-gray-800 dark:text-white">R$ {{ number_format($imposto, 2, ',', '.') }}</div>
                        </div>
                        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600"></div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Repasse</div>
                            <div class="font-semibold text-blue-600 dark:text-blue-400">R$ {{ number_format($repasse, 2, ',', '.') }}</div>
                        </div>
                    </div>

                    {{-- Frete separado --}}
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Frete (cobrado → {{ $fretePago ? 'pago' : ($custoFrete > 0 ? 'cotado' : '-') }})</div>
                            <div class="font-semibold text-gray-800 dark:text-white">
                                R$ {{ number_format($freteCliente, 2, ',', '.') }} → R$ {{ number_format($custoFrete, 2, ',', '.') }}
                                @if($custoFrete > 0 && !$fretePago)
                                    <span style="color:#d97706;font-size:9px;">⚠ estimado</span>
                                @endif
                            </div>
                            @if($alertaCobrado)
                                <div style="font-size:10px;font-weight:700;{{ $diffCobradoPercent > 0 ? 'color:#059669;' : 'color:#dc2626;' }}">
                                    💰 {{ $diffCobradoPercent > 0 ? '+' : '' }}{{ $diffCobradoPercent }}% vs cotado
                                </div>
                            @endif
                            @if($alertaCte)
                                <div style="font-size:10px;font-weight:700;{{ $diffCtePercent > 0 ? 'color:#dc2626;' : 'color:#059669;' }}">
                                    🚨 CT-e {{ $diffCtePercent > 0 ? '+' : '' }}{{ $diffCtePercent }}% vs cotado
                                </div>
                            @elseif($fretePago && $freteCotado > 0 && $freteCotado != $custoFrete)
                                <span style="color:#6b7280;font-size:9px;">(cotado: R$ {{ number_format($freteCotado, 2, ',', '.') }})</span>
                            @endif
                        </div>
                    </div>

                    @if($subsidio > 0)
                    <div class="flex items-center px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">{{ $isMagaluCard ? 'Desc. Vendedor' : 'Subsídio Pix' }}</div>
                            <div class="font-semibold {{ $isMagaluCard ? 'text-red-600' : 'text-blue-600' }}">R$ {{ number_format($subsidio, 2, ',', '.') }}</div>
                        </div>
                    </div>
                    @endif

                    {{-- Lucro destacado --}}
                    <div class="flex items-center px-4 py-2 rounded-lg {{ $lucroBg }} border {{ $lucro >= 0 ? 'border-green-200 dark:border-green-800' : 'border-red-200 dark:border-red-800' }}">
                        <div>
                            <div class="text-gray-500 dark:text-gray-400 text-[10px] uppercase tracking-wide">Lucro</div>
                            <div class="font-bold text-base {{ $lucroColor }}">
                                R$ {{ number_format($lucro, 2, ',', '.') }}
                                <span class="text-xs font-normal">({{ $margemPct }}%)</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Margens detalhadas (horizontal) --}}
                @php
                    $comissaoSobreFrete = (bool) ($venda->canal?->comissao_sobre_frete ?? false);
                    $impostoSobreFrete = (bool) ($venda->canal?->imposto_sobre_frete ?? false);
                    $pctImposto = (float) $venda->percentual_imposto;
                    $comissaoFreteVal = 0;
                    if ($comissaoSobreFrete && $freteCliente > 0 && $venda->canal) {
                        $regraCanal = $venda->canal->regrasComissao()->where('ativo', true)->first();
                        if ($regraCanal) {
                            $comissaoFreteVal = round($freteCliente * (float) $regraCanal->percentual / 100, 2);
                        }
                    }
                    $comissaoProdVal = $comissao - $comissaoFreteVal;
                    $impostoFreteVal = ($impostoSobreFrete && $freteCliente > 0 && $pctImposto > 0)
                        ? round($freteCliente * $pctImposto / 100, 2) : 0;
                    $impostoProdVal = $imposto - $impostoFreteVal;
                    $isMagaluCard2 = str_contains(strtolower($canal), 'magalu');
                    $desconto = $total - $totalProd - $freteCliente;
                @endphp
                <div class="flex flex-wrap gap-2 mt-2 text-xs">
                    {{-- Margem Produto --}}
                    <div class="flex-1 min-w-[200px] rounded-lg px-3 py-2 {{ $margemProd >= 0 ? 'bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800' }}">
                        <div class="font-semibold {{ $margemProd >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                            📦 Margem Produto: R$ {{ number_format($margemProd, 2, ',', '.') }}
                            ({{ $totalProd > 0 ? round(($margemProd / $totalProd) * 100, 1) : 0 }}%)
                        </div>
                        <div style="color:#9ca3af;font-size:10px;margin-top:2px;">
                            Sub: {{ number_format($totalProd, 2, ',', '.') }}
                            @if($desconto < -0.01 && $isMagaluCard2) | Desc: {{ number_format(abs($desconto), 2, ',', '.') }} @endif
                            | Custo: {{ number_format($custoProd, 2, ',', '.') }}
                            | Com: {{ number_format($comissaoProdVal, 2, ',', '.') }}
                            @if($impostoProdVal > 0) | Imp: {{ number_format($impostoProdVal, 2, ',', '.') }} @endif
                        </div>
                    </div>

                    {{-- Margem Frete --}}
                    @if($freteCliente > 0 || $custoFrete > 0)
                    <div class="flex-1 min-w-[200px] rounded-lg px-3 py-2 {{ $margemFrete >= 0 ? 'bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800' }}">
                        <div class="font-semibold {{ $margemFrete >= 0 ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                            🚚 Margem Frete: R$ {{ number_format($margemFrete, 2, ',', '.') }}
                        </div>
                        <div style="color:#9ca3af;font-size:10px;margin-top:2px;">
                            Cobrado: {{ number_format($freteCliente, 2, ',', '.') }}
                            | Pago: {{ number_format($custoFrete, 2, ',', '.') }}
                            @if($comissaoFreteVal > 0) | Com: {{ number_format($comissaoFreteVal, 2, ',', '.') }} @endif
                            @if($impostoFreteVal > 0) | Imp: {{ number_format($impostoFreteVal, 2, ',', '.') }} @endif
                        </div>
                    </div>
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
                    $isMagalu = str_contains(strtolower($canal), 'magalu');
                    $isWebcontinental = str_contains(strtolower($canal), 'webcontinental');
                    $temNfe = !empty($venda->nfe_chave_acesso);
                    $fretePagoReal = (bool) $venda->frete_pago;
                    $temPlanilha = (bool) $venda->planilha_processada;
                    $isMarketplace = $isML || $isShopee || $isMagalu || $isWebcontinental || $isMadeiraMadeira;
                    $mlTipoFreteBtn = $venda->ml_tipo_frete ?? null;
                    $isMlMe1 = $mlTipoFreteBtn === 'ME1';
                @endphp
                <div class="flex flex-wrap gap-2 mt-3">
                    @if(!$temNfe)
                        <button wire:click="buscarNfe({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#2563eb;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📄 Buscar NF-e
                        </button>
                        @if(!$venda->data_prevista_envio)
                            <button onclick="let d=prompt('Data prevista de envio (YYYY-MM-DD):','{{ now()->addDays(7)->format('Y-m-d') }}');if(d)@this.marcarAguardandoEnvio({{ $venda->id_venda }},d)"
                                style="background:#7c3aed;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                                📦 Aguardando Envio
                            </button>
                        @else
                            <span style="background:#7c3aed;color:#fff;padding:3px 8px;border-radius:4px;font-size:10px;">
                                📦 Envio: {{ $venda->data_prevista_envio->format('d/m') }}
                            </span>
                            <button wire:click="removerAguardandoEnvio({{ $venda->id_venda }})"
                                style="background:#374151;color:#9ca3af;padding:3px 8px;font-size:10px;border-radius:5px;border:none;cursor:pointer;">
                                ✖
                            </button>
                        @endif
                    @else
                        <button onclick="let n=prompt('Número da NF-e correta:');if(n)@this.buscarNfePorNumero({{ $venda->id_venda }},n)"
                            style="background:#374151;color:#e5e7eb;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            🔄 Trocar NF-e
                        </button>
                    @endif
                    @if($temNfe && !$fretePagoReal && !$isMlMe2Full && (!$isML || $isMlMe1) && !($isShopee && $freteCliente == 0))
                        <button wire:click="buscarCte({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#7c3aed;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            🚚 Buscar CT-e
                        </button>
                        @php
                            $isViaCnova = str_contains(strtolower($canal), 'via') || str_contains(strtolower($canal), 'cnova');
                        @endphp
                        @if($isViaCnova)
                            <button wire:click="marcarFreteEnvias({{ $venda->id_venda }})" wire:loading.attr="disabled"
                                wire:confirm="Marcar como Envias? Frete será zerado (marketplace paga)."
                                style="background:#0891b2;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                                📦 Frete Envias
                            </button>
                        @endif
                        @if($isShopee)
                            <button wire:click="marcarFreteEnvias({{ $venda->id_venda }})" wire:loading.attr="disabled"
                                wire:confirm="Marcar como Shopee Xpress? Frete será zerado."
                                style="background:#ea580c;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                                📦 Shopee Xpress
                            </button>
                        @endif
                        <div x-data="{ open: false, valor: '', transp: '' }" style="display:inline-block;">
                            <button @click="open = !open" type="button"
                                style="background:#065f46;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                                ✏️ Frete Manual
                            </button>
                            <div x-show="open" x-cloak style="margin-top:4px;display:flex;gap:4px;align-items:center;">
                                <input x-model="valor" type="text" placeholder="Valor" style="width:80px;padding:3px 6px;font-size:11px;border-radius:4px;border:1px solid #4b5563;background:#1f2937;color:#fff;">
                                <input x-model="transp" type="text" placeholder="Transportadora" style="width:120px;padding:3px 6px;font-size:11px;border-radius:4px;border:1px solid #4b5563;background:#1f2937;color:#fff;">
                                <button @click="if(valor) { $wire.lancarFreteManual({{ $venda->id_venda }}, valor, transp); open=false; }" type="button"
                                    style="background:#059669;color:#fff;padding:3px 8px;font-size:11px;border-radius:4px;border:none;cursor:pointer;">✓</button>
                            </div>
                        </div>
                    @endif
                    @if($isML && !$temPlanilha)
                        <button wire:click="aplicarPlanilhaML({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#d97706;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📊 Buscar Dados ML
                        </button>
                    @endif
                    @if($isShopee && !$temPlanilha)
                        <button wire:click="aplicarPlanilhaShopee({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#ea580c;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📊 Aplicar Planilha Shopee
                        </button>
                    @endif
                    @php
                        $isMadeiraMadeira = str_contains(strtolower($canal), 'madeira');
                    @endphp
                    @if($isMadeiraMadeira && !$temPlanilha)
                        <button wire:click="aplicarPlanilhaMM({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#b45309;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📊 Aplicar Planilha MM
                        </button>
                    @endif
                    @if($isWebcontinental && !$temPlanilha)
                        <button wire:click="aplicarPlanilhaWC({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#7c2d12;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📊 Aplicar Planilha WC
                        </button>
                    @endif
                    <button wire:click="recalcular({{ $venda->id_venda }})" wire:loading.attr="disabled"
                        style="background:#059669;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                        🔄 Recalcular
                    </button>
                    @if($custoProd <= 0)
                        <button wire:click="buscarCustos({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#d97706;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            💰 Buscar Custos
                        </button>
                    @endif
                    <a href="/vendas/{{ $venda->id_venda }}/edit"
                        style="background:#374151;color:#e5e7eb;padding:3px 10px;font-size:11px;border-radius:5px;text-decoration:none;display:inline-block;">
                        ✏️ Editar
                    </a>
                    <span x-data="{showEstorno:false,dataEstorno:'{{ now()->format('Y-m-d') }}'}" class="inline">
                        <button @click="showEstorno=!showEstorno" title="Pedido CANCELADO antes do pagamento. O marketplace vai descontar o valor antecipado do próximo repasse." style="background:#991b1b;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">↩️ Estorno</button>
                        <span x-show="showEstorno" x-cloak style="display:inline-flex;align-items:center;gap:4px;margin-left:4px;">
                            <input type="date" x-model="dataEstorno" style="padding:2px 6px;font-size:11px;border-radius:4px;border:1px solid #374151;background:#111827;color:#fff;">
                            <button @click="$wire.cancelarComEstorno({{ $venda->id_venda }},dataEstorno);showEstorno=false" style="background:#dc2626;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;">Confirmar</button>
                            <button @click="showEstorno=false" style="color:#9ca3af;font-size:10px;cursor:pointer;background:none;border:none;">✖</button>
                        </span>
                    </span>
                    <span x-data="{showReembolso:false,dataReembolso:'{{ now()->format('Y-m-d') }}'}" class="inline">
                        <button @click="showReembolso=!showReembolso" title="Pedido DEVOLVIDO ou disputa perdida. A venda existiu mas o marketplace vai debitar o valor do próximo repasse." style="background:#7f1d1d;color:#fca5a5;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">🔄 Reembolso</button>
                        <span x-show="showReembolso" x-cloak style="display:inline-flex;align-items:center;gap:4px;margin-left:4px;">
                            <input type="date" x-model="dataReembolso" style="padding:2px 6px;font-size:11px;border-radius:4px;border:1px solid #374151;background:#111827;color:#fff;">
                            <button @click="$wire.registrarReembolso({{ $venda->id_venda }},dataReembolso);showReembolso=false" style="background:#dc2626;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;">Confirmar</button>
                            <button @click="showReembolso=false" style="color:#9ca3af;font-size:10px;cursor:pointer;background:none;border:none;">✖</button>
                        </span>
                    </span>
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
                            <span class="text-gray-600 dark:text-gray-400" title="{{ $desc }}">
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

    {{-- Paginação --}}
    @php $totalPaginas = $this->totalPaginas; @endphp
    @if($totalPaginas > 1)
    <div class="flex items-center justify-center gap-4 mt-6">
        <button wire:click="paginaAnterior" @if($pagina <= 1) disabled @endif
            style="background:{{ $pagina <= 1 ? '#374151' : '#2563eb' }};color:#fff;padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:{{ $pagina <= 1 ? 'default' : 'pointer' }};opacity:{{ $pagina <= 1 ? '0.5' : '1' }};">
            ← Anterior
        </button>
        <span class="text-sm text-gray-500">Página {{ $pagina }} de {{ $totalPaginas }}</span>
        <button wire:click="proximaPagina" @if($pagina >= $totalPaginas) disabled @endif
            style="background:{{ $pagina >= $totalPaginas ? '#374151' : '#2563eb' }};color:#fff;padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:{{ $pagina >= $totalPaginas ? 'default' : 'pointer' }};opacity:{{ $pagina >= $totalPaginas ? '0.5' : '1' }};">
            Próxima →
        </button>
    </div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
        let chartBar = null, chartDonut = null, chartInitTimeout = null;

        function initDashboardCharts() {
            if (chartInitTimeout) clearTimeout(chartInitTimeout);
            chartInitTimeout = setTimeout(doInitCharts, 200);
        }

        function doInitCharts() {
            const barEl = document.getElementById('chartVendasDiarias');
            const donutEl = document.getElementById('chartCanais');
            const holder = document.getElementById('chartDataHolder');
            if (!barEl || !donutEl || !holder) return;

            try {
                const grafico = JSON.parse(holder.dataset.grafico);
                const porCanal = JSON.parse(holder.dataset.canais);
                const cores = ['#3b82f6','#f59e0b','#10b981','#8b5cf6','#ef4444','#06b6d4','#ec4899','#6366f1'];
                const isDark = document.documentElement.classList.contains('dark');
                const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
                const textColor = isDark ? '#9ca3af' : '#6b7280';

                if (chartBar) { chartBar.destroy(); chartBar = null; }
                if (chartDonut) { chartDonut.destroy(); chartDonut = null; }

                chartBar = new Chart(barEl, {
                    type: 'bar',
                    data: {
                        labels: grafico.labels,
                        datasets: [
                            { label: 'Faturamento', data: grafico.faturamento, backgroundColor: '#3b82f6', borderRadius: 6, barPercentage: 0.6 },
                            { label: 'Lucro', data: grafico.lucro, backgroundColor: '#10b981', borderRadius: 6, barPercentage: 0.6 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 16, color: textColor, font: { size: 11 } } } },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: textColor, font: { size: 10 } } },
                            y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 }, callback: v => 'R$ ' + (v/1000).toFixed(1) + 'k' } }
                        }
                    }
                });

                chartDonut = new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: porCanal.map(c => c.canal),
                        datasets: [{ data: porCanal.map(c => c.total), backgroundColor: cores.slice(0, porCanal.length), borderWidth: 0 }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false, cutout: '65%',
                        plugins: { legend: { display: false } }
                    }
                });
            } catch(e) { console.warn('Chart init error:', e); }
        }

        document.addEventListener('DOMContentLoaded', initDashboardCharts);
        document.addEventListener('livewire:navigated', initDashboardCharts);
        document.addEventListener('livewire:init', () => {
            initDashboardCharts();
            Livewire.hook('morph.updated', () => initDashboardCharts());
        });
    </script>
</x-filament-panels::page>
