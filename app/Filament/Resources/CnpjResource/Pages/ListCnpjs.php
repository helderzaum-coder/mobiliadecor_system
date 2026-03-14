<?php

namespace App\Filament\Resources\CnpjResource\Pages;

use App\Filament\Resources\CnpjResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCnpjs extends ListRecords
{
    protected static string $resource = CnpjResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
