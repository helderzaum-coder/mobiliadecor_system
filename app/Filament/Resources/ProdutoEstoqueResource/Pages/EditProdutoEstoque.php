<?php

namespace App\Filament\Resources\ProdutoEstoqueResource\Pages;

use App\Filament\Resources\ProdutoEstoqueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProdutoEstoque extends EditRecord
{
    protected static string $resource = ProdutoEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('save_and_close')
                ->label('Salvar e Fechar')
                ->action(function () {
                    $this->save(shouldRedirect: false);
                    $this->redirect($this->previousUrl ?? ProdutoEstoqueResource::getUrl('index'));
                })
                ->color('gray'),
            $this->getCancelFormAction(),
        ];
    }
}
