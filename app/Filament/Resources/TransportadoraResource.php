<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportadoraResource\Pages;
use App\Models\Transportadora;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransportadoraResource extends Resource
{
    protected static ?string $model = Transportadora::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $modelLabel = 'Transportadora';
    protected static ?string $pluralModelLabel = 'Transportadoras';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome_transportadora')
                ->label('Nome')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('cnpj')
                ->label('CNPJ')
                ->required()
                ->maxLength(18)
                ->mask('99.999.999/9999-99'),
            Forms\Components\Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_transportadora')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('cnpj')->label('CNPJ')->searchable(),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('ativo'),
            ])
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
            'index' => Pages\ListTransportadoras::route('/'),
            'create' => Pages\CreateTransportadora::route('/create'),
            'edit' => Pages\EditTransportadora::route('/{record}/edit'),
        ];
    }
}
