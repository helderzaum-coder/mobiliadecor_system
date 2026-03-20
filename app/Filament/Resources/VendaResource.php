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
                    ->label('Nº Pedido Canal')->required()->maxLength(50),
                Forms\Components\TextInput::make('numero_nota_fiscal')
                    ->label('NF')->maxLength(50),
                Forms\Components\Select::make('id_canal')
                    ->label('Canal')->relationship('canal', 'nome_canal')
                    ->required()->searchable()->preload(),
                Forms\Components\Select::make('id_cnpj')
                    ->label('CNPJ')->relationship('cnpj', 'razao_social')
                    ->required()->searchable()->preload(),
                Forms\Components\DatePicker::make('data_venda')
                    ->label('Data')->required(),
                Forms\Components\TextInput::make('cliente_nome')
                    ->label('Cliente')->disabled(),
            ])->columns(3),

            Forms\Components\Section::make('Valores')->schema([
                Forms\Components\TextInput::make('total_produtos')
                    ->label('Subtotal Produtos')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('custo_produtos')
                    ->label('Custo Produtos')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('valor_total_venda')
                    ->label('Total Pedido')->numeric()->prefix('R$')->required(),
                Forms\Components\TextInput::make('valor_frete_cliente')
                    ->label('Frete')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('valor_frete_transportadora')
                    ->label('Frete Pago (Transp.)')->numeric()->prefix('R$'),
                Forms\Components\Toggle::make('frete_pago')->label('Frete Pago'),
            ])->columns(3),

            Forms\Components\Section::make('Comissão e Impostos')->schema([
                Forms\Components\TextInput::make('comissao')
                    ->label('Comissão')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('subsidio_pix')
                    ->label('Subsídio Pix')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('percentual_imposto')
                    ->label('Imposto (%)')->numeric()->suffix('%'),
                Forms\Components\TextInput::make('valor_imposto')
                    ->label('Valor Imposto')->numeric()->prefix('R$'),
            ])->columns(4),

            Forms\Components\Section::make('Margens')->schema([
                Forms\Components\TextInput::make('margem_frete')
                    ->label('Margem Frete')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('margem_produto')
                    ->label('Margem Produto')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('margem_venda_total')
                    ->label('Lucro Final')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('margem_contribuicao')
                    ->label('Lucro Final (%)')->numeric()->suffix('%'),
            ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_pedido_canal')
                    ->label('Pedido')->searchable(),
                Tables\Columns\TextColumn::make('numero_nota_fiscal')
                    ->label('NF')->searchable(),
                Tables\Columns\TextColumn::make('canal.nome_canal')
                    ->label('Canal'),
                Tables\Columns\TextColumn::make('cnpj.razao_social')
                    ->label('CNPJ')->limit(20),
                Tables\Columns\TextColumn::make('data_venda')
                    ->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('total_produtos')
                    ->label('Subtotal')->money('BRL'),
                Tables\Columns\TextColumn::make('custo_produtos')
                    ->label('Custo Prod.')->money('BRL'),
                Tables\Columns\TextColumn::make('comissao')
                    ->label('Comissão')->money('BRL'),
                Tables\Columns\TextColumn::make('valor_imposto')
                    ->label('Imposto')->money('BRL'),
                Tables\Columns\TextColumn::make('margem_produto')
                    ->label('Margem Produto')->money('BRL')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('margem_produto_pct')
                    ->label('Margem Prod. %')
                    ->getStateUsing(fn (Venda $r) => $r->total_produtos > 0
                        ? round(($r->margem_produto / $r->total_produtos) * 100, 1) . '%'
                        : '-'),
                Tables\Columns\TextColumn::make('valor_frete_cliente')
                    ->label('Frete')->money('BRL'),
                Tables\Columns\TextColumn::make('valor_frete_transportadora')
                    ->label('Frete Pago')->money('BRL'),
                Tables\Columns\TextColumn::make('margem_frete')
                    ->label('Margem Frete')->money('BRL')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('margem_frete_pct')
                    ->label('Margem Frete %')
                    ->getStateUsing(fn (Venda $r) => $r->valor_frete_cliente > 0
                        ? round(($r->margem_frete / $r->valor_frete_cliente) * 100, 1) . '%'
                        : '-'),
                Tables\Columns\TextColumn::make('valor_total_venda')
                    ->label('Total')->money('BRL'),
                Tables\Columns\TextColumn::make('repasse_estimado')
                    ->label('Repasse Est.')
                    ->money('BRL')
                    ->getStateUsing(fn (Venda $r) => round(
                        (float) $r->valor_total_venda - (float) $r->comissao - (float) $r->subsidio_pix,
                        2
                    ))
                    ->color('info'),
                Tables\Columns\TextColumn::make('margem_venda_total')
                    ->label('Lucro Final')->money('BRL')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('margem_contribuicao')
                    ->label('Lucro %')->suffix('%')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('data_venda', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('id_canal')
                    ->label('Canal')
                    ->relationship('canal', 'nome_canal'),
                Tables\Filters\SelectFilter::make('id_cnpj')
                    ->label('CNPJ')
                    ->relationship('cnpj', 'razao_social'),
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
            'index' => Pages\ListVendas::route('/'),
            'create' => Pages\CreateVenda::route('/create'),
            'edit' => Pages\EditVenda::route('/{record}/edit'),
        ];
    }
}
