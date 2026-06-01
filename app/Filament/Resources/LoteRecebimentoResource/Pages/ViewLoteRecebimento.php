<?php

namespace App\Filament\Resources\LoteRecebimentoResource\Pages;

use App\Filament\Resources\LoteRecebimentoResource;
use App\Models\ContaReceber;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;

class ViewLoteRecebimento extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = LoteRecebimentoResource::class;
    protected static string $view = 'filament.resources.lote-recebimento.view';

    public $record;

    public function mount($record): void
    {
        $this->record = \App\Models\LoteRecebimento::findOrFail($record);
    }

    public function getTitle(): string
    {
        return "Lote #{$this->record->id} — " . $this->record->data_recebimento->format('d/m/Y');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ContaReceber::where('lote_recebimento_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('venda.numero_pedido_canal')
                    ->label('Pedido')
                    ->searchable(),
                Tables\Columns\TextColumn::make('forma_pagamento')
                    ->label('Canal')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('venda.cliente_nome')
                    ->label('Cliente')
                    ->limit(30),
                Tables\Columns\TextColumn::make('valor_parcela')
                    ->label('Valor')
                    ->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('data_recebimento')
                    ->label('Recebido em')
                    ->date('d/m/Y'),
            ]);
    }
}
