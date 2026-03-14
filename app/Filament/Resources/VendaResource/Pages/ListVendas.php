<?php

namespace App\Filament\Resources\VendaResource\Pages;

use App\Filament\Resources\VendaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendas extends ListRecords
{
    protected static string $resource = VendaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
