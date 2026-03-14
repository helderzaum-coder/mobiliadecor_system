<?php

namespace App\Filament\Resources\PedidoBlingStagingResource\Pages;

use App\Filament\Resources\PedidoBlingStagingResource;
use App\Services\Bling\BlingImportService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPedidoBlingStaging extends EditRecord
{
    protected static string $resource = PedidoBlingStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('buscar_nfe')
                ->label('Buscar NF-e')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Buscar NF-e no Bling')
                ->modalDescription('Isso vai buscar a NF-e vinculada a este pedido na API do Bling.')
                ->action(function () {
                    $found = BlingImportService::buscarNfePorPedido($this->record);
                    if ($found) {
                        Notification::make()->title('NF-e encontrada e vinculada.')->success()->send();
                        $this->fillForm();
                    } else {
                        Notification::make()->title('NF-e não encontrada para este pedido.')->warning()->send();
                    }
                })
                ->visible(fn () => $this->record->status === 'pendente' && empty($this->record->nfe_chave_acesso)),
            Actions\Action::make('aprovar')
                ->label('Aprovar e Salvar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    $this->save();
                    $this->record->update(['status' => 'aprovado']);
                    $this->redirect(PedidoBlingStagingResource::getUrl());
                }),
        ];
    }
}
