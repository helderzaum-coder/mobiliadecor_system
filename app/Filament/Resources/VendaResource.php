<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendaResource\Pages;
use App\Models\Venda;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendaResource extends Resource
{
    protected static ?string $model = Venda::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Vendas';
    protected static ?string $modelLabel = 'Venda';
    protected static ?string $pluralModelLabel = 'Vendas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados da Venda')->schema([
                Forms\Components\TextInput::make('numero_pedido_canal')
                    ->label('Nº Pedido Canal')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('numero_nota_fiscal')
                    ->label('Nº Nota Fiscal')
                    ->required()
                    ->maxLength(50),
                Forms\Components\Select::make('id_canal')
                    ->label('Canal de Venda')
                    ->relationship('canal', 'nome_canal')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('id_cnpj')
                    ->label('CNPJ')
                    ->relationship('cnpj', 'razao_social')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\DatePicker::make('data_venda')
                    ->label('Data da Venda')
                    ->required(),
                Forms\Components\TextInput::make('valor_total_venda')
                    ->label('Valor Total')
                    ->required()
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('valor_frete_cliente')
                    ->label('Frete Cliente')
                    ->required()
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\Toggle::make('frete_pago')
                    ->label('Frete Pago'),
            ])->columns(2),
            Forms\Components\Section::make('Margens')->schema([
                Forms\Components\TextInput::make('margem_frete')
                    ->label('Margem Frete')
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('margem_produto')
                    ->label('Margem Produto')
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('margem_venda_total')
                    ->label('Margem Venda Total')
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('margem_contribuicao')
                    ->label('Margem Contribuição')
                    ->numeric()
                    ->prefix('R$'),
            ])->columns(2)->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_pedido_canal')->label('Pedido')->searchable(),
                Tables\Columns\TextColumn::make('numero_nota_fiscal')->label('NF')->searchable(),
                Tables\Columns\TextColumn::make('canal.nome_canal')->label('Canal'),
                Tables\Columns\TextColumn::make('cnpj.razao_social')->label('CNPJ'),
                Tables\Columns\TextColumn::make('data_venda')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('valor_total_venda')->label('Valor Total')->money('BRL'),
                Tables\Columns\IconColumn::make('frete_pago')->label('Frete Pago')->boolean(),
            ])
            ->defaultSort('data_venda', 'desc')
            ->filters([])
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
            'index' => Pages\ListVendas::route('/'),
            'create' => Pages\CreateVenda::route('/create'),
            'edit' => Pages\EditVenda::route('/{record}/edit'),
        ];
    }
}
