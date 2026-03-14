<?php

namespace App\Filament\Resources\ExtratoBancarioResource\Pages;

use App\Filament\Resources\ExtratoBancarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExtratosBancarios extends ListRecords
{
    protected static string $resource = ExtratoBancarioResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
