<?php

namespace App\Filament\Resources\ImpostoMensalResource\Pages;

use App\Filament\Resources\ImpostoMensalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditImpostoMensal extends EditRecord
{
    protected static string $resource = ImpostoMensalResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
