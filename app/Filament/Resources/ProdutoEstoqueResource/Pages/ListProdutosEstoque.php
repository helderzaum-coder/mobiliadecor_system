<?php

namespace App\Filament\Resources\ProdutoEstoqueResource\Pages;

use App\Filament\Resources\ProdutoEstoqueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProdutosEstoque extends ListRecords
{
    protected static string $resource = ProdutoEstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
