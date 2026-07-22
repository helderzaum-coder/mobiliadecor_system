<?php

namespace App\Filament\Resources\ReclamacaoMLResource\Pages;

use App\Filament\Resources\ReclamacaoMLResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReclamacoesML extends ListRecords
{
    protected static string $resource = ReclamacaoMLResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
