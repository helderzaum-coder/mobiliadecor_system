<?php

namespace App\Filament\Pages;

use App\Models\Venda;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class DashboardVendas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Dashboard Vendas';
    protected static ?string $title = 'Dashboard de Vendas';
    protected static string $view = 'filament.pages.dashboard-vendas';
    protected static ?int $navigationSort = 0;

    public ?string $periodo = 'este_mes';
    public ?string $mes_selecionado = null;
    public ?string $canal = null;
    public ?string $conta = null;
    public ?string $status_filtro = null;
    public ?string $busca_pedido = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public int $pagina = 1;
    public int $porPagina = 20;

    public function mount(): void
    {
        $this->mes_selecionado = now()->format('Y-m');
    }

    // ---- Ações ----

    public function buscarNfe(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::buscarNfe($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function buscarCte(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::buscarCte($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function aplicarPlanilhaML(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::aplicarPlanilhaML($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function aplicarPlanilhaShopee(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::aplicarPlanilhaShopee($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function recalcular(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        \App\Services\VendaRecalculoService::recalcularMargens($venda);
        \Filament\Notifications\Notification::make()->title('Margens recalculadas.')->success()->send();
    }

    public function buscarCustos(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;

        $staging = \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
        if (!$staging) {
            \Filament\Notifications\Notification::make()->title('Staging não encontrado.')->warning()->send();
            return;
        }

        $atualizados = \App\Services\Bling\BlingImportService::buscarCustosProdutos($staging);

        if ($atualizados > 0) {
            // Recalcular custo total na venda
            $custoProdutos = 0;
            foreach ($staging->fresh()->itens ?? [] as $item) {
                $custoProdutos += ((float) ($item['custo'] ?? 0)) * ((int) ($item['quantidade'] ?? 1));
            }
            $venda->update(['custo_produtos' => round($custoProdutos, 2)]);
            \App\Services\VendaRecalculoService::recalcularMargens($venda);
            \Filament\Notifications\Notification::make()->title("{$atualizados} custo(s) atualizado(s). Margens recalculadas.")->success()->send();
        } else {
            \Filament\Notifications\Notification::make()->title('Nenhum custo encontrado no Bling.')->warning()->send();
        }
    }

    public function buscarNfeLote(): void
    {
        $vendas = $this->buildQuery()
            ->where(fn ($q) => $q->whereNull('nfe_chave_acesso')->orWhere('nfe_chave_acesso', ''))
            ->get();

        $ok = 0; $falha = 0;
        foreach ($vendas as $venda) {
            $result = \App\Services\VendaRecalculoService::buscarNfe($venda);
            $result['success'] ? $ok++ : $falha++;
        }

        \Filament\Notifications\Notification::make()
            ->title("NF-e em lote: {$ok} encontrada(s), {$falha} não encontrada(s)")
            ->{$ok > 0 ? 'success' : 'warning'}()->send();
    }

    public function buscarCteLote(): void
    {
        $vendas = $this->buildQuery()
            ->where('frete_pago', false)
            ->where(fn ($q) => $q->whereNotNull('nfe_chave_acesso')->where('nfe_chave_acesso', '!=', ''))
            ->where(fn ($q) => $q->whereNull('ml_tipo_frete')->orWhere('ml_tipo_frete', 'ME1'))
            ->get();

        $ok = 0; $falha = 0;
        foreach ($vendas as $venda) {
            $result = \App\Services\VendaRecalculoService::buscarCte($venda);
            $result['success'] ? $ok++ : $falha++;
        }

        \Filament\Notifications\Notification::make()
            ->title("CT-e em lote: {$ok} encontrado(s), {$falha} não encontrado(s)")
            ->{$ok > 0 ? 'success' : 'warning'}()->send();
    }

    public function buscarCustosLote(): void
    {
        $vendas = $this->buildQuery()
            ->where(fn ($q) => $q->where('custo_produtos', '<=', 0)->orWhereNull('custo_produtos'))
            ->get();

        $ok = 0; $falha = 0;
        foreach ($vendas as $venda) {
            $staging = \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
            if (!$staging) { $falha++; continue; }

            $atualizados = \App\Services\Bling\BlingImportService::buscarCustosProdutos($staging);
            if ($atualizados > 0) {
                $custoProdutos = 0;
                foreach ($staging->fresh()->itens ?? [] as $item) {
                    $custoProdutos += ((float) ($item['custo'] ?? 0)) * ((int) ($item['quantidade'] ?? 1));
                }
                $venda->update(['custo_produtos' => round($custoProdutos, 2)]);
                \App\Services\VendaRecalculoService::recalcularMargens($venda);
                $ok++;
            } else {
                $falha++;
            }
        }

        \Filament\Notifications\Notification::make()
            ->title("Custos em lote: {$ok} atualizado(s), {$falha} sem custo")
            ->{$ok > 0 ? 'success' : 'warning'}()->send();
    }

    public function paginaAnterior(): void
    {
        if ($this->pagina > 1) $this->pagina--;
    }

    public function proximaPagina(): void
    {
        $this->pagina++;
    }

    public function updatedPeriodo(): void { $this->pagina = 1; }
    public function updatedMesSelecionado(): void { $this->pagina = 1; }
    public function updatedCanal(): void { $this->pagina = 1; }
    public function updatedConta(): void { $this->pagina = 1; }
    public function updatedStatusFiltro(): void { $this->pagina = 1; }
    public function updatedBuscaPedido(): void { $this->pagina = 1; }
    public function updatedDataInicio(): void { $this->pagina = 1; }
    public function updatedDataFim(): void { $this->pagina = 1; }

    // ---- Form ----

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(8)->schema([
                Forms\Components\TextInput::make('busca_pedido')
                    ->label('Pedido')
                    ->placeholder('Nº pedido...')
                    ->reactive()
                    ->debounce(400),
                Forms\Components\Select::make('periodo')
                    ->label('Período')
                    ->options([
                        'hoje' => 'Hoje',
                        'esta_semana' => 'Esta semana',
                        'este_mes' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'selecionar_mes' => 'Selecionar mês',
                        'customizado' => 'Período customizado',
                    ])
                    ->reactive(),
                Forms\Components\Select::make('mes_selecionado')
                    ->label('Mês')
                    ->options(function () {
                        $options = [];
                        for ($i = 0; $i < 12; $i++) {
                            $d = now()->subMonths($i)->startOfMonth();
                            $options[$d->format('Y-m')] = ucfirst($d->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
                        }
                        return $options;
                    })
                    ->visible(fn ($get) => $get('periodo') === 'selecionar_mes')
                    ->reactive(),
                Forms\Components\DatePicker::make('data_inicio')
                    ->label('De')
                    ->displayFormat('d/m/Y')
                    ->visible(fn ($get) => $get('periodo') === 'customizado')
                    ->reactive(),
                Forms\Components\DatePicker::make('data_fim')
                    ->label('Até')
                    ->displayFormat('d/m/Y')
                    ->visible(fn ($get) => $get('periodo') === 'customizado')
                    ->reactive(),
                Forms\Components\Select::make('canal')
                    ->label('Canal')
                    ->options(fn () => \App\Models\CanalVenda::orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray())
                    ->placeholder('Todos')
                    ->reactive(),
                Forms\Components\Select::make('conta')
                    ->label('Conta')
                    ->options(['primary' => 'Mobilia Decor', 'secondary' => 'HES Móveis'])
                    ->placeholder('Todas')
                    ->reactive(),
                Forms\Components\Select::make('status_filtro')
                    ->label('Status')
                    ->options([
                        'falta_nfe' => '⚠ Falta NF-e',
                        'falta_frete' => '🚚 Falta Frete',
                        'falta_planilha' => '📊 Falta Planilha',
                        'sem_custo' => '💰 Sem Custo Produto',
                        'completo' => '✅ Completo',
                    ])
                    ->placeholder('Todos')
                    ->reactive(),
            ]),
        ]);
    }

    // ---- Dados ----

    private function buildQuery()
    {
        $query = Venda::with('canal')->orderBy('data_venda', 'desc');

        $query = match ($this->periodo) {
            'hoje' => $query->whereDate('data_venda', today()),
            'esta_semana' => $query->whereBetween('data_venda', [now()->startOfWeek(), now()->endOfWeek()]),
            'este_mes' => $query->whereBetween('data_venda', [now()->startOfMonth(), now()->endOfMonth()]),
            'mes_passado' => $query->whereBetween('data_venda', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
            'selecionar_mes' => $this->mes_selecionado
                ? $query->whereBetween('data_venda', [
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->startOfMonth(),
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->endOfMonth(),
                ])
                : $query,
            'customizado' => $query
                ->when($this->data_inicio, fn ($q) => $q->whereDate('data_venda', '>=', $this->data_inicio))
                ->when($this->data_fim, fn ($q) => $q->whereDate('data_venda', '<=', $this->data_fim)),
            default => $query,
        };

        if ($this->busca_pedido) {
            $query->where('numero_pedido_canal', 'like', '%' . $this->busca_pedido . '%');
        }
        if ($this->canal) {
            $query->whereHas('canal', fn ($q) => $q->where('nome_canal', $this->canal));
        }
        if ($this->conta) {
            $query->where('bling_account', $this->conta);
        }

        // Filtro de status
        if ($this->status_filtro === 'falta_nfe') {
            $query->where(fn ($q) => $q->whereNull('nfe_chave_acesso')->orWhere('nfe_chave_acesso', ''));
        } elseif ($this->status_filtro === 'falta_frete') {
            // Falta frete: frete_pago=false, EXCETO ML ME2/FULL (não precisam de frete)
            $query->where('frete_pago', false)
                ->where(fn ($q) => $q
                    ->whereNull('ml_tipo_frete')
                    ->orWhere('ml_tipo_frete', 'ME1')
                );
        } elseif ($this->status_filtro === 'falta_planilha') {
            $query->where('planilha_processada', false)
                ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%')->orWhere('nome_canal', 'like', '%ercado%')->orWhere('nome_canal', 'like', '%agalu%')->orWhere('nome_canal', 'like', '%ebcontinental%'));
        } elseif ($this->status_filtro === 'sem_custo') {
            $query->where(fn ($q) => $q->where('custo_produtos', '<=', 0)->orWhereNull('custo_produtos'));
        } elseif ($this->status_filtro === 'completo') {
            // Completo: tem NF-e + (frete_pago OU ML ME2/FULL) + planilha ok
            $query->where(fn ($q) => $q->whereNotNull('nfe_chave_acesso')->where('nfe_chave_acesso', '!=', ''))
                ->where(fn ($q) => $q
                    ->where('frete_pago', true)
                    ->orWhereIn('ml_tipo_frete', ['ME2', 'FULL'])
                )
                ->where(fn ($q) => $q
                    ->where('planilha_processada', true)
                    ->orWhereDoesntHave('canal', fn ($q2) => $q2->where('nome_canal', 'like', '%hopee%')->orWhere('nome_canal', 'like', '%ercado%')->orWhere('nome_canal', 'like', '%agalu%')->orWhere('nome_canal', 'like', '%ebcontinental%'))
                );
        }

        return $query;
    }

    public function getVendasProperty(): \Illuminate\Support\Collection
    {
        $vendas = $this->buildQuery()
            ->skip(($this->pagina - 1) * $this->porPagina)
            ->take($this->porPagina)
            ->get();

        // Carregar itens e documento do staging
        $blingIds = $vendas->pluck('bling_id')->filter()->toArray();
        if (!empty($blingIds)) {
            $stagings = \App\Models\PedidoBlingStaging::whereIn('bling_id', $blingIds)
                ->select('bling_id', 'itens', 'cliente_documento')
                ->get()
                ->keyBy('bling_id');

            foreach ($vendas as $venda) {
                $staging = $stagings[$venda->bling_id] ?? null;
                $venda->staging_itens = $staging?->itens ?? [];
                if (empty($venda->cliente_documento) && !empty($staging?->cliente_documento)) {
                    $venda->cliente_documento = $staging->cliente_documento;
                }
            }
        }

        return $vendas;
    }

    public function getTotaisProperty(): array
    {
        $query = $this->buildQuery();
        $total = (clone $query)->sum('valor_total_venda');
        $lucro = (clone $query)->sum('margem_venda_total');
        $count = (clone $query)->count();
        $comLucro = (clone $query)->where('margem_venda_total', '>=', 0)->count();
        $comPrejuizo = $count - $comLucro;

        return [
            'qtd' => $count,
            'total' => $total,
            'lucro' => $lucro,
            'margem' => $total > 0 ? round(($lucro / $total) * 100, 1) : 0,
            'com_lucro' => $comLucro,
            'com_prejuizo' => $comPrejuizo,
        ];
    }

    public function getTotalPaginasProperty(): int
    {
        return max(1, (int) ceil($this->totais['qtd'] / $this->porPagina));
    }

    public function getGraficoVendasDiariasProperty(): array
    {
        $rows = $this->buildQuery()
            ->reorder()
            ->selectRaw('DATE(data_venda) as dia, SUM(valor_total_venda) as faturamento, SUM(margem_venda_total) as lucro, COUNT(*) as qtd')
            ->groupByRaw('DATE(data_venda)')
            ->orderBy('dia')
            ->get();

        return [
            'labels' => $rows->pluck('dia')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))->toArray(),
            'faturamento' => $rows->pluck('faturamento')->map(fn ($v) => round((float) $v, 2))->toArray(),
            'lucro' => $rows->pluck('lucro')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    public function getVendasPorCanalProperty(): array
    {
        return $this->buildQuery()
            ->reorder()
            ->selectRaw('COALESCE(canal_nome, "Outros") as canal, COUNT(*) as qtd, SUM(valor_total_venda) as total, SUM(margem_venda_total) as lucro')
            ->groupBy('canal_nome')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'canal' => $r->canal,
                'qtd' => $r->qtd,
                'total' => round((float) $r->total, 2),
                'lucro' => round((float) $r->lucro, 2),
            ])
            ->toArray();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
