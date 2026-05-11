<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdutoEstoqueResource\Pages;
use App\Models\ProdutoEstoque;
use App\Services\EstoqueService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProdutoEstoqueResource extends Resource
{
    protected static ?string $model = ProdutoEstoque::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Produtos';
    protected static ?string $modelLabel = 'Produto';
    protected static ?string $pluralModelLabel = 'Produtos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sku')->label('SKU')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('nome')->label('Nome')->required(),
            Forms\Components\Select::make('formato')->label('Formato')->options([
                'S' => 'Simples',
                'E' => 'Kit/Estrutura',
            ])->default('S')->required(),
            Forms\Components\TextInput::make('saldo')->label('Saldo')->numeric()->default(0),
            Forms\Components\TextInput::make('saldo_minimo')->label('Saldo Mínimo')->numeric()->default(0),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->sortable()->limit(40),
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
                Tables\Columns\TextColumn::make('saldo')->label('Saldo')
                    ->sortable()
                    ->color(fn ($record) => $record->saldo <= $record->saldo_minimo ? 'danger' : 'success')
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
                Tables\Actions\Action::make('entrada')
                    ->label('Entrada')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('quantidade')->label('Quantidade')->numeric()->required()->minValue(1),
                        Forms\Components\TextInput::make('referencia')->label('Referência/Obs')->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $res = EstoqueService::entrada($record->sku, (int) $data['quantidade'], 'manual', $data['referencia'] ?? null, auth()->id());
                        if ($res['success']) {
                            Notification::make()->title("Entrada: {$record->sku} → saldo {$res['saldo']}")->success()->send();
                        } else {
                            Notification::make()->title($res['erro'] ?? 'Erro')->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('saida')
                    ->label('Saída')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->form([
                        Forms\Components\TextInput::make('quantidade')->label('Quantidade')->numeric()->required()->minValue(1),
                        Forms\Components\TextInput::make('referencia')->label('Referência/Obs')->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $res = EstoqueService::saida($record->sku, (int) $data['quantidade'], 'manual', $data['referencia'] ?? null, auth()->id());
                        if ($res['success']) {
                            Notification::make()->title("Saída: {$record->sku} → saldo {$res['saldo']}")->success()->send();
                        } else {
                            Notification::make()->title($res['erro'] ?? 'Erro')->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('balanco')
                    ->label('Balanço')
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('novo_saldo')->label('Novo Saldo')->numeric()->required()->minValue(0),
                        Forms\Components\TextInput::make('referencia')->label('Referência/Obs')->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $res = EstoqueService::balanco($record->sku, (int) $data['novo_saldo'], 'manual', $data['referencia'] ?? null, auth()->id());
                        if ($res['success']) {
                            Notification::make()->title("Balanço: {$record->sku} → saldo {$res['saldo']}")->success()->send();
                        } else {
                            Notification::make()->title($res['erro'] ?? 'Erro')->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('importar_bling')
                    ->label('Importar do Bling')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Importa todos os produtos e saldos do Bling Primary. Produtos existentes terão saldo atualizado.')
                    ->action(function () {
                        \App\Jobs\ImportarProdutosBlingJob::dispatch();
                        Notification::make()->title('Importação iniciada em background.')->info()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdutosEstoque::route('/'),
            'create' => Pages\CreateProdutoEstoque::route('/create'),
            'edit' => Pages\EditProdutoEstoque::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
