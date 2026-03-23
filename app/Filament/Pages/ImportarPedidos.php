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
        ]);
    }

    public function importar(): void
    {
        $data = $this->form->getState();

        // Disparar job em background em vez de processar sincronamente
        ImportarPedidosBlingJob::dispatch(
            $data['account'],
            $data['data_inicio'],
            $data['data_fim']
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
