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
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public ?string $canal = null;
    public ?string $conta = null;

    public function mount(): void
    {
        $this->mes_selecionado = now()->format('Y-m');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(5)->schema([
                Forms\Components\Select::make('periodo')
                    ->label('Período')
                    ->options([
                        'hoje' => 'Hoje',
                        'esta_semana' => 'Esta semana',
                        'este_mes' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'selecionar_mes' => 'Selecionar mês',
                    ])
                    ->reactive()
                    ->afterStateUpdated(fn () => null),
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
            ]),
        ]);
    }

    public function getVendasProperty(): \Illuminate\Support\Collection
    {
        $query = Venda::with('canal')->orderBy('data_venda', 'desc');

        // Filtro período
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
            default => $query,
        };

        if ($this->canal) {
            $query->whereHas('canal', fn ($q) => $q->where('nome_canal', $this->canal));
        }
        if ($this->conta) {
            $query->where('bling_account', $this->conta);
        }

        return $query->limit(100)->get();
    }

    public function getTotaisProperty(): array
    {
        $vendas = $this->vendas;
        $total = $vendas->sum('valor_total_venda');
        $lucro = $vendas->sum('margem_venda_total');
        $comLucro = $vendas->where('margem_venda_total', '>=', 0)->count();
        $comPrejuizo = $vendas->where('margem_venda_total', '<', 0)->count();

        return [
            'qtd' => $vendas->count(),
            'total' => $total,
            'lucro' => $lucro,
            'margem' => $total > 0 ? round(($lucro / $total) * 100, 1) : 0,
            'com_lucro' => $comLucro,
            'com_prejuizo' => $comPrejuizo,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
