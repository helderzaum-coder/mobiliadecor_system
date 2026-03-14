<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Resources\ContaPagarResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContaPagar extends EditRecord
{
    protected static string $resource = ContaPagarResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
