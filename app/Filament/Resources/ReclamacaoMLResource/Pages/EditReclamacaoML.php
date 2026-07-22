<?php

namespace App\Filament\Resources\ReclamacaoMLResource\Pages;

use App\Filament\Resources\ReclamacaoMLResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReclamacaoML extends EditRecord
{
    protected static string $resource = ReclamacaoMLResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
