<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MovimentacaoEstoqueResource\Pages;
use App\Models\MovimentacaoEstoque;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MovimentacaoEstoqueResource extends Resource
{
    protected static ?string $model = MovimentacaoEstoque::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Movimentações';
    protected static ?string $modelLabel = 'Movimentação';
    protected static ?string $pluralModelLabel = 'Movimentações';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('produto.sku')->label('SKU')->searchable(),
                Tables\Columns\TextColumn::make('produto.nome')->label('Produto')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'entrada' => 'success',
                        'saida' => 'danger',
                        'balanco' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quantidade')->label('Qtd'),
                Tables\Columns\TextColumn::make('saldo_anterior')->label('Antes'),
                Tables\Columns\TextColumn::make('saldo_posterior')->label('Depois'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->badge(),
                Tables\Columns\TextColumn::make('referencia')->label('Ref.')->limit(30),
                Tables\Columns\TextColumn::make('user.name')->label('Usuário'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options(['entrada' => 'Entrada', 'saida' => 'Saída', 'balanco' => 'Balanço']),
                Tables\Filters\SelectFilter::make('origem')
                    ->options([
                        'manual' => 'Manual',
                        'venda_primary' => 'Venda Primary',
                        'venda_secondary' => 'Venda Secondary',
                        'importacao' => 'Importação',
                        'sync' => 'Sync',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovimentacoesEstoque::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
