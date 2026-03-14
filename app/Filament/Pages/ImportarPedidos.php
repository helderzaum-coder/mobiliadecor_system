<?php

namespace App\Filament\Pages;

use App\Services\Bling\BlingImportService;
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
        $service = new BlingImportService($data['account']);

        $this->resultado = $service->importarParaStaging(
            $data['data_inicio'],
            $data['data_fim']
        );

        $msg = "{$this->resultado['importados']} pedidos para revisão";
        if ($this->resultado['ignorados'] > 0) {
            $msg .= ", {$this->resultado['ignorados']} já existentes";
        }

        Notification::make()
            ->title('Importação concluída')
            ->body($msg)
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
