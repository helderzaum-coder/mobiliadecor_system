<?php

namespace App\Filament\Resources\ProdutoEstoqueSecondaryResource\Pages;

use App\Filament\Resources\ProdutoEstoqueSecondaryResource;
use Filament\Resources\Pages\ListRecords;

class ListProdutosEstoqueSecondary extends ListRecords
{
    protected static string $resource = ProdutoEstoqueSecondaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('sync_saldos')
                ->label('Sincronizar Saldos Secondary')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Busca os saldos atuais da conta Secondary (HES Móveis) no Bling e atualiza a visualização.')
                ->action(function () {
                    \App\Jobs\SyncSaldoSecondaryJob::dispatch();
                    \Filament\Notifications\Notification::make()->title('Sincronização iniciada em background.')->info()->send();
                }),
        ];
    }
}
