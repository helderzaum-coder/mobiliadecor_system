<?php

namespace App\Filament\Pages;

use App\Jobs\ImportarPedidosBlingJob;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarPedidos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Importar Pedidos';
    protected static ?string $title = 'Importar Pedidos do Bling';
    protected static string $view = 'filament.pages.importar-pedidos';

    public ?string $account = 'primary';
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public ?string $canal_filtro = null;
    public ?array $resultado = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('account')
                ->label('Conta Bling')
                ->options([
                    'primary' => 'Mobilia Decor',
                    'secondary' => 'HES Móveis',
                ])
                ->required(),
            DatePicker::make('data_inicio')
                ->label('Data Início')
                ->required(),
            DatePicker::make('data_fim')
                ->label('Data Fim')
                ->required(),
            Select::make('canal_filtro')
                ->label('Canal (opcional)')
                ->options(fn () => \App\Models\CanalVenda::where('ativo', true)->orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray())
                ->placeholder('Todos os canais')
                ->helperText('Filtrar importação por canal específico'),
        ]);
    }

    public function importar(): void
    {
        $data = $this->form->getState();

        ImportarPedidosBlingJob::dispatch(
            $data['account'],
            $data['data_inicio'],
            $data['data_fim'],
            $data['canal_filtro'] ?? null
        );

        Notification::make()
            ->title('Importação iniciada')
            ->body('A importação foi enfileirada e será processada em background. Você receberá uma notificação quando terminar.')
            ->info()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
