<?php

namespace App\Filament\Resources\LoteRecebimentoResource\Pages;

use App\Filament\Resources\LoteRecebimentoResource;
use Filament\Resources\Pages\ListRecords;

class ListLotesRecebimento extends ListRecords
{
    protected static string $resource = LoteRecebimentoResource::class;

    protected function getTableFiltersFormStateQueryStringKey(): ?string
    {
        return 'filtros';
    }
}
