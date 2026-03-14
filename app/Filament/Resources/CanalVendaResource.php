<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CanalVendaResource\Pages;
use App\Models\CanalVenda;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CanalVendaResource extends Resource
{
    protected static ?string $model = CanalVenda::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $modelLabel = 'Canal de Venda';
    protected static ?string $pluralModelLabel = 'Canais de Venda';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome_canal')
                ->label('Nome do Canal')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('percentual_comissao')
                ->label('Comissão (%)')
                ->required()
                ->numeric()
                ->suffix('%'),
            Forms\Components\TextInput::make('percentual_imposto')
                ->label('Imposto (%)')
                ->required()
                ->numeric()
                ->suffix('%'),
            Forms\Components\Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_canal')->label('Canal')->searchable(),
                Tables\Columns\TextColumn::make('percentual_comissao')->label('Comissão (%)')->suffix('%'),
                Tables\Columns\TextColumn::make('percentual_imposto')->label('Imposto (%)')->suffix('%'),
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
            'index' => Pages\ListCanaisVenda::route('/'),
            'create' => Pages\CreateCanalVenda::route('/create'),
            'edit' => Pages\EditCanalVenda::route('/{record}/edit'),
        ];
    }
}
