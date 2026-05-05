<?php

namespace App\Filament\Pages;

use App\Models\Venda;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class Recebimentos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Recebimentos';
    protected static ?string $title = 'Confirmação de Recebimentos';
    protected static string $view = 'filament.pages.recebimentos';
    protected static ?int $navigationSort = 2;

    public ?string $periodo = 'este_mes';
    public ?string $mes_selecionado = null;
    public ?string $canal = null;
    public ?string $conta = null;
    public ?string $filtro_status = null;
    public int $pagina = 1;
    public int $porPagina = 20;

    public function mount(): void
    {
        $this->mes_selecionado = now()->format('Y-m');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(6)->schema([
                Forms\Components\Select::make('periodo')
                    ->label('Período')
                    ->options([
                        'este_mes' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'selecionar_mes' => 'Selecionar mês',
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
                Forms\Components\Select::make('filtro_status')
                    ->label('Status')
                    ->options([
                        'pendente' => '⏳ Pendente',
                        'recebido' => '✅ Recebido',
                    ])
                    ->placeholder('Todos')
                    ->reactive(),
            ]),
        ]);
    }

    public function confirmarRecebimento(int $vendaId): void
    {
        Venda::where('id_venda', $vendaId)->update([
            'repasse_recebido' => true,
            'data_recebimento' => now()->toDateString(),
        ]);
        \Filament\Notifications\Notification::make()->title('Recebimento confirmado.')->success()->send();
    }

    public function confirmarRecebimentoData(int $vendaId, string $data): void
    {
        Venda::where('id_venda', $vendaId)->update([
            'repasse_recebido' => true,
            'data_recebimento' => $data,
        ]);
        \Filament\Notifications\Notification::make()->title('Recebimento confirmado.')->success()->send();
    }

    public function desfazerRecebimento(int $vendaId): void
    {
        Venda::where('id_venda', $vendaId)->update([
            'repasse_recebido' => false,
            'data_recebimento' => null,
        ]);
        \Filament\Notifications\Notification::make()->title('Recebimento desfeito.')->success()->send();
    }

    private function buildQuery()
    {
        $query = Venda::with('canal')->orderBy('data_venda', 'desc');

        $query = match ($this->periodo) {
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
        if ($this->filtro_status === 'pendente') {
            $query->where('repasse_recebido', false);
        } elseif ($this->filtro_status === 'recebido') {
            $query->where('repasse_recebido', true);
        }

        return $query;
    }

    public function getVendasProperty()
    {
        return $this->buildQuery()
            ->skip(($this->pagina - 1) * $this->porPagina)
            ->take($this->porPagina)
            ->get();
    }

    public function getTotaisProperty(): array
    {
        $query = $this->buildQuery();
        $totalRepasse = (clone $query)->sum(\DB::raw('valor_total_venda - comissao + COALESCE(subsidio_pix, 0)'));
        $recebido = (clone $query)->where('repasse_recebido', true)->sum(\DB::raw('valor_total_venda - comissao + COALESCE(subsidio_pix, 0)'));
        $pendente = $totalRepasse - $recebido;
        $qtdTotal = (clone $query)->count();
        $qtdRecebido = (clone $query)->where('repasse_recebido', true)->count();

        return [
            'total_repasse' => $totalRepasse,
            'recebido' => $recebido,
            'pendente' => $pendente,
            'qtd_total' => $qtdTotal,
            'qtd_recebido' => $qtdRecebido,
            'qtd_pendente' => $qtdTotal - $qtdRecebido,
        ];
    }

    public function getTotalPaginasProperty(): int
    {
        return max(1, (int) ceil($this->totais['qtd_total'] / $this->porPagina));
    }

    public function paginaAnterior(): void { if ($this->pagina > 1) $this->pagina--; }
    public function proximaPagina(): void { $this->pagina++; }
    public function updatedPeriodo(): void { $this->pagina = 1; }
    public function updatedMesSelecionado(): void { $this->pagina = 1; }
    public function updatedCanal(): void { $this->pagina = 1; }
    public function updatedConta(): void { $this->pagina = 1; }
    public function updatedFiltroStatus(): void { $this->pagina = 1; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
