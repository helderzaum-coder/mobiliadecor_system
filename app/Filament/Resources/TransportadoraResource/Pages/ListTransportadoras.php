<?php

namespace App\Filament\Resources\TransportadoraResource\Pages;

use App\Filament\Resources\TransportadoraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportadoras extends ListRecords
{
    protected static string $resource = TransportadoraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
