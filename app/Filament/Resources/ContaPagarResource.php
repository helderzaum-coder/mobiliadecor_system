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
use Illuminate\Support\Str;
use Carbon\Carbon;

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
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('descricao')
                    ->label('Descrição / Nome da Conta')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Ex: Aluguel, Internet, Salário, Frete...'),
                Forms\Components\Select::make('id_fatura')
                    ->label('Fatura (Transportadora)')
                    ->relationship('fatura', 'numero_fatura')
                    ->searchable()
                    ->preload()
                    ->placeholder('Nenhuma (lançamento avulso)'),
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->options(fn () => \App\Models\CategoriaFinanceira::whereIn('tipo', ['saida', 'ambos'])->where('ativo', true)->where('sistema', false)->orderBy('nome')->pluck('nome', 'id')->toArray())
                    ->searchable()
                    ->placeholder('Selecione a categoria')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(100),
                        Forms\Components\Select::make('tipo')->label('Tipo')->options(['entrada' => 'Entrada', 'saida' => 'Saída', 'ambos' => 'Ambos'])->default('saida')->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => \App\Models\CategoriaFinanceira::create($data)->id),
            ])->columns(2),

            Forms\Components\Section::make('Valores e Pagamento')->schema([
                Forms\Components\TextInput::make('valor_parcela')
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\Select::make('forma_pagamento')
                    ->label('Forma de Pagamento')
                    ->options([
                        'pix' => 'Pix',
                        'boleto' => 'Boleto',
                        'cartao' => 'Cartão',
                        'transferencia' => 'Transferência',
                        'dinheiro' => 'Dinheiro',
                        'debito_automatico' => 'Débito Automático',
                    ])
                    ->placeholder('Selecione...'),
                Forms\Components\Select::make('conta_bancaria_id')
                    ->label('Banco')
                    ->relationship('contaBancaria', 'nome')
                    ->searchable()
                    ->preload()
                    ->placeholder('Selecione o banco'),
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
                Forms\Components\DatePicker::make('data_lancamento')
                    ->label('Data do Lançamento')
                    ->default(now())
                    ->required(),
                Forms\Components\DatePicker::make('data_vencimento')
                    ->label('Data de Vencimento')
                    ->required(),
                Forms\Components\DatePicker::make('data_pagamento')
                    ->label('Data do Pagamento')
                    ->helperText('Preencha apenas quando efetivamente pago'),
            ])->columns(3),

            Forms\Components\Section::make('Parcelas / Recorrência')->schema([
                Forms\Components\Toggle::make('recorrente')
                    ->label('Pagamento Recorrente')
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $set('numero_parcela', null);
                            $set('total_parcelas', null);
                        } else {
                            $set('numero_parcela', 1);
                            $set('total_parcelas', 1);
                            $set('intervalo_recorrencia', null);
                            $set('data_fim_recorrencia', null);
                        }
                    }),
                Forms\Components\Select::make('intervalo_recorrencia')
                    ->label('Intervalo')
                    ->options([
                        'semanal' => 'Semanal',
                        'quinzenal' => 'Quinzenal',
                        'mensal' => 'Mensal',
                    ])
                    ->visible(fn ($get) => $get('recorrente'))
                    ->required(fn ($get) => $get('recorrente')),
                Forms\Components\DatePicker::make('data_fim_recorrencia')
                    ->label('Fim da Recorrência')
                    ->helperText('Deixe vazio para recorrência indefinida')
                    ->visible(fn ($get) => $get('recorrente')),
                Forms\Components\TextInput::make('numero_parcela')
                    ->label('Nº Parcela')
                    ->numeric()
                    ->default(1)
                    ->visible(fn ($get) => !$get('recorrente')),
                Forms\Components\TextInput::make('total_parcelas')
                    ->label('Total Parcelas')
                    ->numeric()
                    ->default(1)
                    ->visible(fn ($get) => !$get('recorrente')),
            ])->columns(2),

            Forms\Components\Section::make('Juros por Atraso')->schema([
                Forms\Components\TextInput::make('juros_atraso')
                    ->label('Juros (%)')
                    ->numeric()
                    ->placeholder('Ex: 2.00')
                    ->helperText('Percentual aplicado por dia ou por mês de atraso'),
                Forms\Components\Select::make('tipo_juros')
                    ->label('Tipo de Juros')
                    ->options([
                        'ao_dia' => 'Ao dia',
                        'ao_mes' => 'Ao mês',
                    ])
                    ->placeholder('Selecione...')
                    ->required(fn ($get) => filled($get('juros_atraso'))),
            ])->columns(2),

            Forms\Components\Section::make('Observações')->schema([
                Forms\Components\Textarea::make('observacoes')
                    ->label('Observações')
                    ->columnSpanFull()
                    ->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->descricao),
                Tables\Columns\TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->badge()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('fatura.numero_fatura')
                    ->label('Fatura')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('valor_parcela')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valor_atualizado')
                    ->label('Valor Atual')
                    ->getStateUsing(fn ($record) => $record->valor_atualizado)
                    ->money('BRL')
                    ->color(fn ($record) => $record->dias_atraso > 0 ? 'danger' : null)
                    ->tooltip(fn ($record) => $record->dias_atraso > 0 ? "{$record->dias_atraso} dia(s) de atraso" : null),
                Tables\Columns\TextColumn::make('data_vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->dias_atraso > 0 && $record->status !== 'pago' ? 'danger' : null),
                Tables\Columns\TextColumn::make('data_pagamento')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('recorrente')
                    ->label('Recorr.')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->formatStateUsing(fn ($record) => $record->recorrente
                        ? 'Recorrente'
                        : "{$record->numero_parcela}/{$record->total_parcelas}")
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\SelectFilter::make('categoria_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome'),
                Tables\Filters\TernaryFilter::make('recorrente')
                    ->label('Recorrente')
                    ->placeholder('Todos')
                    ->trueLabel('Somente recorrentes')
                    ->falseLabel('Somente avulsos'),
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\Select::make('periodo_rapido')
                            ->label('Período')
                            ->options([
                                'este_mes' => 'Este mês',
                                'mes_passado' => 'Mês passado',
                                'proximo_mes' => 'Próximo mês',
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
                            'proximo_mes' => $query->whereBetween('data_vencimento', [now()->addMonth()->startOfMonth(), now()->addMonth()->endOfMonth()]),
                            'customizado' => $query
                                ->when($data['data_inicio'] ?? null, fn ($q) => $q->whereDate('data_vencimento', '>=', $data['data_inicio']))
                                ->when($data['data_fim'] ?? null, fn ($q) => $q->whereDate('data_vencimento', '<=', $data['data_fim'])),
                            default => $query,
                        };
                    }),
            ])
            ->filtersFormColumns(4)
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
                    ->visible(fn (ContaPagar $record) => in_array($record->status, ['pendente', 'atrasado'])),
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
                Tables\Actions\Action::make('duplicar')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->form(fn (ContaPagar $record) => [
                        Forms\Components\TextInput::make('descricao')
                            ->label('Descrição')
                            ->default($record->descricao)
                            ->required(),
                        Forms\Components\TextInput::make('valor_parcela')
                            ->label('Valor')
                            ->numeric()
                            ->prefix('R$')
                            ->default($record->valor_parcela)
                            ->required(),
                        Forms\Components\DatePicker::make('data_vencimento')
                            ->label('Vencimento')
                            ->default(now())
                            ->required(),
                        Forms\Components\DatePicker::make('data_pagamento')
                            ->label('Data do Pagamento (se já pago)'),
                    ])
                    ->action(function (ContaPagar $record, array $data) {
                        $novo = $record->replicate(['data_pagamento', 'grupo_recorrencia']);
                        $novo->descricao = $data['descricao'];
                        $novo->valor_parcela = $data['valor_parcela'];
                        $novo->data_vencimento = $data['data_vencimento'];
                        $novo->data_lancamento = now();
                        $novo->data_pagamento = $data['data_pagamento'] ?? null;
                        $novo->status = $data['data_pagamento'] ? 'pago' : 'pendente';
                        $novo->numero_parcela = 1;
                        $novo->total_parcelas = 1;
                        $novo->recorrente = false;
                        $novo->save();

                        Notification::make()->title('Conta duplicada com sucesso.')->success()->send();
                    }),
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
                            if (!in_array($record->status, ['pendente', 'atrasado'])) continue;
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
