<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaPagarResource\Pages;
use App\Models\ContaPagar;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContaPagarResource extends Resource
{
    protected static ?string $model = ContaPagar::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $modelLabel = 'Conta a Pagar';
    protected static ?string $pluralModelLabel = 'Contas a Pagar';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Pagamento')->schema([
                Forms\Components\Select::make('id_fatura')
                    ->label('Fatura (Transportadora)')
                    ->relationship('fatura', 'numero_fatura')
                    ->searchable()
                    ->preload()
                    ->placeholder('Nenhuma (lançamento avulso)'),
                Forms\Components\TextInput::make('valor_parcela')
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('forma_pagamento')
                    ->label('Categoria / Tipo')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('Ex: Estorno, Frete, Fornecedor...'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pendente' => 'Pendente',
                        'pago' => 'Pago',
                        'atrasado' => 'Atrasado',
                        'cancelado' => 'Cancelado',
                    ])
                    ->required()
                    ->default('pendente'),
            ])->columns(2),

            Forms\Components\Section::make('Datas')->schema([
                Forms\Components\DatePicker::make('data_vencimento')
                    ->label('Vencimento')
                    ->required(),
                Forms\Components\DatePicker::make('data_pagamento')
                    ->label('Data do Pagamento'),
            ])->columns(2),

            Forms\Components\Section::make('Parcelas')->schema([
                Forms\Components\TextInput::make('numero_parcela')
                    ->label('Nº Parcela')
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('total_parcelas')
                    ->label('Total Parcelas')
                    ->numeric()
                    ->default(1),
            ])->columns(2),

            Forms\Components\Section::make('Observações')->schema([
                Forms\Components\Textarea::make('observacoes')
                    ->label('Observações / Descrição')
                    ->columnSpanFull()
                    ->rows(3),
                Forms\Components\Toggle::make('lancamento_manual')
                    ->label('Lançamento Manual'),
                Forms\Components\Select::make('conta_bancaria_id')
                    ->label('Banco')
                    ->relationship('contaBancaria', 'nome')
                    ->searchable()
                    ->preload()
                    ->placeholder('Selecione o banco')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(100),
                        Forms\Components\TextInput::make('banco')->label('Banco')->maxLength(100),
                    ]),
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome', fn ($query) => $query->whereIn('tipo', ['saida', 'ambos'])->where('ativo', true)->orderBy('nome'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Selecione a categoria')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(100),
                        Forms\Components\Select::make('tipo')->label('Tipo')->options(['entrada' => 'Entrada', 'saida' => 'Saída', 'ambos' => 'Ambos'])->default('saida')->required(),
                    ]),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('observacoes')
                    ->label('Descrição')
                    ->limit(50)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->observacoes),
                Tables\Columns\TextColumn::make('forma_pagamento')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn (string $state) => match (strtolower($state)) {
                        'estorno' => 'danger',
                        'frete' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fatura.numero_fatura')
                    ->label('Fatura')
                    ->placeholder('-')
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
                Tables\Columns\TextColumn::make('data_pagamento')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pago' => 'success',
                        'pendente' => 'warning',
                        'atrasado' => 'danger',
                        'cancelado' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('numero_parcela')
                    ->label('Parcela')
                    ->formatStateUsing(fn ($record) => "{$record->numero_parcela}/{$record->total_parcelas}"),
            ])
            ->defaultSort('data_vencimento', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pendente' => 'Pendente',
                        'pago' => 'Pago',
                        'atrasado' => 'Atrasado',
                        'cancelado' => 'Cancelado',
                    ])
                    ->default('pendente'),
                Tables\Filters\SelectFilter::make('forma_pagamento')
                    ->label('Categoria')
                    ->options(fn () => ContaPagar::distinct()->whereNotNull('forma_pagamento')->pluck('forma_pagamento', 'forma_pagamento')->toArray()),
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
                            'este_mes' => $query->whereBetween('data_vencimento', [now()->startOfMonth(), now()->endOfMonth()]),
                            'mes_passado' => $query->whereBetween('data_vencimento', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
                            'customizado' => $query
                                ->when($data['data_inicio'] ?? null, fn ($q) => $q->whereDate('data_vencimento', '>=', $data['data_inicio']))
                                ->when($data['data_fim'] ?? null, fn ($q) => $q->whereDate('data_vencimento', '<=', $data['data_fim'])),
                            default => $query,
                        };
                    }),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\Action::make('confirmar_pagamento')
                    ->label('Pagar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('data_pagamento')
                            ->label('Data do Pagamento')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (ContaPagar $record, array $data) {
                        $record->update([
                            'status' => 'pago',
                            'data_pagamento' => $data['data_pagamento'],
                        ]);
                        Notification::make()->title('Pagamento confirmado.')->success()->send();
                    })
                    ->visible(fn (ContaPagar $record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('desfazer_pagamento')
                    ->label('Desfazer')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (ContaPagar $record) {
                        $record->update(['status' => 'pendente', 'data_pagamento' => null]);
                        Notification::make()->title('Pagamento desfeito.')->success()->send();
                    })
                    ->visible(fn (ContaPagar $record) => $record->status === 'pago'),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('pagar_selecionados')
                    ->label('Confirmar Pagamento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('data_pagamento')
                            ->label('Data do Pagamento')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status !== 'pendente') continue;
                            $record->update(['status' => 'pago', 'data_pagamento' => $data['data_pagamento']]);
                            $count++;
                        }
                        Notification::make()->title("{$count} pagamento(s) confirmado(s).")->success()->send();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContasPagar::route('/'),
            'create' => Pages\CreateContaPagar::route('/create'),
            'edit' => Pages\EditContaPagar::route('/{record}/edit'),
        ];
    }
}
