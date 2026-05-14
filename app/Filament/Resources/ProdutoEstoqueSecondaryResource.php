<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdutoEstoqueSecondaryResource\Pages;
use App\Models\ProdutoEstoque;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProdutoEstoqueSecondaryResource extends Resource
{
    protected static ?string $model = ProdutoEstoque::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'HES Móveis (Secondary)';
    protected static ?string $modelLabel = 'Produto Secondary';
    protected static ?string $pluralModelLabel = 'Produtos Secondary';
    protected static ?string $slug = 'estoque-secondary';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->sortable()->limit(40)->tooltip(fn ($record) => $record->nome),
                Tables\Columns\TextColumn::make('formato')->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'E', 'C' => 'Kit',
                        default => 'Simples',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'E', 'C' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('componentes_count')
                    ->label('Comp.')
                    ->counts('componentes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('saldo_secondary')->label('Saldo')
                    ->sortable()
                    ->color(fn ($record) => $record->saldo_secondary <= $record->saldo_minimo ? 'danger' : 'success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('saldo_minimo')->label('Mín.')->sortable(),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->filters([
                Tables\Filters\SelectFilter::make('formato')
                    ->options(['S' => 'Simples', 'E' => 'Kit']),
                Tables\Filters\TernaryFilter::make('ativo')->label('Ativo'),
                Tables\Filters\Filter::make('estoque_baixo')
                    ->label('Estoque Baixo')
                    ->query(fn ($query) => $query->whereColumn('saldo', '<=', 'saldo_minimo')),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_componentes')
                    ->label('Componentes')
                    ->icon('heroicon-o-queue-list')
                    ->color('gray')
                    ->visible(fn ($record) => $record->isKit())
                    ->modalHeading(fn ($record) => "Componentes: {$record->nome}")
                    ->modalContent(fn ($record) => view('filament.components.componentes-kit', ['produto' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Tables\Actions\Action::make('ver_kits')
                    ->label('Kits')
                    ->icon('heroicon-o-rectangle-group')
                    ->color('gray')
                    ->visible(fn ($record) => !$record->isKit() && $record->kits()->exists())
                    ->modalHeading(fn ($record) => "Kits que contêm: {$record->nome}")
                    ->modalContent(fn ($record) => view('filament.components.kits-do-produto', ['produto' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdutosEstoqueSecondary::route('/'),
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
