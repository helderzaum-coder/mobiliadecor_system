<?php

namespace App\Filament\Pages;

use App\Helpers\TransportadoraHelper;
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
    public ?string $filtro_transportadora = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public bool $incluir_frete_zerado = false;

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
                ->select('bling_id', 'dest_cidade', 'dest_uf', 'transportadora')
                ->get()
                ->keyBy('bling_id');
        }

        // Carregar transportadora do CT-e
        $nfeChaves = $vendas->pluck('nfe_chave_acesso')->filter()->toArray();
        $vendaIds = $vendas->pluck('id_venda')->toArray();
        $cteByNfe = collect();
        $cteByVenda = collect();
        if (!empty($nfeChaves) || !empty($vendaIds)) {
            $ctes = \App\Models\Cte::where(function ($q) use ($nfeChaves, $vendaIds) {
                if (!empty($nfeChaves)) $q->whereIn('chave_nfe', $nfeChaves);
                if (!empty($vendaIds)) $q->orWhereIn('venda_id', $vendaIds);
            })->get();
            $cteByNfe = $ctes->keyBy('chave_nfe');
            $cteByVenda = $ctes->keyBy('venda_id');
        }

        $filename = 'relatorio_fretes_' . now()->format('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($vendas, $stagings, $cteByNfe, $cteByVenda) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            fputcsv($file, [
                'Pedido', 'Canal', 'Data', 'Cliente', 'Cidade', 'UF', 'Transportadora',
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

                $cte = $cteByNfe[$venda->nfe_chave_acesso] ?? $cteByVenda[$venda->id_venda] ?? null;
                $transp = $cte?->transportadora ?? $staging?->transportadora ?? $venda->transportadora_manual ?? '';
                $transp = TransportadoraHelper::resolver($transp) ?? '';

                fputcsv($file, [
                    $venda->numero_pedido_canal,
                    $canal?->nome_canal ?? '-',
                    $venda->data_venda?->format('d/m/Y'),
                    $venda->cliente_nome,
                    $staging?->dest_cidade ?? '',
                    $staging?->dest_uf ?? '',
                    $transp,
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
            Forms\Components\Grid::make(10)->schema([
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
                Forms\Components\Select::make('filtro_transportadora')
                    ->label('Transportadora')
                    ->options(fn () => TransportadoraHelper::listarUnicas())
                    ->placeholder('Todas')
                    ->searchable()
                    ->reactive()
                    ->columnSpan(2),
                Forms\Components\Toggle::make('incluir_frete_zerado')
                    ->label('Incluir frete zerado')
                    ->default(false)
                    ->reactive(),
            ]),
        ]);
    }

    private function buildQuery()
    {
        $query = Venda::with('canal')
            ->where(function ($q) {
                // Pedidos com CT-e vinculado
                $q->whereIn('id_venda', function ($sub) {
                    $sub->select('venda_id')->from('ctes')->whereNotNull('venda_id');
                })->orWhereIn('nfe_chave_acesso', function ($sub) {
                    $sub->select('chave_nfe')->from('ctes')->whereNotNull('chave_nfe');
                })
                // OU frete pago manualmente
                ->orWhere('frete_pago', true);
            })
            ->where(function ($q) {
                // Excluir ME2/FULL (frete custo = 0)
                $q->whereNull('ml_tipo_frete')
                    ->orWhereNotIn('ml_tipo_frete', ['me2', 'full', 'ME2', 'FULL']);
            })
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

        if ($this->filtro_transportadora) {
            $filtro = $this->filtro_transportadora;

            $nomesCte = \App\Models\Cte::whereNotNull('transportadora')
                ->where('transportadora', '!=', '')->distinct()->pluck('transportadora')
                ->filter(fn ($t) => TransportadoraHelper::resolver($t) === $filtro)->values();

            $nomesStaging = \App\Models\PedidoBlingStaging::whereNotNull('transportadora')
                ->where('transportadora', '!=', '')->distinct()->pluck('transportadora')
                ->filter(fn ($t) => TransportadoraHelper::resolver($t) === $filtro)->values();

            $nomesVenda = \App\Models\Venda::whereNotNull('transportadora_manual')
                ->where('transportadora_manual', '!=', '')->distinct()->pluck('transportadora_manual')
                ->filter(fn ($t) => TransportadoraHelper::resolver($t) === $filtro)->values();

            $query->where(function ($q) use ($nomesStaging, $nomesCte, $nomesVenda) {
                if ($nomesStaging->isNotEmpty()) {
                    $q->orWhereIn('bling_id', function ($sub) use ($nomesStaging) {
                        $sub->select('bling_id')->from('pedidos_bling_staging')
                            ->whereIn('transportadora', $nomesStaging);
                    });
                }
                if ($nomesCte->isNotEmpty()) {
                    $q->orWhereIn('nfe_chave_acesso', function ($sub) use ($nomesCte) {
                        $sub->select('chave_nfe')->from('ctes')
                            ->whereIn('transportadora', $nomesCte);
                    })->orWhereIn('id_venda', function ($sub) use ($nomesCte) {
                        $sub->select('venda_id')->from('ctes')
                            ->whereIn('transportadora', $nomesCte)
                            ->whereNotNull('venda_id');
                    });
                }
                if ($nomesVenda->isNotEmpty()) {
                    $q->orWhereIn('transportadora_manual', $nomesVenda);
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
            $query->where('valor_frete_transportadora', '<=', 0);
        } elseif ($this->filtro_frete === 'todos_pagos') {
            $query->where('valor_frete_transportadora', '>', 0);
        }

        if (!$this->incluir_frete_zerado) {
            $query->where('valor_frete_transportadora', '>', 0);
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
                ->select('bling_id', 'dest_cidade', 'dest_uf', 'transportadora')
                ->get()
                ->keyBy('bling_id');

            foreach ($vendas as $venda) {
                $staging = $stagings[$venda->bling_id] ?? null;
                $venda->staging_cidade = $staging?->dest_cidade;
                $venda->staging_uf = $staging?->dest_uf;
                $venda->staging_transportadora = $staging?->transportadora;
            }
        }

        // Carregar transportadora do CT-e (mais confiável)
        $nfeChaves = $vendas->pluck('nfe_chave_acesso')->filter()->toArray();
        $vendaIds = $vendas->pluck('id_venda')->toArray();
        if (!empty($nfeChaves) || !empty($vendaIds)) {
            $ctes = \App\Models\Cte::where(function ($q) use ($nfeChaves, $vendaIds) {
                if (!empty($nfeChaves)) $q->whereIn('chave_nfe', $nfeChaves);
                if (!empty($vendaIds)) $q->orWhereIn('venda_id', $vendaIds);
            })->get();

            // Agrupar por venda para contar múltiplos CT-es
            $ctesByNfe = $ctes->groupBy('chave_nfe');
            $ctesByVenda = $ctes->groupBy('venda_id');

            foreach ($vendas as $venda) {
                $ctesVenda = $ctesByNfe[$venda->nfe_chave_acesso] ?? $ctesByVenda[$venda->id_venda] ?? collect();
                $venda->qtd_ctes = $ctesVenda->count();
                if ($ctesVenda->isNotEmpty()) {
                    $venda->staging_transportadora = $ctesVenda->first()->transportadora ?? $venda->staging_transportadora;
                }
                // Fallback: transportadora_manual da venda
                if (empty($venda->staging_transportadora) && $venda->transportadora_manual) {
                    $venda->staging_transportadora = $venda->transportadora_manual;
                }
            }
        }

        // Normalizar nomes para exibição uniforme
        foreach ($vendas as $venda) {
            if (empty($venda->staging_transportadora) && $venda->transportadora_manual) {
                $venda->staging_transportadora = $venda->transportadora_manual;
            }
            $venda->staging_transportadora = TransportadoraHelper::resolver($venda->staging_transportadora);
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
