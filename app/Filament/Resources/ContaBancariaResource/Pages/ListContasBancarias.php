<?php

namespace App\Filament\Resources\ContaBancariaResource\Pages;

use App\Filament\Resources\ContaBancariaResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListContasBancarias extends ListRecords
{
    protected static string $resource = ContaBancariaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
