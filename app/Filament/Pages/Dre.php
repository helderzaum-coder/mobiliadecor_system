<?php

namespace App\Filament\Pages;

use App\Models\CanalVenda;
use App\Models\Cnpj;
use App\Models\ImpostoMensal;
use App\Services\DreService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Dre extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'DRE';
    protected static ?string $title = 'DRE - Demonstração do Resultado';
    protected static string $view = 'filament.pages.dre';
    protected static ?int $navigationSort = 2;

    public ?string $periodo = 'este_mes';
    public ?string $mes_selecionado = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public ?string $cnpj_id = null;
    public ?string $canal = null;
    public ?string $visao = 'mensal';

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
                Forms\Components\Select::make('visao')
                    ->label('Visão')
                    ->options([
                        'mensal' => '📊 Mensal (consolidado)',
                        'diaria' => '📅 Diária',
                    ])
                    ->reactive(),
            ]),
        ]);
    }

    private function getDataRange(): array
    {
        return match ($this->periodo) {
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

    public function getDreProperty(): array
    {
        [$inicio, $fim] = $this->getDataRange();
        $cnpjId = $this->cnpj_id ? (int) $this->cnpj_id : null;

        return DreService::calcular($inicio, $fim, $cnpjId, $this->canal);
    }

    public function getDreDiarioProperty(): array
    {
        [$inicio, $fim] = $this->getDataRange();
        $cnpjId = $this->cnpj_id ? (int) $this->cnpj_id : null;

        return DreService::calcularDiario($inicio, $fim, $cnpjId, $this->canal);
    }

    /**
     * Info sobre alíquota usada no período atual.
     */
    public function getInfoImpostoProperty(): string
    {
        [$inicio] = $this->getDataRange();
        $data = \Carbon\Carbon::parse($inicio);
        $mesAnterior = $data->copy()->subMonth();

        $cnpjId = $this->cnpj_id ? (int) $this->cnpj_id : null;

        // Verificar se existe alíquota do próprio mês
        $impostoMesAtual = ImpostoMensal::where('mes_referencia', $data->month)
            ->where('ano_referencia', $data->year)
            ->when($cnpjId, fn ($q) => $q->where('id_cnpj', $cnpjId))
            ->first();

        if ($impostoMesAtual) {
            return "Alíquota definitiva: {$impostoMesAtual->percentual_imposto}% (ref. {$data->format('m/Y')})";
        }

        // Usa mês anterior
        $impostoAnterior = ImpostoMensal::where('mes_referencia', $mesAnterior->month)
            ->where('ano_referencia', $mesAnterior->year)
            ->when($cnpjId, fn ($q) => $q->where('id_cnpj', $cnpjId))
            ->first();

        if ($impostoAnterior) {
            return "⚠ Usando alíquota provisória do mês anterior: {$impostoAnterior->percentual_imposto}% (ref. {$mesAnterior->format('m/Y')})";
        }

        return "⚠ Nenhuma alíquota cadastrada para provisionar impostos";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('recalcular_impostos')
                ->label('Recalcular Impostos do Período')
                ->icon('heroicon-o-calculator')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Recalcular Impostos')
                ->modalDescription('Isso vai aplicar a alíquota cadastrada para o mês/ano selecionado em TODAS as vendas do período. Use quando a alíquota definitiva for cadastrada.')
                ->action(function () {
                    [$inicio] = $this->getDataRange();
                    $data = \Carbon\Carbon::parse($inicio);
                    $cnpjId = $this->cnpj_id ? (int) $this->cnpj_id : null;

                    $count = DreService::recalcularImpostosPeriodo(
                        $data->month,
                        $data->year,
                        $cnpjId
                    );

                    if ($count > 0) {
                        Notification::make()
                            ->title("{$count} venda(s) com imposto recalculado")
                            ->body("Alíquota de {$data->format('m/Y')} aplicada.")
                            ->success()->send();
                    } else {
                        Notification::make()
                            ->title('Nenhuma venda recalculada')
                            ->body('Verifique se a alíquota do mês está cadastrada em Impostos Mensais.')
                            ->warning()->send();
                    }
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
