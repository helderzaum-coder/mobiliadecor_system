<?php

namespace App\Filament\Resources\CanalVendaResource\Pages;

use App\Filament\Resources\CanalVendaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCanalVenda extends EditRecord
{
    protected static string $resource = CanalVendaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
