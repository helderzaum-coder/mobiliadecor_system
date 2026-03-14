<?php

namespace App\Filament\Resources\FaturaTransportadoraResource\Pages;

use App\Filament\Resources\FaturaTransportadoraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFaturasTransportadoras extends ListRecords
{
    protected static string $resource = FaturaTransportadoraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
