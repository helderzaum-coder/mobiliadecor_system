<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CnpjResource\Pages;
use App\Models\Cnpj;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CnpjResource extends Resource
{
    protected static ?string $model = Cnpj::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $modelLabel = 'CNPJ';
    protected static ?string $pluralModelLabel = 'CNPJs';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('numero_cnpj')
                ->label('CNPJ')
                ->required()
                ->maxLength(18)
                ->mask('99.999.999/9999-99'),
            Forms\Components\TextInput::make('razao_social')
                ->label('Razão Social')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('regime_tributario')
                ->label('Regime Tributário')
                ->required()
                ->maxLength(50),
            Forms\Components\Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_cnpj')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('razao_social')->label('Razão Social')->searchable(),
                Tables\Columns\TextColumn::make('regime_tributario')->label('Regime Tributário'),
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
            'index' => Pages\ListCnpjs::route('/'),
            'create' => Pages\CreateCnpj::route('/create'),
            'edit' => Pages\EditCnpj::route('/{record}/edit'),
        ];
    }
}
