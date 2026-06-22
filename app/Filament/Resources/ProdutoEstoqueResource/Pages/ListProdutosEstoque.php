<?php

namespace App\Filament\Resources\ProdutoEstoqueResource\Pages;

use App\Filament\Resources\ProdutoEstoqueResource;
use App\Jobs\AplicarLimiteTampoJob;
use App\Jobs\VariacaoTamposJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProdutosEstoque extends ListRecords
{
    protected static string $resource = ProdutoEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('variacao_tampos')
                ->label('Rodar Variação de Tampos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Rodar Variação de Tampos')
                ->modalDescription('Isso vai equalizar os saldos de todos os grupos e aplicar os limites de tampo. Continuar?')
                ->action(function () {
                    VariacaoTamposJob::dispatch('primary');
                    Notification::make()->title('Variação de Tampos disparada! Aguarde a notificação de conclusão.')->success()->send();
                }),
            Actions\Action::make('aplicar_limite_tampos')
                ->label('Aplicar Limite de Tampos')
                ->icon('heroicon-o-funnel')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Aplicar Limite de Tampos')
                ->modalDescription('Isso vai limitar o saldo de TODOS os produtos configurados pelo estoque do tampo correspondente. Continuar?')
                ->action(function () {
                    AplicarLimiteTampoJob::dispatch();
                    Notification::make()->title('Limite de Tampos disparado! Aguarde a notificação de conclusão.')->success()->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
