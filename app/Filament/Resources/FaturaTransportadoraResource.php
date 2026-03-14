<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaturaTransportadoraResource\Pages;
use App\Models\FaturaTransportadora;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaturaTransportadoraResource extends Resource
{
    protected static ?string $model = FaturaTransportadora::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Transporte';
    protected static ?string $modelLabel = 'Fatura Transportadora';
    protected static ?string $pluralModelLabel = 'Faturas Transportadoras';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id_transportadora')
                ->label('Transportadora')
                ->relationship('transportadora', 'nome_transportadora')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('numero_fatura')
                ->label('Nº Fatura')
                ->required()
                ->maxLength(50),
            Forms\Components\DatePicker::make('data_emissao')
                ->label('Data Emissão')
                ->required(),
            Forms\Components\TextInput::make('valor_total')
                ->label('Valor Total')
                ->required()
                ->numeric()
                ->prefix('R$'),
            Forms\Components\DatePicker::make('data_vencimento')
                ->label('Vencimento')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transportadora.nome_transportadora')->label('Transportadora')->searchable(),
                Tables\Columns\TextColumn::make('numero_fatura')->label('Nº Fatura')->searchable(),
                Tables\Columns\TextColumn::make('data_emissao')->label('Emissão')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('valor_total')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('data_vencimento')->label('Vencimento')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('data_emissao', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaturasTransportadoras::route('/'),
            'create' => Pages\CreateFaturaTransportadora::route('/create'),
            'edit' => Pages\EditFaturaTransportadora::route('/{record}/edit'),
        ];
    }
}
