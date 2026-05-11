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
}
