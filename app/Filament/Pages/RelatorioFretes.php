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
    public ?string $filtro_uf = null;
    public ?string $filtro_cidade = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;

    public function mount(): void
    {
        $this->mes_selecionado = now()->format('Y-m');
    }

    public function exportar()
    {
        $vendas = $this->buildQuery()->limit(5000)->get();

        // Carregar cidade/UF do staging
        $blingIds = $vendas->pluck('bling_id')->filter()->toArray();
        $stagings = [];
        if (!empty($blingIds)) {
            $stagings = \App\Models\PedidoBlingStaging::whereIn('bling_id', $blingIds)
                ->select('bling_id', 'dest_cidade', 'dest_uf')
                ->get()
                ->keyBy('bling_id');
        }

        $filename = 'relatorio_fretes_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($vendas, $stagings) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($file, [
                'Pedido', 'Canal', 'Data', 'Cliente', 'Cidade', 'UF',
                'Frete Cobrado', 'Frete Cotado', 'Frete Pago',
                'Comissão Frete', 'Imposto Frete', 'Margem Frete',
            ], ';');

            foreach ($vendas as $venda) {
                $staging = $stagings[$venda->bling_id] ?? null;
                $canal = $venda->canal;
                $cobrado = (float) $venda->valor_frete_cliente;

                $comissaoFrete = 0;
                if ($canal && (bool) ($canal->comissao_sobre_frete ?? false) && $cobrado > 0) {
                    $regra = $canal->regrasComissao()->where('ativo', true)->first();
                    if ($regra) $comissaoFrete = round($cobrado * (float) $regra->percentual / 100, 2);
                }
                $impostoFrete = 0;
                if ($canal && (bool) ($canal->imposto_sobre_frete ?? false) && $cobrado > 0 && (float) $venda->percentual_imposto > 0) {
                    $impostoFrete = round($cobrado * (float) $venda->percentual_imposto / 100, 2);
                }

                fputcsv($file, [
                    $venda->numero_pedido_canal,
                    $canal?->nome_canal ?? '-',
                    $venda->data_venda?->format('d/m/Y'),
                    $venda->cliente_nome,
                    $staging?->dest_cidade ?? '',
                    $staging?->dest_uf ?? '',
                    number_format((float) $venda->valor_frete_cliente, 2, ',', ''),
                    number_format((float) ($venda->frete_cotado ?? 0), 2, ',', ''),
                    number_format((float) $venda->valor_frete_transportadora, 2, ',', ''),
                    number_format($comissaoFrete, 2, ',', ''),
                    number_format($impostoFrete, 2, ',', ''),
                    number_format((float) $venda->margem_frete, 2, ',', ''),
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(9)->schema([
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
                Forms\Components\Select::make('filtro_uf')
                    ->label('Estado')
                    ->options(fn () => \App\Models\PedidoBlingStaging::whereNotNull('dest_uf')
                        ->where('dest_uf', '!=', '')
                        ->distinct()
                        ->orderBy('dest_uf')
                        ->pluck('dest_uf', 'dest_uf')
                        ->toArray())
                    ->placeholder('Todos')
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(fn ($set) => $set('filtro_cidade', null)),
                Forms\Components\Select::make('filtro_cidade')
                    ->label('Cidade')
                    ->options(function ($get) {
                        $query = \App\Models\PedidoBlingStaging::whereNotNull('dest_cidade')
                            ->where('dest_cidade', '!=', '');
                        if ($get('filtro_uf')) {
                            $query->where('dest_uf', $get('filtro_uf'));
                        }
                        return $query->distinct()->orderBy('dest_cidade')->pluck('dest_cidade', 'dest_cidade')->toArray();
                    })
                    ->placeholder('Todas')
                    ->searchable()
                    ->reactive()
                    ->visible(fn ($get) => filled($get('filtro_uf'))),
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

        // Filtro por UF/Cidade (dados no staging)
        if ($this->filtro_uf) {
            $query->whereIn('bling_id', function ($sub) {
                $sub->select('bling_id')->from('pedidos_bling_staging')
                    ->where('dest_uf', $this->filtro_uf);
                if ($this->filtro_cidade) {
                    $sub->where('dest_cidade', $this->filtro_cidade);
                }
            });
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
        $totalMargemFrete = (clone $query)->sum('margem_frete');
        $count = (clone $query)->count();

        $comPrejuizo = (clone $query)->where('margem_frete', '<', 0)->count();
        $totalPrejuizo = abs((float) ((clone $query)->where('margem_frete', '<', 0)->sum('margem_frete')));

        $acimaCotado = (clone $query)->whereNotNull('frete_cotado')->where('frete_cotado', '>', 0)
            ->whereRaw('valor_frete_transportadora > frete_cotado')->count();

        // Calcular comissão e imposto sobre frete (aproximado via margem)
        $totalComissaoFrete = (float) $totalCobrado - (float) $totalPago - (float) $totalMargemFrete;
        // Detalhar: buscar vendas com canal que cobra comissão/imposto sobre frete
        $vendasComFrete = (clone $query)->get();
        $somaComissaoFrete = 0;
        $somaImpostoFrete = 0;
        foreach ($vendasComFrete as $v) {
            $canal = $v->canal;
            $cobrado = (float) $v->valor_frete_cliente;
            if (!$canal || $cobrado <= 0) continue;

            if ((bool) ($canal->comissao_sobre_frete ?? false)) {
                $regra = $canal->regrasComissao()->where('ativo', true)->first();
                if ($regra) {
                    $somaComissaoFrete += round($cobrado * (float) $regra->percentual / 100, 2);
                }
            }
            if ((bool) ($canal->imposto_sobre_frete ?? false) && (float) $v->percentual_imposto > 0) {
                $somaImpostoFrete += round($cobrado * (float) $v->percentual_imposto / 100, 2);
            }
        }

        return [
            'count' => $count,
            'total_cobrado' => (float) $totalCobrado,
            'total_pago' => (float) $totalPago,
            'total_cotado' => (float) $totalCotado,
            'margem_frete' => (float) $totalMargemFrete,
            'com_prejuizo' => $comPrejuizo,
            'total_prejuizo' => $totalPrejuizo,
            'acima_cotado' => $acimaCotado,
            'comissao_frete' => $somaComissaoFrete,
            'imposto_frete' => $somaImpostoFrete,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
