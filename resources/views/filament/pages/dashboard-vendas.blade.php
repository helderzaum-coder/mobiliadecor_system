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
        <button wire:click="recalcularImpostosLote" wire:loading.attr="disabled" wire:confirm="Recalcular impostos para vendas com NF-e mas imposto zerado no período filtrado?"
            style="background:#dc2626;color:#fff;padding:6px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;white-space:nowrap;">
            🏦 Impostos Lote
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
                            @if($venda->repasse_recebido)
                                <span style="background:#065f46;color:#6ee7b7;padding:2px 8px;border-radius:4px;font-size:10px;">💰 Recebido</span>
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
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(6,78,59,0.35);border:1px solid rgba(34,197,94,0.5);">
                                <span style="color:#4ade80;font-size:14px;">✓</span>
                                <span style="font-size:11px;font-weight:600;color:#86efac;">NF-e</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(127,29,29,0.35);border:1px solid rgba(239,68,68,0.5);">
                                <span style="color:#f87171;font-size:14px;">✗</span>
                                <span style="font-size:11px;font-weight:600;color:#fca5a5;">NF-e</span>
                                @if($venda->data_prevista_envio)
                                    <span style="font-size:10px;color:#c084fc;margin-left:4px;">📦 {{ $venda->data_prevista_envio->format('d/m') }}</span>
                                @endif
                            </div>
                        @endif

                        {{-- Frete --}}
                        @if($freteOk)
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(6,78,59,0.35);border:1px solid rgba(34,197,94,0.5);">
                                <span style="color:#4ade80;font-size:14px;">✓</span>
                                <span style="font-size:11px;font-weight:600;color:#86efac;">Frete</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(120,53,15,0.35);border:1px solid rgba(245,158,11,0.5);">
                                <span style="color:#fbbf24;font-size:14px;">✗</span>
                                <span style="font-size:11px;font-weight:600;color:#fcd34d;">Frete</span>
                            </div>
                        @endif

                        {{-- Planilha (só marketplaces) --}}
                        @if($precisaPlanilha)
                            @if($planilhaOk)
                                <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(6,78,59,0.35);border:1px solid rgba(34,197,94,0.5);">
                                    <span style="color:#4ade80;font-size:14px;">✓</span>
                                    <span style="font-size:11px;font-weight:600;color:#86efac;">Planilha</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(88,28,135,0.35);border:1px solid rgba(168,85,247,0.5);">
                                    <span style="color:#c084fc;font-size:14px;">✗</span>
                                    <span style="font-size:11px;font-weight:600;color:#d8b4fe;">Planilha</span>
                                </div>
                            @endif
                        @endif

                        {{-- Afiliado (só Shopee) --}}
                        @if($precisaAfiliado)
                            @if($afiliadoOk)
                                <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(6,78,59,0.35);border:1px solid rgba(34,197,94,0.5);">
                                    <span style="color:#4ade80;font-size:14px;">✓</span>
                                    <span style="font-size:11px;font-weight:600;color:#86efac;">Afiliado</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(131,24,67,0.35);border:1px solid rgba(236,72,153,0.5);">
                                    <span style="color:#f472b6;font-size:14px;">✗</span>
                                    <span style="font-size:11px;font-weight:600;color:#fbcfe8;">Afiliado</span>
                                </div>
                            @endif
                        @endif

                        {{-- Custos --}}
                        @if($custoProd > 0)
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(6,78,59,0.35);border:1px solid rgba(34,197,94,0.5);">
                                <span style="color:#4ade80;font-size:14px;">✓</span>
                                <span style="font-size:11px;font-weight:600;color:#86efac;">Custos</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(124,45,18,0.35);border:1px solid rgba(249,115,22,0.5);">
                                <span style="color:#fb923c;font-size:14px;">✗</span>
                                <span style="font-size:11px;font-weight:600;color:#fdba74;">Custos</span>
                            </div>
                        @endif

                        {{-- Imposto --}}
                        @if($imposto > 0)
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(6,78,59,0.35);border:1px solid rgba(34,197,94,0.5);">
                                <span style="color:#4ade80;font-size:14px;">✓</span>
                                <span style="font-size:11px;font-weight:600;color:#86efac;">Imposto</span>
                            </div>
                        @else
                            <div class="flex items-center gap-1.5 rounded-lg" style="padding:6px 12px;background:rgba(124,45,18,0.35);border:1px solid rgba(249,115,22,0.5);">
                                <span style="color:#fb923c;font-size:14px;">✗</span>
                                <span style="font-size:11px;font-weight:600;color:#fdba74;">Imposto</span>
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
                        ? $total - $comissao
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
                    <div class="flex items-center gap-4 px-3 py-2 rounded-lg" style="background:rgba(31,41,55,0.5);border:1px solid rgba(75,85,99,0.6);">
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Total</div>
                            <div class="font-semibold" style="color:#fff;">R$ {{ number_format($total, 2, ',', '.') }}</div>
                        </div>
                        <div style="width:1px;height:24px;background:#4b5563;"></div>
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Subtotal</div>
                            <div class="font-semibold" style="color:#fff;">R$ {{ number_format($totalProd, 2, ',', '.') }}</div>
                        </div>
                        <div style="width:1px;height:24px;background:#4b5563;"></div>
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Frete Recebido</div>
                            <div class="font-semibold" style="color:#fff;">R$ {{ number_format($freteCliente, 2, ',', '.') }}</div>
                        </div>
                        <div style="width:1px;height:24px;background:#4b5563;"></div>
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Custo</div>
                            <div class="font-semibold" style="color:{{ $custoProd > 0 ? '#fff' : '#f97316' }};">R$ {{ number_format($custoProd, 2, ',', '.') }}</div>
                        </div>
                        <div style="width:1px;height:24px;background:#4b5563;"></div>
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Comissão</div>
                            <div class="font-semibold" style="color:#fff;">
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
                        <div style="width:1px;height:24px;background:#4b5563;"></div>
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Imposto</div>
                            <div class="font-semibold" style="color:#fff;">R$ {{ number_format($imposto, 2, ',', '.') }}</div>
                        </div>
                        <div style="width:1px;height:24px;background:#4b5563;"></div>
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Repasse</div>
                            <div class="font-semibold" style="color:#60a5fa;">R$ {{ number_format($repasse + (float)($venda->subsidio_magalu ?? 0), 2, ',', '.') }}</div>
                            @if((float)($venda->subsidio_magalu ?? 0) > 0)
                                <div style="color:#9ca3af;font-size:9px;margin-top:1px;">
                                    {{ number_format($repasse, 2, ',', '.') }} + {{ number_format((float)$venda->subsidio_magalu, 2, ',', '.') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Frete separado --}}
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg" style="background:rgba(31,41,55,0.5);border:1px solid rgba(75,85,99,0.6);">
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Frete (cobrado → {{ $fretePago ? 'pago' : ($custoFrete > 0 ? 'cotado' : '-') }})</div>
                            <div class="font-semibold" style="color:#fff;">
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
                    <div class="flex items-center px-3 py-2 rounded-lg" style="background:rgba(31,41,55,0.5);border:1px solid rgba(75,85,99,0.6);">
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">{{ $isMagaluCard ? 'Desc. Vendedor' : 'Subsídio Pix' }}</div>
                            <div class="font-semibold" style="color:{{ $isMagaluCard ? '#ef4444' : '#60a5fa' }};">R$ {{ number_format($subsidio, 2, ',', '.') }}</div>
                        </div>
                    </div>
                    @endif

                    @php $subsidioMagalu = (float) ($venda->subsidio_magalu ?? 0); @endphp
                    @if($subsidioMagalu > 0)
                    <div class="flex items-center px-3 py-2 rounded-lg" style="background:rgba(31,41,55,0.5);border:1px solid rgba(75,85,99,0.6);">
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Subsídio Magalu</div>
                            <div class="font-semibold" style="color:#10b981;">+R$ {{ number_format($subsidioMagalu, 2, ',', '.') }}</div>
                        </div>
                    </div>
                    @endif

                    {{-- Lucro destacado --}}
                    @php
                        $lucroBgInline = $lucro >= 0 ? 'background:rgba(6,78,59,0.3);border:1px solid rgba(34,197,94,0.4);' : 'background:rgba(127,29,29,0.3);border:1px solid rgba(239,68,68,0.4);';
                        $lucroColorInline = $lucro >= 0 ? 'color:#4ade80;' : 'color:#f87171;';
                        $subsidioMagaluCard = (float) ($venda->subsidio_magalu ?? 0);
                    @endphp
                    <div class="flex items-center px-4 py-2 rounded-lg" style="{{ $lucroBgInline }}">
                        <div>
                            <div style="color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:0.05em;">Lucro</div>
                            <div class="font-bold text-base" style="{{ $lucroColorInline }}">
                                R$ {{ number_format($lucro, 2, ',', '.') }}
                                <span style="font-size:12px;font-weight:normal;">({{ $margemPct }}%)</span>
                            </div>
                            @if($subsidioMagaluCard > 0)
                                <div style="color:#9ca3af;font-size:10px;margin-top:2px;">
                                    📦 {{ number_format($margemProd, 2, ',', '.') }}
                                    🚚 {{ number_format($margemFrete, 2, ',', '.') }}
                                    💰 +{{ number_format($subsidioMagaluCard, 2, ',', '.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Margens detalhadas (horizontal) --}}
                @php
                    $comissaoSobreFrete = (bool) ($venda->canal?->comissao_sobre_frete ?? false);
                    $impostoSobreFrete = (bool) ($venda->canal?->imposto_sobre_frete ?? false);
                    $pctImposto = (float) $venda->percentual_imposto;
                    $isMagaluCard2 = str_contains(strtolower($canal), 'magalu');
                    $comissaoFreteVal = 0;
                    if ($comissaoSobreFrete && $freteCliente > 0 && $venda->canal) {
                        if ($isMagaluCard2) {
                            $baseDist = $totalProd + $freteCliente;
                            if ($baseDist > 0) {
                                $comissaoFreteVal = round($comissao * ($freteCliente / $baseDist), 2);
                            }
                        } else {
                            $regraCanal = $venda->canal->regrasComissao()->where('ativo', true)->first();
                            if ($regraCanal) {
                                $comissaoFreteVal = round($freteCliente * (float) $regraCanal->percentual / 100, 2);
                            }
                        }
                    }
                    $comissaoProdVal = $comissao - $comissaoFreteVal;
                    $impostoFreteVal = ($impostoSobreFrete && $freteCliente > 0 && $pctImposto > 0)
                        ? round($freteCliente * $pctImposto / 100, 2) : 0;
                    $impostoProdVal = $imposto - $impostoFreteVal;
                    $desconto = $total - $totalProd - $freteCliente;
                @endphp
                <div class="flex flex-wrap gap-2 mt-2 text-xs">
                    {{-- Margem Produto --}}
                    @php
                        $margemProdBg = $margemProd >= 0
                            ? 'background:rgba(6,78,59,0.25);border:1px solid rgba(34,197,94,0.4);'
                            : 'background:rgba(127,29,29,0.25);border:1px solid rgba(239,68,68,0.4);';
                        $margemProdColor = $margemProd >= 0 ? 'color:#4ade80;' : 'color:#f87171;';
                    @endphp
                    <div class="flex-1 min-w-[200px] rounded-lg px-3 py-2" style="{{ $margemProdBg }}">
                        <div class="font-semibold" style="{{ $margemProdColor }}">
                            📦 Margem Produto: R$ {{ number_format($margemProd, 2, ',', '.') }}
                            ({{ $totalProd > 0 ? round(($margemProd / $totalProd) * 100, 1) : 0 }}%)
                        </div>
                        <div style="color:#9ca3af;font-size:10px;margin-top:2px;">
                            Sub: {{ number_format($totalProd, 2, ',', '.') }}
                            | Custo: {{ number_format($custoProd, 2, ',', '.') }}
                            | Com: {{ number_format($comissaoProdVal, 2, ',', '.') }}
                            @if($impostoProdVal > 0) | Imp: {{ number_format($impostoProdVal, 2, ',', '.') }} @endif
                        </div>
                    </div>

                    {{-- Margem Frete --}}
                    @if($freteCliente > 0 || $custoFrete > 0)
                    @php
                        $margemFreteBg = $margemFrete >= 0
                            ? 'background:rgba(6,78,59,0.25);border:1px solid rgba(34,197,94,0.4);'
                            : 'background:rgba(127,29,29,0.25);border:1px solid rgba(239,68,68,0.4);';
                        $margemFreteColor = $margemFrete >= 0 ? 'color:#4ade80;' : 'color:#f87171;';
                    @endphp
                    <div class="flex-1 min-w-[200px] rounded-lg px-3 py-2" style="{{ $margemFreteBg }}">
                        <div class="font-semibold" style="{{ $margemFreteColor }}">
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
