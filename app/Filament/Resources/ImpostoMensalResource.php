<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImpostoMensalResource\Pages;
use App\Models\ImpostoMensal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ImpostoMensalResource extends Resource
{
    protected static ?string $model = ImpostoMensal::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Fiscal';
    protected static ?string $modelLabel = 'Imposto Mensal';
    protected static ?string $pluralModelLabel = 'Impostos Mensais';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id_cnpj')
                ->label('CNPJ')
                ->relationship('cnpj', 'razao_social')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('mes_referencia')
                ->label('Mês Referência')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(12),
            Forms\Components\TextInput::make('ano_referencia')
                ->label('Ano Referência')
                ->required()
                ->numeric()
                ->minValue(2020),
            Forms\Components\TextInput::make('percentual_imposto')
                ->label('Percentual Imposto (%)')
                ->required()
                ->numeric()
                ->suffix('%'),
            Forms\Components\DatePicker::make('data_atualizacao')
                ->label('Data Atualização')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cnpj.razao_social')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('mes_referencia')->label('Mês'),
                Tables\Columns\TextColumn::make('ano_referencia')->label('Ano')->sortable(),
                Tables\Columns\TextColumn::make('percentual_imposto')->label('Imposto (%)')->suffix('%'),
                Tables\Columns\TextColumn::make('data_atualizacao')->label('Atualização')->date('d/m/Y'),
            ])
            ->defaultSort('ano_referencia', 'desc')
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
            'index' => Pages\ListImpostosMensais::route('/'),
            'create' => Pages\CreateImpostoMensal::route('/create'),
            'edit' => Pages\EditImpostoMensal::route('/{record}/edit'),
        ];
    }
}
