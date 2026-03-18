<?php

namespace App\Filament\Resources\TransportadoraResource\Pages;

use App\Filament\Resources\TransportadoraResource;
use App\Models\TransportadoraUf;
use Filament\Resources\Pages\CreateRecord;

class CreateTransportadora extends CreateRecord
{
    protected static string $resource = TransportadoraResource::class;

    protected function afterCreate(): void
    {
        $ufs = $this->data['ufs_selecionadas'] ?? [];
        foreach ($ufs as $uf) {
            TransportadoraUf::create([
                'id_transportadora' => $this->record->id_transportadora,
                'uf' => $uf,
            ]);
        }
    }
}
