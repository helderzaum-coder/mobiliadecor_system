<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaReceberResource\Pages;
use App\Models\ContaReceber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContaReceberResource extends Resource
{
    protected static ?string $model = ContaReceber::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $modelLabel = 'Conta a Receber';
    protected static ?string $pluralModelLabel = 'Contas a Receber';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id_venda')
                ->label('Venda')
                ->relationship('venda', 'numero_pedido_canal')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('valor_parcela')
                ->label('Valor da Parcela')
                ->required()
                ->numeric()
                ->prefix('R$'),
            Forms\Components\DatePicker::make('data_vencimento')
                ->label('Vencimento')
                ->required(),
            Forms\Components\DatePicker::make('data_recebimento')
                ->label('Recebimento'),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'pendente' => 'Pendente',
                    'recebido' => 'Recebido',
                    'atrasado' => 'Atrasado',
                    'cancelado' => 'Cancelado',
                ])
                ->required(),
            Forms\Components\TextInput::make('numero_parcela')
                ->label('Nº Parcela')
                ->required()
                ->numeric(),
            Forms\Components\TextInput::make('total_parcelas')
                ->label('Total Parcelas')
                ->required()
                ->numeric(),
            Forms\Components\TextInput::make('forma_pagamento')
                ->label('Forma de Pagamento')
                ->required()
                ->maxLength(50),
            Forms\Components\Textarea::make('observacoes')
                ->label('Observações')
                ->columnSpanFull(),
            Forms\Components\Toggle::make('lancamento_manual')
                ->label('Lançamento Manual'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('venda.numero_pedido_canal')->label('Pedido')->searchable(),
                Tables\Columns\TextColumn::make('valor_parcela')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('data_vencimento')->label('Vencimento')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('data_recebimento')->label('Recebimento')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'recebido' => 'success',
                        'pendente' => 'warning',
                        'atrasado' => 'danger',
                        'cancelado' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('numero_parcela')->label('Parcela')
                    ->formatStateUsing(fn ($record) => "{$record->numero_parcela}/{$record->total_parcelas}"),
                Tables\Columns\TextColumn::make('forma_pagamento')->label('Pagamento'),
            ])
            ->defaultSort('data_vencimento', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pendente' => 'Pendente',
                        'recebido' => 'Recebido',
                        'atrasado' => 'Atrasado',
                        'cancelado' => 'Cancelado',
                    ]),
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
            'index' => Pages\ListContasReceber::route('/'),
            'create' => Pages\CreateContaReceber::route('/create'),
            'edit' => Pages\EditContaReceber::route('/{record}/edit'),
        ];
    }
}
