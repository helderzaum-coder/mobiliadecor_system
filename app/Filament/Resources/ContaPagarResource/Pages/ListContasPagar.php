<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Resources\ContaPagarResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContasPagar extends ListRecords
{
    protected static string $resource = ContaPagarResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
