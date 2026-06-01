<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoteRecebimentoResource\Pages;
use App\Models\LoteRecebimento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoteRecebimentoResource extends Resource
{
    protected static ?string $model = LoteRecebimento::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $modelLabel = 'Lote de Recebimento';
    protected static ?string $pluralModelLabel = 'Lotes de Recebimento';
    protected static ?string $slug = 'lotes-recebimento';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data_recebimento')
                ->label('Data do Recebimento')
                ->required(),
            Forms\Components\TextInput::make('descricao')
                ->label('Descrição')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Lote #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_recebimento')
                    ->label('Data Recebimento')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('quantidade_contas')
                    ->label('Qtd. Pedidos')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valor_total')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_contas')
                    ->label('Ver Pedidos')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LoteRecebimento $record) => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLotesRecebimento::route('/'),
            'view' => Pages\ViewLoteRecebimento::route('/{record}'),
        ];
    }
}
