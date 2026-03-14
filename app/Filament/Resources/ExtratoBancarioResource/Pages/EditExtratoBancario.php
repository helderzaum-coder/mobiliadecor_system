<?php

namespace App\Filament\Resources\ExtratoBancarioResource\Pages;

use App\Filament\Resources\ExtratoBancarioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExtratoBancario extends EditRecord
{
    protected static string $resource = ExtratoBancarioResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
