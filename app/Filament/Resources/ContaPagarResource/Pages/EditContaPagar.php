<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Resources\ContaPagarResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditContaPagar extends EditRecord
{
    protected static string $resource = ContaPagarResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['recorrente']) && empty($data['grupo_recorrencia'])) {
            $data['grupo_recorrencia'] = Str::uuid()->toString();
        }

        return $data;
    }
}
