<?php

namespace App\Filament\Resources\ContaReceberResource\Pages;

use App\Filament\Resources\ContaReceberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContaReceber extends CreateRecord
{
    protected static string $resource = ContaReceberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['lancamento_manual'] = true;
        $data['numero_parcela'] = $data['numero_parcela'] ?? 1;
        $data['total_parcelas'] = $data['total_parcelas'] ?? 1;

        return $data;
    }
}
