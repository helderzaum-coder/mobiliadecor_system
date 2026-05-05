<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaReceberResource\Pages;
use App\Models\ContaReceber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('venda.data_venda')
                    ->label('Data Venda')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('venda.numero_pedido_canal')
                    ->label('Pedido')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('forma_pagamento')
                    ->label('Canal')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('venda.bling_account')
                    ->label('Conta')
                    ->formatStateUsing(fn (?string $state) => $state === 'primary' ? 'Mobilia' : ($state === 'secondary' ? 'HES' : '-')),
                Tables\Columns\TextColumn::make('venda.cliente_nome')
                    ->label('Cliente')
                    ->limit(25)
                    ->searchable(),
                Tables\Columns\TextColumn::make('valor_parcela')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('data_vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_recebimento')
                    ->label('Recebido em')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'recebido' => 'success',
                        'pendente' => 'warning',
                        'atrasado' => 'danger',
                        'cancelado' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('data_vencimento', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pendente' => 'Pendente',
                        'recebido' => 'Recebido',
                        'atrasado' => 'Atrasado',
                        'cancelado' => 'Cancelado',
                    ])
                    ->default('pendente'),
                Tables\Filters\SelectFilter::make('canal')
                    ->label('Canal')
                    ->options(fn () => ContaReceber::distinct()->pluck('forma_pagamento', 'forma_pagamento')->toArray())
                    ->attribute('forma_pagamento'),
                Tables\Filters\SelectFilter::make('conta')
                    ->label('Conta')
                    ->options(['primary' => 'Mobilia Decor', 'secondary' => 'HES Móveis'])
                    ->query(fn ($query, $data) => $data['value']
                        ? $query->whereHas('venda', fn ($q) => $q->where('bling_account', $data['value']))
                        : $query
                    ),
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\Select::make('periodo_rapido')
                            ->label('Período')
                            ->options([
                                'este_mes' => 'Este mês',
                                'mes_passado' => 'Mês passado',
                                'customizado' => 'Customizado',
                            ])
                            ->reactive(),
                        Forms\Components\DatePicker::make('data_inicio')
                            ->label('De')
                            ->visible(fn ($get) => $get('periodo_rapido') === 'customizado'),
                        Forms\Components\DatePicker::make('data_fim')
                            ->label('Até')
                            ->visible(fn ($get) => $get('periodo_rapido') === 'customizado'),
                    ])
                    ->query(function ($query, array $data) {
                        $periodo = $data['periodo_rapido'] ?? null;
                        if (!$periodo) return $query;
                        return match ($periodo) {
                            'este_mes' => $query->whereHas('venda', fn ($q) => $q->whereBetween('data_venda', [now()->startOfMonth(), now()->endOfMonth()])),
                            'mes_passado' => $query->whereHas('venda', fn ($q) => $q->whereBetween('data_venda', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])),
                            'customizado' => $query->whereHas('venda', fn ($q) => $q
                                ->when($data['data_inicio'] ?? null, fn ($q2) => $q2->whereDate('data_venda', '>=', $data['data_inicio']))
                                ->when($data['data_fim'] ?? null, fn ($q2) => $q2->whereDate('data_venda', '<=', $data['data_fim']))
                            ),
                            default => $query,
                        };
                    }),
            ])
            ->filtersFormColumns(4)
            ->actions([
                Tables\Actions\Action::make('confirmar_recebimento')
                    ->label('Recebido')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('Data do Recebimento')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (ContaReceber $record, array $data) {
                        $record->update([
                            'status' => 'recebido',
                            'data_recebimento' => $data['data_recebimento'],
                        ]);
                        // Atualizar venda também
                        if ($record->venda) {
                            $record->venda->update([
                                'repasse_recebido' => true,
                                'data_recebimento' => $data['data_recebimento'],
                            ]);
                        }
                        Notification::make()->title('Recebimento confirmado.')->success()->send();
                    })
                    ->visible(fn (ContaReceber $record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('desfazer')
                    ->label('Desfazer')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (ContaReceber $record) {
                        $record->update(['status' => 'pendente', 'data_recebimento' => null]);
                        if ($record->venda) {
                            $record->venda->update(['repasse_recebido' => false, 'data_recebimento' => null]);
                        }
                        Notification::make()->title('Recebimento desfeito.')->success()->send();
                    })
                    ->visible(fn (ContaReceber $record) => $record->status === 'recebido'),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('confirmar_recebimento_massa')
                    ->label('Confirmar Recebimento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('Data do Recebimento')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status !== 'pendente') continue;
                            $record->update([
                                'status' => 'recebido',
                                'data_recebimento' => $data['data_recebimento'],
                            ]);
                            if ($record->venda) {
                                $record->venda->update([
                                    'repasse_recebido' => true,
                                    'data_recebimento' => $data['data_recebimento'],
                                ]);
                            }
                            $count++;
                        }
                        Notification::make()->title("{$count} recebimento(s) confirmado(s).")->success()->send();
                    }),
                Tables\Actions\BulkAction::make('cancelar_massa')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            $record->update(['status' => 'cancelado']);
                            $count++;
                        }
                        Notification::make()->title("{$count} conta(s) cancelada(s).")->success()->send();
                    }),
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
