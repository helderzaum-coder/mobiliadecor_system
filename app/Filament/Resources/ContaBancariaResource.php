<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaBancariaResource\Pages;
use App\Models\ContaBancaria;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContaBancariaResource extends Resource
{
    protected static ?string $model = ContaBancaria::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Bancos';
    protected static ?string $modelLabel = 'Conta Bancária';
    protected static ?string $pluralModelLabel = 'Contas Bancárias';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(100)
                ->helperText('Ex: Itaú PJ, Bradesco, Caixa Econômica'),
            Forms\Components\TextInput::make('banco')->label('Banco')->maxLength(100),
            Forms\Components\TextInput::make('agencia')->label('Agência')->maxLength(20),
            Forms\Components\TextInput::make('conta')->label('Conta')->maxLength(30),
            Forms\Components\TextInput::make('saldo_inicial')->label('Saldo Inicial')->numeric()->prefix('R$')->default(0),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('banco')->label('Banco'),
                Tables\Columns\TextColumn::make('agencia')->label('Agência'),
                Tables\Columns\TextColumn::make('conta')->label('Conta'),
                Tables\Columns\TextColumn::make('saldo_inicial')->label('Saldo Inicial')->money('BRL'),
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
            'index' => Pages\ListContasBancarias::route('/'),
            'create' => Pages\CreateContaBancaria::route('/create'),
            'edit' => Pages\EditContaBancaria::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
