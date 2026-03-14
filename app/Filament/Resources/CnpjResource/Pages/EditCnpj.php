<?php

namespace App\Filament\Resources\CnpjResource\Pages;

use App\Filament\Resources\CnpjResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCnpj extends EditRecord
{
    protected static string $resource = CnpjResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
