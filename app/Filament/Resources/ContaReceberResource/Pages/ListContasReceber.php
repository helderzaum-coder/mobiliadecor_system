<?php

namespace App\Filament\Resources\ContaReceberResource\Pages;

use App\Filament\Resources\ContaReceberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContasReceber extends ListRecords
{
    protected static string $resource = ContaReceberResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
