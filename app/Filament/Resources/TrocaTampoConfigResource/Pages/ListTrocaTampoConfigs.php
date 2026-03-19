<?php

namespace App\Filament\Resources\TrocaTampoConfigResource\Pages;

use App\Filament\Resources\TrocaTampoConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrocaTampoConfigs extends ListRecords
{
    protected static string $resource = TrocaTampoConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
