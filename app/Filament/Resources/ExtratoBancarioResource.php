<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExtratoBancarioResource\Pages;
use App\Models\ExtratoBancario;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExtratoBancarioResource extends Resource
{
    protected static ?string $model = ExtratoBancario::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $modelLabel = 'Extrato Bancário';
    protected static ?string $pluralModelLabel = 'Extratos Bancários';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id_cnpj')
                ->label('CNPJ')
                ->relationship('cnpj', 'razao_social')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\DatePicker::make('data_movimento')
                ->label('Data Movimento')
                ->required(),
            Forms\Components\TextInput::make('descricao')
                ->label('Descrição')
                ->required()
                ->maxLength(191),
            Forms\Components\TextInput::make('valor')
                ->label('Valor')
                ->required()
                ->numeric()
                ->prefix('R$'),
            Forms\Components\Select::make('tipo_movimento')
                ->label('Tipo')
                ->options([
                    'credito' => 'Crédito',
                    'debito' => 'Débito',
                ])
                ->required(),
            Forms\Components\TextInput::make('saldo')
                ->label('Saldo')
                ->required()
                ->numeric()
                ->prefix('R$'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cnpj.razao_social')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('data_movimento')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('tipo_movimento')->label('Tipo')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'credito' => 'success',
                        'debito' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('saldo')->label('Saldo')->money('BRL'),
            ])
            ->defaultSort('data_movimento', 'desc')
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
            'index' => Pages\ListExtratosBancarios::route('/'),
            'create' => Pages\CreateExtratoBancario::route('/create'),
            'edit' => Pages\EditExtratoBancario::route('/{record}/edit'),
        ];
    }
}
