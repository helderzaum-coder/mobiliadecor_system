<?php

namespace App\Filament\Resources\PedidoBlingStagingResource\Pages;

use App\Filament\Resources\PedidoBlingStagingResource;
use App\Services\Bling\BlingImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPedidosBlingStaging extends ListRecords
{
    protected static string $resource = PedidoBlingStagingResource::class;

    protected ?string $defaultTableSortColumn = 'data_pedido';
    protected ?string $defaultTableSortDirection = 'desc';

    public function getDefaultActiveTab(): ?string
    {
        return null;
    }

    /**
     * Garante que o filtro de status 'pendente' esteja sempre ativo ao abrir a página
     */
    public function mount(): void
    {
        parent::mount();

        // Se não tem filtros na URL, forçar status=pendente
        if (empty(request()->query('tableFilters'))) {
            $this->tableFilters['status']['value'] = 'pendente';
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reprocessar_impostos')
                ->label('Reprocessar Impostos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('bling_account')
                        ->label('Conta')
                        ->options([
                            'primary' => 'Mobilia Decor',
                            'secondary' => 'HES Móveis',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('mes')
                        ->label('Mês')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(12)
                        ->required(),
                    Forms\Components\TextInput::make('ano')
                        ->label('Ano')
                        ->numeric()
                        ->minValue(2020)
                        ->default(now()->year)
                        ->required(),
                ])
                ->modalHeading('Reprocessar Impostos do Mês')
                ->modalDescription('Recalcula o imposto de todos os pedidos pendentes do mês/conta usando o percentual cadastrado em Impostos Mensais.')
                ->action(function (array $data) {
                    $result = BlingImportService::reprocessarImpostos(
                        $data['bling_account'],
                        (int) $data['mes'],
                        (int) $data['ano']
                    );

                    if (isset($result['erro'])) {
                        Notification::make()->title($result['erro'])->danger()->send();
                    } else {
                        Notification::make()
                            ->title("{$result['atualizados']} pedidos atualizados com {$result['percentual']}%")
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
