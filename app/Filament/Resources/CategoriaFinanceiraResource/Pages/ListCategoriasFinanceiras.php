<?php

namespace App\Filament\Resources\CategoriaFinanceiraResource\Pages;

use App\Filament\Resources\CategoriaFinanceiraResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCategoriasFinanceiras extends ListRecords
{
    protected static string $resource = CategoriaFinanceiraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
