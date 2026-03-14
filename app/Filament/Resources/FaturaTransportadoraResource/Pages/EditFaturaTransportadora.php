<?php

namespace App\Filament\Resources\FaturaTransportadoraResource\Pages;

use App\Filament\Resources\FaturaTransportadoraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFaturaTransportadora extends EditRecord
{
    protected static string $resource = FaturaTransportadoraResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
