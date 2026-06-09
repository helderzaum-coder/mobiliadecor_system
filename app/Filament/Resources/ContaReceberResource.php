<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaReceberResource\Pages;
use App\Models\ContaReceber;
use App\Models\LoteRecebimento;
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
            Forms\Components\Section::make('Identificação')->schema([
                Forms\Components\TextInput::make('observacoes')
                    ->label('Descrição')
                    ->placeholder('Ex: Repasse Shopee, Saque, Entrada avulsa...')
                    ->columnSpanFull(),
                Forms\Components\Select::make('id_venda')
                    ->label('Venda (opcional)')
                    ->relationship('venda', 'numero_pedido_canal')
                    ->searchable()
                    ->preload()
                    ->placeholder('Nenhuma (entrada avulsa)'),
            ])->columns(1),

            Forms\Components\Section::make('Valores e Pagamento')->schema([
                Forms\Components\TextInput::make('valor_parcela')
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\Select::make('forma_pagamento')
                    ->label('Forma / Canal')
                    ->options([
                        'Pix' => 'Pix',
                        'Boleto' => 'Boleto',
                        'Transferência' => 'Transferência',
                        'Mercadolivre' => 'Mercadolivre',
                        'Shopee' => 'Shopee',
                        'Magalu' => 'Magalu',
                        'Outro' => 'Outro',
                    ])
                    ->required()
                    ->placeholder('Selecione...'),
                Forms\Components\Select::make('conta_bancaria_id')
                    ->label('Banco')
                    ->relationship('contaBancaria', 'nome')
                    ->searchable()
                    ->preload()
                    ->placeholder('Selecione o banco'),
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->options(fn () => \App\Models\CategoriaFinanceira::whereIn('tipo', ['entrada', 'ambos'])->where('ativo', true)->where('sistema', false)->orderBy('nome')->pluck('nome', 'id')->toArray())
                    ->searchable()
                    ->placeholder('Selecione a categoria')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nome')->label('Nome')->required()->maxLength(100),
                        Forms\Components\Select::make('tipo')->label('Tipo')->options(['entrada' => 'Entrada', 'saida' => 'Saída', 'ambos' => 'Ambos'])->default('entrada')->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => \App\Models\CategoriaFinanceira::create($data)->id),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pendente' => 'Pendente',
                        'recebido' => 'Recebido',
                        'cancelado' => 'Cancelado',
                    ])
                    ->required()
                    ->default('pendente'),
            ])->columns(2),

            Forms\Components\Section::make('Datas')->schema([
                Forms\Components\DatePicker::make('data_vencimento')
                    ->label('Data de Vencimento')
                    ->required()
                    ->default(now()),
                Forms\Components\DatePicker::make('data_recebimento')
                    ->label('Data do Recebimento')
                    ->helperText('Preencha apenas quando efetivamente recebido'),
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
                Tables\Columns\TextColumn::make('parcela_info')
                    ->label('Parcela')
                    ->getStateUsing(function (ContaReceber $record) {
                        if ($record->total_parcelas <= 1) return null;
                        return $record->numero_parcela . '/' . $record->total_parcelas;
                    })
                    ->badge()
                    ->color('info')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('valor_ja_recebido')
                    ->label('Já Recebido')
                    ->getStateUsing(function (ContaReceber $record) {
                        if (!$record->id_venda || $record->total_parcelas <= 1) return null;
                        $recebido = ContaReceber::where('id_venda', $record->id_venda)
                            ->where('status', 'recebido')
                            ->sum('valor_parcela');
                        return $recebido > 0 ? $recebido : null;
                    })
                    ->money('BRL')
                    ->color('success')
                    ->placeholder('-'),
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
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->estorno_pendente) return $state . ' ⚠️ Estorno';
                        if ($state === 'pendente' && $record->total_parcelas > 1) {
                            return "pendente ({$record->numero_parcela}/{$record->total_parcelas})";
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('contaBancaria.nome')
                    ->label('Banco')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('observacoes')
                    ->label('Obs')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lote_recebimento_id')
                    ->label('Lote')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : null)
                    ->url(fn ($record) => $record->lote_recebimento_id
                        ? '/lotes-recebimento/' . $record->lote_recebimento_id
                        : null
                    )
                    ->color('primary'),
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
                Tables\Filters\SelectFilter::make('conta_bancaria_id')
                    ->label('Banco')
                    ->relationship('contaBancaria', 'nome'),
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\Select::make('filtrar_por')
                            ->label('Filtrar por')
                            ->options([
                                'data_venda' => '📅 Data da Venda',
                                'data_recebimento' => '💰 Data do Recebimento',
                            ])
                            ->default('data_venda')
                            ->reactive(),
                        Forms\Components\Select::make('periodo_rapido')
                            ->label('Período')
                            ->options([
                                'este_mes' => 'Este mês',
                                'mes_passado' => 'Mês passado',
                                'selecionar_mes' => 'Selecionar mês',
                                'customizado' => 'Customizado',
                            ])
                            ->reactive(),
                        Forms\Components\Select::make('mes_selecionado')
                            ->label('Mês')
                            ->options(function () {
                                $options = [];
                                for ($i = 0; $i < 12; $i++) {
                                    $d = now()->subMonths($i)->startOfMonth();
                                    $options[$d->format('Y-m')] = ucfirst($d->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
                                }
                                return $options;
                            })
                            ->visible(fn ($get) => $get('periodo_rapido') === 'selecionar_mes'),
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

                        $filtrarPor = $data['filtrar_por'] ?? 'data_venda';

                        if ($filtrarPor === 'data_recebimento') {
                            return match ($periodo) {
                                'este_mes' => $query->where(fn ($q) => $q
                                    ->whereBetween('data_recebimento', [now()->startOfMonth(), now()->endOfMonth()])
                                    ->orWhereHas('venda', fn ($q2) => $q2->whereBetween('data_recebimento', [now()->startOfMonth(), now()->endOfMonth()]))
                                ),
                                'mes_passado' => $query->where(fn ($q) => $q
                                    ->whereBetween('data_recebimento', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                                    ->orWhereHas('venda', fn ($q2) => $q2->whereBetween('data_recebimento', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]))
                                ),
                                'selecionar_mes' => isset($data['mes_selecionado']) && $data['mes_selecionado']
                                    ? $query->where(fn ($q) => $q
                                        ->whereBetween('data_recebimento', [
                                            now()->createFromFormat('Y-m', $data['mes_selecionado'])->startOfMonth(),
                                            now()->createFromFormat('Y-m', $data['mes_selecionado'])->endOfMonth(),
                                        ])
                                        ->orWhereHas('venda', fn ($q2) => $q2->whereBetween('data_recebimento', [
                                            now()->createFromFormat('Y-m', $data['mes_selecionado'])->startOfMonth(),
                                            now()->createFromFormat('Y-m', $data['mes_selecionado'])->endOfMonth(),
                                        ]))
                                    )
                                    : $query,
                                'customizado' => $query->where(fn ($q) => $q
                                    ->where(fn ($q2) => $q2
                                        ->when($data['data_inicio'] ?? null, fn ($q3) => $q3->whereDate('data_recebimento', '>=', $data['data_inicio']))
                                        ->when($data['data_fim'] ?? null, fn ($q3) => $q3->whereDate('data_recebimento', '<=', $data['data_fim']))
                                    )
                                    ->orWhereHas('venda', fn ($q2) => $q2
                                        ->when($data['data_inicio'] ?? null, fn ($q3) => $q3->whereDate('data_recebimento', '>=', $data['data_inicio']))
                                        ->when($data['data_fim'] ?? null, fn ($q3) => $q3->whereDate('data_recebimento', '<=', $data['data_fim']))
                                    )
                                ),
                                default => $query,
                            };
                        }

                        return match ($periodo) {
                            'este_mes' => $query->whereHas('venda', fn ($q) => $q->whereBetween('data_venda', [now()->startOfMonth(), now()->endOfMonth()])),
                            'mes_passado' => $query->whereHas('venda', fn ($q) => $q->whereBetween('data_venda', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])),
                            'selecionar_mes' => isset($data['mes_selecionado']) && $data['mes_selecionado']
                                ? $query->whereHas('venda', fn ($q) => $q->whereBetween('data_venda', [
                                    now()->createFromFormat('Y-m', $data['mes_selecionado'])->startOfMonth(),
                                    now()->createFromFormat('Y-m', $data['mes_selecionado'])->endOfMonth(),
                                ]))
                                : $query,
                            'customizado' => $query->whereHas('venda', fn ($q) => $q
                                ->when($data['data_inicio'] ?? null, fn ($q2) => $q2->whereDate('data_venda', '>=', $data['data_inicio']))
                                ->when($data['data_fim'] ?? null, fn ($q2) => $q2->whereDate('data_venda', '<=', $data['data_fim']))
                            ),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data) {
                        $filtrarPor = $data['filtrar_por'] ?? 'data_venda';
                        $periodo = $data['periodo_rapido'] ?? null;
                        if (!$periodo) return null;
                        $prefixo = $filtrarPor === 'data_recebimento' ? '💰 Recebimento: ' : '📅 Venda: ';
                        return $prefixo . match ($periodo) {
                            'este_mes' => 'Este mês',
                            'mes_passado' => 'Mês passado',
                            'selecionar_mes' => $data['mes_selecionado'] ?? 'Mês',
                            'customizado' => trim(($data['data_inicio'] ?? '') . ' → ' . ($data['data_fim'] ?? '')),
                            default => '',
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
                Tables\Actions\Action::make('baixa_parcial')
                    ->label('Baixa Parcial')
                    ->icon('heroicon-o-scissors')
                    ->color('warning')
                    ->form([
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content(fn (ContaReceber $record) => 'Valor total pendente: R$ ' . number_format((float) $record->valor_parcela, 2, ',', '.')),
                        Forms\Components\TextInput::make('total_parcelas')
                            ->label('Quantidade de parcelas')
                            ->numeric()
                            ->required()
                            ->default(2)
                            ->minValue(2)
                            ->helperText('O valor será dividido igualmente entre as parcelas'),
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('Data do 1º recebimento')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (ContaReceber $record, array $data) {
                        $totalParcelas = (int) $data['total_parcelas'];
                        $valorTotal = (float) $record->valor_parcela;
                        $valorParcela = round($valorTotal / $totalParcelas, 2);
                        // Ajustar centavos na última parcela
                        $valorRestante = round($valorTotal - $valorParcela, 2);

                        if ($totalParcelas < 2) {
                            Notification::make()->title('Mínimo 2 parcelas.')->warning()->send();
                            return;
                        }

                        // Marcar 1ª parcela como recebida
                        $record->update([
                            'valor_parcela' => $valorParcela,
                            'status' => 'recebido',
                            'data_recebimento' => $data['data_recebimento'],
                            'numero_parcela' => 1,
                            'total_parcelas' => $totalParcelas,
                        ]);

                        // Criar registro com o restante (parcelas 2 a N)
                        ContaReceber::create([
                            'id_venda' => $record->id_venda,
                            'valor_parcela' => $valorRestante,
                            'data_vencimento' => $record->data_vencimento,
                            'status' => 'pendente',
                            'numero_parcela' => 2,
                            'total_parcelas' => $totalParcelas,
                            'forma_pagamento' => $record->forma_pagamento,
                            'observacoes' => ($record->observacoes ?? '') . " (parcelas 2-{$totalParcelas})",
                            'lancamento_manual' => false,
                            'conta_bancaria_id' => $record->conta_bancaria_id,
                            'categoria_id' => $record->categoria_id,
                        ]);

                        Notification::make()
                            ->title("Parcela 1/{$totalParcelas}: R$ " . number_format($valorParcela, 2, ',', '.') . " recebida. Restante: R$ " . number_format($valorRestante, 2, ',', '.'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (ContaReceber $record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('desfazer')
                    ->label('Desfazer')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (ContaReceber $record) {
                        $record->update(['status' => 'pendente', 'data_recebimento' => null, 'lote_recebimento_id' => null]);
                        if ($record->venda) {
                            $record->venda->update(['repasse_recebido' => false, 'data_recebimento' => null]);
                        }
                        Notification::make()->title('Recebimento desfeito.')->success()->send();
                    })
                    ->visible(fn (ContaReceber $record) => in_array($record->status, ['recebido', 'cancelado'])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('confirmar_recebimento_massa')
                    ->label('Confirmar Recebimento')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form(fn (Tables\Actions\BulkAction $action) => [
                        Forms\Components\Placeholder::make('aviso')
                            ->label('')
                            ->content(function () use ($action) {
                                $records = $action->getRecords();
                                $total = $records->where('status', 'pendente')->sum('valor_parcela');
                                $qtd = $records->where('status', 'pendente')->count();
                                return "⚠️ {$qtd} registro(s) selecionado(s) — Total: R$ " . number_format((float) $total, 2, ',', '.');
                            }),
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('📅 Data do Recebimento')
                            ->required()
                            ->helperText('Informe a data em que o valor foi efetivamente recebido.'),
                        Forms\Components\Select::make('conta_bancaria_id')
                            ->label('Banco')
                            ->relationship('contaBancaria', 'nome')
                            ->searchable()
                            ->preload()
                            ->placeholder('Selecione o banco (opcional)'),
                        Forms\Components\TextInput::make('descricao')
                            ->label('Descrição do Lote (opcional)')
                            ->placeholder('Ex: Repasse ML semana 23')
                            ->maxLength(255),
                    ])
                    ->modalHeading('Confirmar Recebimento em Lote')
                    ->modalDescription('Verifique a data de recebimento antes de confirmar.')
                    ->modalSubmitActionLabel('Confirmar Recebimento')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records, array $data) {
                        $count = 0;
                        $valorTotal = 0;
                        foreach ($records as $record) {
                            if ($record->status !== 'pendente') continue;
                            $record->update([
                                'status' => 'recebido',
                                'data_recebimento' => $data['data_recebimento'],
                                'conta_bancaria_id' => $data['conta_bancaria_id'] ?? null,
                            ]);
                            if ($record->venda) {
                                $record->venda->update([
                                    'repasse_recebido' => true,
                                    'data_recebimento' => $data['data_recebimento'],
                                ]);
                            }
                            $valorTotal += (float) $record->valor_parcela;
                            $count++;
                        }

                        $lote = null;
                        if ($count > 0) {
                            $lote = LoteRecebimento::create([
                                'data_recebimento' => $data['data_recebimento'],
                                'descricao' => $data['descricao'] ?? null,
                                'valor_total' => round($valorTotal, 2),
                                'quantidade_contas' => $count,
                            ]);
                            foreach ($records as $record) {
                                if ($record->status === 'recebido' && !$record->lote_recebimento_id) {
                                    $record->update(['lote_recebimento_id' => $lote->id]);
                                }
                            }
                        }

                        $titulo = $lote
                            ? "{$count} recebimento(s) confirmado(s) — Lote #{$lote->id}"
                            : 'Nenhum registro pendente selecionado.';
                        Notification::make()->title($titulo)->success()->send();
                    }),
                Tables\Actions\BulkAction::make('alterar_data_recebimento')
                    ->label('Corrigir Data Recebimento')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->form([
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('Nova Data de Recebimento')
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status !== 'recebido') continue;
                            $record->update(['data_recebimento' => $data['data_recebimento']]);
                            if ($record->venda) {
                                $record->venda->update(['data_recebimento' => $data['data_recebimento']]);
                            }
                            $count++;
                        }
                        Notification::make()->title("{$count} data(s) corrigida(s).")->success()->send();
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
