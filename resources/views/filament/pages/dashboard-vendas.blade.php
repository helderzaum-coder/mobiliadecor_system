<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    {{-- Resumo horizontal compacto --}}
    @php
        $totais = $this->totais;
        $grafico = $this->graficoVendasDiarias;
        $porCanal = $this->vendasPorCanal;
        $ticketMedio = $totais['qtd'] > 0 ? $totais['total'] / $totais['qtd'] : 0;
    @endphp

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-md p-5 border-t-4 border-blue-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-blue-500 uppercase tracking-wide">Vendas</span>
                <span class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-500">🛒</span>
            </div>
            <div class="text-3xl font-extrabold text-gray-800 dark:text-white">{{ $totais['qtd'] }}</div>
            <div class="text-xs text-gray-400 mt-1">pedidos no período</div>
        </div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-md p-5 border-t-4 border-indigo-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-indigo-500 uppercase tracking-wide">Faturamento</span>
                <span class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-500">💰</span>
            </div>
            <div class="text-3xl font-extrabold text-gray-800 dark:text-white">R$ {{ number_format($totais['total'], 2, ',', '.') }}</div>
            <div class="text-xs text-gray-400 mt-1">ticket médio R$ {{ number_format($ticketMedio, 2, ',', '.') }}</div>
        </div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-md p-5 border-t-4 {{ $totais['lucro'] >= 0 ? 'border-green-500' : 'border-red-500' }}">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold {{ $totais['lucro'] >= 0 ? 'text-green-500' : 'text-red-500' }} uppercase tracking-wide">Lucro</span>
                <span class="w-8 h-8 rounded-lg {{ $totais['lucro'] >= 0 ? 'bg-green-100 dark:bg-green-900/40 text-green-500' : 'bg-red-100 dark:bg-red-900/40 text-red-500' }} flex items-center justify-center">📈</span>
            </div>
            <div class="text-3xl font-extrabold {{ $totais['lucro'] >= 0 ? 'text-green-600' : 'text-red-600' }}">R$ {{ number_format($totais['lucro'], 2, ',', '.') }}</div>
            <div class="text-xs text-gray-400 mt-1">margem {{ $totais['margem'] }}%</div>
        </div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-md p-5 border-t-4 border-emerald-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-emerald-500 uppercase tracking-wide">Com Lucro</span>
                <span class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center text-emerald-500">✅</span>
            </div>
            <div class="text-3xl font-extrabold text-emerald-600">{{ $totais['com_lucro'] }}</div>
            <div class="text-xs text-gray-400 mt-1">{{ $totais['qtd'] > 0 ? round(($totais['com_lucro'] / $totais['qtd']) * 100, 1) : 0 }}% do total</div>
        </div>
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-md p-5 border-t-4 border-red-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-red-500 uppercase tracking-wide">Com Prejuízo</span>
                <span class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center text-red-500">⚠️</span>
            </div>
            <div class="text-3xl font-extrabold text-red-600">{{ $totais['com_prejuizo'] }}</div>
            <div class="text-xs text-gray-400 mt-1">{{ $totais['qtd'] > 0 ? round(($totais['com_prejuizo'] / $totais['qtd']) * 100, 1) : 0 }}% do total</div>
        </div>
    </div>

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

                // Status da venda
                $temNfeChave = !empty($venda->nfe_chave_acesso);
                $fretePagoFlag = (bool) $venda->frete_pago;
                $planilhaOk = (bool) $venda->planilha_processada;
                $isML = str_contains(strtolower($canal), 'mercado');
                $isShopee = str_contains(strtolower($canal), 'shopee');
                $precisaPlanilha = $isML || $isShopee;
                $completo = $temNfeChave && $fretePagoFlag && (!$precisaPlanilha || $planilhaOk);

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
                        @if($completo)
                            <span style="background:#059669;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">✅ Completo</span>
                        @else
                            @if(!$temNfeChave)
                                <span style="background:#dc2626;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">Falta NF-e</span>
                            @endif
                            @if(!$fretePagoFlag && !$isML)
                                <span style="background:#d97706;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">Falta Frete</span>
                            @endif
                            @if($precisaPlanilha && !$planilhaOk)
                                <span style="background:#7c3aed;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">Falta Planilha</span>
                            @endif
                        @endif
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
                        <div class="text-gray-500">Repasse</div>
                        <div class="font-semibold text-blue-600 dark:text-blue-400">R$ {{ number_format($totalProd + $freteCliente - $comissao, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        @php
                            $fretePago = (bool) $venda->frete_pago;
                            $freteCotado = (float) ($venda->frete_cotado ?? 0);
                        @endphp
                        <div class="text-gray-500">Frete (cobrado → {{ $fretePago ? 'pago' : ($custoFrete > 0 ? 'cotado' : '-') }})</div>
                        <div class="font-semibold text-gray-800 dark:text-white">
                            R$ {{ number_format($freteCliente, 2, ',', '.') }} → R$ {{ number_format($custoFrete, 2, ',', '.') }}
                            @if($fretePago && $freteCotado > 0 && $freteCotado != $custoFrete)
                                <span style="color:#6b7280;font-size:10px;">(cotado: R$ {{ number_format($freteCotado, 2, ',', '.') }})</span>
                            @endif
                            @if($custoFrete > 0 && !$fretePago)
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
                    $fretePagoReal = (bool) $venda->frete_pago;
                    $temPlanilha = (bool) $venda->planilha_processada;
                    $isMarketplace = $isML || $isShopee;
                @endphp
                <div class="flex flex-wrap gap-2 mt-3">
                    @if(!$temNfe)
                        <button wire:click="buscarNfe({{ $venda->id_venda }})" wire:loading.attr="disabled"
                            style="background:#2563eb;color:#fff;padding:3px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                            📄 Buscar NF-e
                        </button>
                    @endif
                    @if($temNfe && !$fretePagoReal && !$isML)
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
        document.addEventListener('livewire:navigated', () => initDashboardCharts());
        document.addEventListener('livewire:init', () => {
            initDashboardCharts();
            Livewire.hook('morph.updated', () => setTimeout(initDashboardCharts, 100));
        });

        let chartBar = null, chartDonut = null;

        function initDashboardCharts() {
            const barEl = document.getElementById('chartVendasDiarias');
            const donutEl = document.getElementById('chartCanais');
            if (!barEl || !donutEl) return;

            const grafico = @json($grafico);
            const porCanal = @json($porCanal);
            const cores = ['#3b82f6','#f59e0b','#10b981','#8b5cf6','#ef4444','#06b6d4','#ec4899','#6366f1'];
            const isDark = document.documentElement.classList.contains('dark');
            const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            if (chartBar) chartBar.destroy();
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

            if (chartDonut) chartDonut.destroy();
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
        }
    </script>
</x-filament-panels::page>
