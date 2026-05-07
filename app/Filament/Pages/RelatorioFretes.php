<?php

namespace App\Filament\Pages;

use App\Models\Venda;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class RelatorioFretes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Relatório de Fretes';
    protected static ?string $title = 'Relatório de Fretes';
    protected static string $view = 'filament.pages.relatorio-fretes';
    protected static ?int $navigationSort = 2;

    public ?string $periodo = 'este_mes';
    public ?string $mes_selecionado = null;
    public ?string $canal = null;
    public ?string $conta = null;
    public ?string $filtro_frete = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;

    public function mount(): void
    {
        $this->mes_selecionado = now()->format('Y-m');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(7)->schema([
                Forms\Components\Select::make('periodo')
                    ->label('Período')
                    ->options([
                        'este_mes' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'selecionar_mes' => 'Selecionar mês',
                        'customizado' => 'Customizado',
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
                Forms\Components\Select::make('filtro_frete')
                    ->label('Filtro Frete')
                    ->options([
                        'prejuizo' => '🔴 Prejuízo (pago > cobrado)',
                        'acima_cotado' => '🟡 Acima do cotado',
                        'sem_frete' => '⚪ Sem frete pago',
                        'todos_pagos' => '✅ Todos com frete pago',
                    ])
                    ->placeholder('Todos')
                    ->reactive(),
            ]),
        ]);
    }

    private function buildQuery()
    {
        $query = Venda::with('canal')
            ->where('frete_pago', true)
            ->where('valor_frete_transportadora', '>', 0)
            ->orderByRaw('(valor_frete_transportadora - valor_frete_cliente) DESC');

        $query = match ($this->periodo) {
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

        if ($this->canal) {
            $query->whereHas('canal', fn ($q) => $q->where('nome_canal', $this->canal));
        }
        if ($this->conta) {
            $query->where('bling_account', $this->conta);
        }

        if ($this->filtro_frete === 'prejuizo') {
            $query->whereRaw('valor_frete_transportadora > valor_frete_cliente');
        } elseif ($this->filtro_frete === 'acima_cotado') {
            $query->whereNotNull('frete_cotado')
                ->where('frete_cotado', '>', 0)
                ->whereRaw('valor_frete_transportadora > frete_cotado');
        } elseif ($this->filtro_frete === 'sem_frete') {
            $query->where('frete_pago', false)->orWhere('valor_frete_transportadora', '<=', 0);
        } elseif ($this->filtro_frete === 'todos_pagos') {
            // já filtrado acima
        }

        return $query;
    }

    public function getVendasProperty(): \Illuminate\Support\Collection
    {
        $vendas = $this->buildQuery()->limit(100)->get();

        // Carregar cidade/UF do staging
        $blingIds = $vendas->pluck('bling_id')->filter()->toArray();
        if (!empty($blingIds)) {
            $stagings = \App\Models\PedidoBlingStaging::whereIn('bling_id', $blingIds)
                ->select('bling_id', 'dest_cidade', 'dest_uf')
                ->get()
                ->keyBy('bling_id');

            foreach ($vendas as $venda) {
                $staging = $stagings[$venda->bling_id] ?? null;
                $venda->staging_cidade = $staging?->dest_cidade;
                $venda->staging_uf = $staging?->dest_uf;
            }
        }

        return $vendas;
    }

    public function getResumoProperty(): array
    {
        $query = $this->buildQuery();

        $totalCobrado = (clone $query)->sum('valor_frete_cliente');
        $totalPago = (clone $query)->sum('valor_frete_transportadora');
        $totalCotado = (clone $query)->whereNotNull('frete_cotado')->where('frete_cotado', '>', 0)->sum('frete_cotado');
        $count = (clone $query)->count();

        $comPrejuizo = (clone $query)->whereRaw('valor_frete_transportadora > valor_frete_cliente')->count();
        $totalPrejuizo = (clone $query)->whereRaw('valor_frete_transportadora > valor_frete_cliente')
            ->selectRaw('SUM(valor_frete_transportadora - valor_frete_cliente) as prejuizo')
            ->value('prejuizo') ?? 0;

        $acimaCotado = (clone $query)->whereNotNull('frete_cotado')->where('frete_cotado', '>', 0)
            ->whereRaw('valor_frete_transportadora > frete_cotado')->count();

        return [
            'count' => $count,
            'total_cobrado' => (float) $totalCobrado,
            'total_pago' => (float) $totalPago,
            'total_cotado' => (float) $totalCotado,
            'margem_frete' => (float) $totalCobrado - (float) $totalPago,
            'com_prejuizo' => $comPrejuizo,
            'total_prejuizo' => (float) $totalPrejuizo,
            'acima_cotado' => $acimaCotado,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
