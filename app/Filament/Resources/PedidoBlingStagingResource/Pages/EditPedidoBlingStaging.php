<?php

namespace App\Filament\Resources\PedidoBlingStagingResource\Pages;

use App\Filament\Resources\PedidoBlingStagingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPedidoBlingStaging extends EditRecord
{
    protected static string $resource = PedidoBlingStagingResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
