<?php

namespace App\Filament\Pages;

use App\Models\CanalVenda;
use App\Models\Cnpj;
use App\Models\Venda;
use App\Services\DreService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DreLancamentos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'DRE Lançamentos';
    protected static ?string $title = 'Lançamentos DRE por Venda';
    protected static string $view = 'filament.pages.dre-lancamentos';
    protected static ?int $navigationSort = 3;

    protected $queryString = [
        'periodo'          => ['except' => 'este_mes', 'as' => 'periodo'],
        'dia_selecionado'  => ['except' => '', 'as' => 'dia'],
        'mes_selecionado'  => ['except' => '', 'as' => 'mes'],
        'data_inicio'      => ['except' => '', 'as' => 'de'],
        'data_fim'         => ['except' => '', 'as' => 'ate'],
        'cnpj_id'          => ['except' => '', 'as' => 'cnpj'],
        'canal'            => ['except' => '', 'as' => 'canal'],
        'status_dre'       => ['except' => '', 'as' => 'status'],
        'pagina'           => ['except' => 1, 'as' => 'pag'],
    ];

    public ?string $periodo = 'este_mes';
    public ?string $dia_selecionado = null;
    public ?string $mes_selecionado = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public ?string $cnpj_id = null;
    public ?string $canal = null;
    public ?string $status_dre = null;
    public int $pagina = 1;
    public int $porPagina = 30;

    public function mount(): void
    {
        if (empty($this->mes_selecionado)) {
            $this->mes_selecionado = now()->format('Y-m');
        }
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(6)->schema([
                Forms\Components\Select::make('periodo')
                    ->label('Período')
                    ->options([
                        'hoje' => 'Hoje',
                        'dia_especifico' => 'Dia específico',
                        'esta_semana' => 'Esta semana',
                        'este_mes' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'selecionar_mes' => 'Selecionar mês',
                        'customizado' => 'Período customizado',
                    ])
                    ->reactive(),
                Forms\Components\DatePicker::make('dia_selecionado')
                    ->label('Dia')
                    ->displayFormat('d/m/Y')
                    ->visible(fn ($get) => $get('periodo') === 'dia_especifico')
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
                Forms\Components\Select::make('cnpj_id')
                    ->label('CNPJ')
                    ->options(fn () => Cnpj::where('ativo', true)->get()->mapWithKeys(fn ($c) => [$c->id_cnpj => $c->razao_social])->toArray())
                    ->placeholder('Todos')
                    ->reactive(),
                Forms\Components\Select::make('canal')
                    ->label('Canal')
                    ->options(fn () => CanalVenda::where('ativo', true)->orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray())
                    ->placeholder('Todos')
                    ->reactive(),
                Forms\Components\Select::make('status_dre')
                    ->label('Status DRE')
                    ->options([
                        'pendente' => '⏳ Pendente',
                        'lancado' => '🔒 Lançado',
                    ])
                    ->placeholder('Todos')
                    ->reactive(),
            ]),
        ]);
    }

    private function getDataRange(): array
    {
        return match ($this->periodo) {
            'hoje' => [today()->toDateString(), today()->toDateString()],
            'dia_especifico' => $this->dia_selecionado
                ? [$this->dia_selecionado, $this->dia_selecionado]
                : [today()->toDateString(), today()->toDateString()],
            'esta_semana' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'este_mes' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'mes_passado' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'selecionar_mes' => $this->mes_selecionado
                ? [
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->startOfMonth()->toDateString(),
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->endOfMonth()->toDateString(),
                ]
                : [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'customizado' => [
                $this->data_inicio ?? now()->startOfMonth()->toDateString(),
                $this->data_fim ?? now()->endOfMonth()->toDateString(),
            ],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    private function buildQuery()
    {
        [$inicio, $fim] = $this->getDataRange();

        $query = Venda::with('canal')
            ->whereBetween('data_venda', [$inicio, $fim])
            ->where(fn ($q) => $q->where('cancelada', false)->orWhereNull('cancelada'))
            ->orderBy('data_venda', 'desc');

        if ($this->cnpj_id) {
            $query->where('id_cnpj', (int) $this->cnpj_id);
        }
        if ($this->canal) {
            $query->where('canal_nome', $this->canal);
        }
        if ($this->status_dre === 'pendente') {
            $query->where(fn ($q) => $q->where('dre_lancado', false)->orWhereNull('dre_lancado'));
        } elseif ($this->status_dre === 'lancado') {
            $query->where('dre_lancado', true);
        }

        return $query;
    }

    public function getVendasProperty(): \Illuminate\Support\Collection
    {
        return $this->buildQuery()
            ->skip(($this->pagina - 1) * $this->porPagina)
            ->take($this->porPagina)
            ->get();
    }

    public function getTotaisProperty(): array
    {
        $query = $this->buildQuery();
        $total = (clone $query)->count();
        $lancados = (clone $query)->where('dre_lancado', true)->count();
        $pendentes = $total - $lancados;

        return [
            'total' => $total,
            'lancados' => $lancados,
            'pendentes' => $pendentes,
        ];
    }

    public function getTotalPaginasProperty(): int
    {
        return max(1, (int) ceil($this->totais['total'] / $this->porPagina));
    }

    public ?int $editandoId = null;
    public ?string $edit_total_produtos = null;
    public ?string $edit_valor_frete_cliente = null;
    public ?string $edit_valor_imposto = null;
    public ?string $edit_valor_frete_transportadora = null;
    public ?string $edit_comissao = null;
    public ?string $edit_comissao_afiliado = null;
    public ?string $edit_custo_produtos = null;

    public function editarVenda(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;

        $this->editandoId = $vendaId;
        $this->edit_total_produtos = number_format((float) $venda->total_produtos, 2, '.', '');
        $this->edit_valor_frete_cliente = number_format((float) $venda->valor_frete_cliente, 2, '.', '');
        $this->edit_valor_imposto = number_format((float) $venda->valor_imposto, 2, '.', '');
        $this->edit_valor_frete_transportadora = number_format((float) $venda->valor_frete_transportadora, 2, '.', '');
        $this->edit_comissao = number_format((float) $venda->comissao, 2, '.', '');
        $this->edit_comissao_afiliado = number_format((float) $venda->comissao_afiliado, 2, '.', '');
        $this->edit_custo_produtos = number_format((float) $venda->custo_produtos, 2, '.', '');
    }

    public function salvarEdicao(): void
    {
        $venda = Venda::find($this->editandoId);
        if (!$venda) return;

        $venda->update([
            'total_produtos' => (float) str_replace(',', '.', $this->edit_total_produtos),
            'valor_frete_cliente' => (float) str_replace(',', '.', $this->edit_valor_frete_cliente),
            'valor_imposto' => (float) str_replace(',', '.', $this->edit_valor_imposto),
            'valor_frete_transportadora' => (float) str_replace(',', '.', $this->edit_valor_frete_transportadora),
            'comissao' => (float) str_replace(',', '.', $this->edit_comissao),
            'comissao_afiliado' => (float) str_replace(',', '.', $this->edit_comissao_afiliado),
            'custo_produtos' => (float) str_replace(',', '.', $this->edit_custo_produtos),
        ]);

        // Recalcular margens
        if (class_exists(\App\Services\VendaRecalculoService::class)) {
            \App\Services\VendaRecalculoService::recalcularMargens($venda);
        }

        $this->editandoId = null;
        Notification::make()->title("Pedido #{$venda->numero_pedido_canal} atualizado.")->success()->send();
    }

    public function cancelarEdicao(): void
    {
        $this->editandoId = null;
    }

    /**
     * Lança uma venda individual no DRE (trava).
     */
    public function lancarDre(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda || $venda->dre_lancado) return;

        // Bloquear se frete é apenas cotação (não pago e não isento)
        if (!$this->freteConfirmado($venda)) {
            Notification::make()->title('Frete ainda é cotação. Aguarde o CT-e.')->warning()->send();
            return;
        }

        $venda->update([
            'dre_lancado' => true,
            'dre_lancado_em' => now(),
        ]);

        Notification::make()->title("Pedido #{$venda->numero_pedido_canal} lançado no DRE.")->success()->send();
    }

    /**
     * Lança todas as vendas visíveis (pendentes) no DRE.
     * Exclui vendas com frete apenas cotação.
     */
    public function lancarTodos(): void
    {
        $vendas = $this->buildQuery()
            ->where(fn ($q) => $q->where('dre_lancado', false)->orWhereNull('dre_lancado'))
            ->get();

        $count = 0;
        foreach ($vendas as $venda) {
            if (!$this->freteConfirmado($venda)) continue;
            $venda->update(['dre_lancado' => true, 'dre_lancado_em' => now()]);
            $count++;
        }

        $ignorados = $vendas->count() - $count;
        $msg = "{$count} venda(s) lançadas no DRE.";
        if ($ignorados > 0) {
            $msg .= " ({$ignorados} ignoradas — frete pendente)";
        }

        Notification::make()->title($msg)->success()->send();
    }

    /**
     * Verifica se o frete da venda está confirmado (pago ou isento pelo canal).
     */
    private function freteConfirmado(Venda $venda): bool
    {
        $fretePago = (bool) $venda->frete_pago;
        $mlTipoFrete = $venda->ml_tipo_frete ?? null;
        $isMlMe2Full = in_array($mlTipoFrete, ['ME2', 'FULL']);
        $freteCliente = (float) $venda->valor_frete_cliente;
        $custoFrete = (float) $venda->valor_frete_transportadora;
        $freteIsento = $isMlMe2Full || ($freteCliente == 0 && $custoFrete == 0);

        return $fretePago || $freteIsento;
    }

    /**
     * Destravar uma venda (admin).
     */
    public function destravarDre(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;

        $venda->update([
            'dre_lancado' => false,
            'dre_lancado_em' => null,
        ]);

        Notification::make()->title("Pedido #{$venda->numero_pedido_canal} destravado.")->warning()->send();
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
    public function updatedDiaSelecionado(): void { $this->pagina = 1; }
    public function updatedMesSelecionado(): void { $this->pagina = 1; }
    public function updatedDataInicio(): void { $this->pagina = 1; }
    public function updatedDataFim(): void { $this->pagina = 1; }
    public function updatedCnpjId(): void { $this->pagina = 1; }
    public function updatedCanal(): void { $this->pagina = 1; }
    public function updatedStatusDre(): void { $this->pagina = 1; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
