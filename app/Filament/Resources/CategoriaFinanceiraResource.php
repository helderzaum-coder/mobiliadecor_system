<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaFinanceiraResource\Pages;
use App\Models\CategoriaFinanceira;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoriaFinanceiraResource extends Resource
{
    protected static ?string $model = CategoriaFinanceira::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Categorias';
    protected static ?string $modelLabel = 'Categoria Financeira';
    protected static ?string $pluralModelLabel = 'Categorias Financeiras';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(100)->unique(ignoreRecord: true),
            Forms\Components\Select::make('tipo')->label('Tipo')
                ->options(['entrada' => 'Entrada', 'saida' => 'Saída', 'ambos' => 'Ambos'])
                ->default('ambos')->required(),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'entrada' => 'success',
                        'saida' => 'danger',
                        'ambos' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoriasFinanceiras::route('/'),
            'create' => Pages\CreateCategoriaFinanceira::route('/create'),
            'edit' => Pages\EditCategoriaFinanceira::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
