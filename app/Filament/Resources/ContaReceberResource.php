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

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

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
                    ->options(function () {
                        $canais = \App\Models\CanalVenda::where('ativo', true)->orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray();
                        $fixos = ['Pix' => 'Pix', 'Boleto' => 'Boleto', 'Transferência' => 'Transferência', 'Outro' => 'Outro'];
                        return array_merge($canais, $fixos);
                    })
                    ->required()
                    ->searchable()
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
                        'pendente'  => 'Pendente',
                        'recebido'  => 'Recebido',
                        'ajuste'    => 'Ajuste (não afeta caixa)',
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
            ->contentFooter(view('filament.resources.conta-receber.soma-selecionados'))
            ->columns([
                Tables\Columns\TextColumn::make('venda.data_venda')
                    ->label('Data Venda')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('venda.numero_pedido_canal')
                    ->label('Pedido')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('observacoes_search')
                    ->label('')
                    ->getStateUsing(fn () => null)
                    ->searchable(query: fn ($query, $search) => $query->orWhere('observacoes', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->searchable(query: fn ($query, $search) => $query
                        ->orWhereHas('venda', fn ($q) => $q->where('cliente_nome', 'like', "%{$search}%"))
                    ),
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
                        'recebido'  => 'success',
                        'pendente'  => 'warning',
                        'atrasado'  => 'danger',
                        'ajuste'    => 'info',
                        'cancelado' => 'gray',
                        default     => 'gray',
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->estorno_pendente) return $state . ' ⚠️ Estorno';
                        if ($state === 'ajuste') return '🔧 Ajuste';
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
                    ->placeholder('-'),
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
                        'pendente'  => 'Pendente',
                        'recebido'  => 'Recebido',
                        'atrasado'  => 'Atrasado',
                        'ajuste'    => 'Ajuste',
                        'cancelado' => 'Cancelado',
                    ]),
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
                Tables\Filters\SelectFilter::make('lote_recebimento_id')
                    ->label('Lote')
                    ->placeholder('Todos')
                    ->options(fn () => LoteRecebimento::orderBy('id', 'desc')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn ($l) => [$l->id => "#{$l->id} — {$l->descricao}"])
                        ->toArray()
                    )
                    ->searchable(),
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\Select::make('filtrar_por')
                            ->label('Filtrar por')
                            ->options([
                                'data_vencimento'   => '📅 Data de Vencimento',
                                'data_recebimento'  => '💰 Data do Recebimento',
                                'data_venda'        => '🛒 Data da Venda',
                            ])
                            ->default('data_vencimento')
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

                        $filtrarPor = $data['filtrar_por'] ?? 'data_vencimento';

                        if ($filtrarPor === 'data_vencimento') {
                            return match ($periodo) {
                                'este_mes'      => $query->whereBetween('data_vencimento', [now()->startOfMonth(), now()->endOfMonth()]),
                                'mes_passado'   => $query->whereBetween('data_vencimento', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
                                'selecionar_mes' => isset($data['mes_selecionado']) && $data['mes_selecionado']
                                    ? $query->whereBetween('data_vencimento', [
                                        now()->createFromFormat('Y-m', $data['mes_selecionado'])->startOfMonth(),
                                        now()->createFromFormat('Y-m', $data['mes_selecionado'])->endOfMonth(),
                                    ])
                                    : $query,
                                'customizado'   => $query
                                    ->when($data['data_inicio'] ?? null, fn ($q) => $q->whereDate('data_vencimento', '>=', $data['data_inicio']))
                                    ->when($data['data_fim'] ?? null, fn ($q) => $q->whereDate('data_vencimento', '<=', $data['data_fim'])),
                                default => $query,
                            };
                        }

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
                        $filtrarPor = $data['filtrar_por'] ?? 'data_vencimento';
                        $periodo = $data['periodo_rapido'] ?? null;
                        if (!$periodo) return null;
                        $prefixo = match($filtrarPor) {
                            'data_recebimento' => '💰 Recebimento: ',
                            'data_venda'       => '🛒 Venda: ',
                            default            => '📅 Vencimento: ',
                        };
                        return $prefixo . match ($periodo) {
                            'este_mes' => 'Este mês',
                            'mes_passado' => 'Mês passado',
                            'selecionar_mes' => $data['mes_selecionado'] ?? 'Mês',
                            'customizado' => trim(($data['data_inicio'] ?? '') . ' → ' . ($data['data_fim'] ?? '')),
                            default => '',
                        };
                    }),
            ])
            ->filtersFormColumns(5)
            ->actions([
                Tables\Actions\Action::make('marcar_ajuste')
                    ->label('Ajuste')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como Ajuste')
                    ->modalDescription('O registro será marcado como recebido mas NÃO aparecerá no fluxo de caixa nem afetará saldos.')
                    ->action(function (ContaReceber $record) {
                        $record->update([
                            'status' => 'recebido',
                            'data_recebimento' => $record->data_recebimento ?? now(),
                            'conta_bancaria_id' => null,
                        ]);
                        if ($record->id_venda) {
                            $pendentes = ContaReceber::where('id_venda', $record->id_venda)
                                ->where('status', 'pendente')->count();
                            if ($pendentes === 0) {
                                $record->venda?->update(['repasse_recebido' => true, 'data_recebimento' => $record->data_recebimento ?? now()]);
                            }
                        }
                        Notification::make()->title('Marcado como recebido (ajuste).')->success()->send();
                    })
                    ->visible(fn (ContaReceber $record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('confirmar_recebimento')
                    ->label('Recebido')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('Data do Recebimento')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('conta_bancaria_id')
                            ->label('Banco')
                            ->options(fn () => \App\Models\ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->searchable()
                            ->placeholder('Selecione o banco'),
                    ])
                    ->action(function (ContaReceber $record, array $data) {
                        $record->update([
                            'status' => 'recebido',
                            'data_recebimento' => $data['data_recebimento'],
                            'conta_bancaria_id' => $data['conta_bancaria_id'] ?? $record->conta_bancaria_id,
                        ]);
                        // Marcar venda como recebida somente se todas as contas estão recebidas
                        if ($record->venda) {
                            $pendentes = ContaReceber::where('id_venda', $record->id_venda)
                                ->where('status', 'pendente')->count();
                            if ($pendentes === 0) {
                                $record->venda->update([
                                    'repasse_recebido' => true,
                                    'data_recebimento' => $data['data_recebimento'],
                                ]);
                            }
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
                Tables\Actions\Action::make('duplicar')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->form(fn (ContaReceber $record) => [
                        Forms\Components\TextInput::make('observacoes')
                            ->label('Descrição')
                            ->default($record->observacoes)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('valor_parcela')
                            ->label('Valor')
                            ->numeric()
                            ->prefix('R$')
                            ->default($record->valor_parcela)
                            ->required(),
                        Forms\Components\Select::make('forma_pagamento')
                            ->label('Forma / Canal')
                            ->options(function () {
                                $canais = \App\Models\CanalVenda::where('ativo', true)->orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray();
                                $fixos = ['Pix' => 'Pix', 'Boleto' => 'Boleto', 'Transferência' => 'Transferência', 'Outro' => 'Outro'];
                                return array_merge($canais, $fixos);
                            })
                            ->default($record->forma_pagamento)
                            ->searchable(),
                        Forms\Components\Select::make('categoria_id')
                            ->label('Categoria')
                            ->options(fn () => \App\Models\CategoriaFinanceira::whereIn('tipo', ['entrada', 'ambos'])->where('ativo', true)->where('sistema', false)->orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->default($record->categoria_id)
                            ->searchable(),
                        Forms\Components\Select::make('conta_bancaria_id')
                            ->label('Banco')
                            ->options(fn () => \App\Models\ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->default($record->conta_bancaria_id)
                            ->searchable(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(['pendente' => 'Pendente', 'recebido' => 'Recebido'])
                            ->default('pendente')
                            ->required()
                            ->reactive(),
                        Forms\Components\DatePicker::make('data_vencimento')
                            ->label('Data de Vencimento')
                            ->default($record->data_vencimento)
                            ->required(),
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('Data do Recebimento')
                            ->default(null)
                            ->visible(fn ($get) => $get('status') === 'recebido'),
                    ])
                    ->action(function (ContaReceber $record, array $data) {
                        $novo = $record->replicate();
                        $novo->observacoes       = $data['observacoes'] ?? $record->observacoes;
                        $novo->valor_parcela     = $data['valor_parcela'];
                        $novo->forma_pagamento   = $data['forma_pagamento'] ?? $record->forma_pagamento;
                        $novo->categoria_id      = $data['categoria_id'] ?? $record->categoria_id;
                        $novo->conta_bancaria_id = $data['conta_bancaria_id'] ?? $record->conta_bancaria_id;
                        $novo->status            = $data['status'];
                        $novo->data_vencimento   = $data['data_vencimento'];
                        $novo->data_recebimento  = $data['status'] === 'recebido' ? ($data['data_recebimento'] ?? null) : null;
                        $novo->lote_recebimento_id = null;
                        $novo->numero_parcela    = 1;
                        $novo->total_parcelas    = 1;
                        $novo->lancamento_manual = true;
                        $novo->save();
                        Notification::make()->title('Conta duplicada com sucesso.')->success()->send();
                    }),
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
                    ->form([
                        Forms\Components\DatePicker::make('data_recebimento')
                            ->label('📅 Data do Recebimento')
                            ->required()
                            ->helperText('Informe a data em que o valor foi efetivamente recebido.'),
                        Forms\Components\Select::make('conta_bancaria_id')
                            ->label('Banco')
                            ->options(fn () => \App\Models\ContaBancaria::orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->searchable()
                            ->placeholder('Selecione o banco (opcional)'),
                        Forms\Components\TextInput::make('descricao')
                            ->label('Descrição do Lote (opcional)')
                            ->placeholder('Ex: Repasse ML semana 23')
                            ->maxLength(255),
                    ])
                    ->modalHeading('Confirmar Recebimento em Lote')
                    ->modalSubmitActionLabel('Confirmar Recebimento')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records, array $data) {
                        $count = 0;
                        $valorTotal = 0;
                        $processedIds = [];
                        foreach ($records as $record) {
                            if ($record->status !== 'pendente') continue;
                            $record->update([
                                'status' => 'recebido',
                                'data_recebimento' => $data['data_recebimento'],
                                'conta_bancaria_id' => $data['conta_bancaria_id'] ?? null,
                            ]);
                            if ($record->venda) {
                                $pendentes = ContaReceber::where('id_venda', $record->id_venda)
                                    ->where('status', 'pendente')->count();
                                if ($pendentes === 0) {
                                    $record->venda->update([
                                        'repasse_recebido' => true,
                                        'data_recebimento' => $data['data_recebimento'],
                                    ]);
                                }
                            }
                            $processedIds[] = $record->getKey();
                            $valorTotal += (float) $record->valor_parcela;
                            $count++;
                        }

                        $lote = null;
                        if ($count > 0) {
                            $canal = ContaReceber::whereIn('id_conta_receber', $processedIds)->whereNotNull('forma_pagamento')->pluck('forma_pagamento')->countBy()->sortDesc()->keys()->first() ?? 'Geral';
                            $dataFormatada = \Carbon\Carbon::parse($data['data_recebimento'])->format('d/m/Y');
                            $descricao = !empty($data['descricao']) ? $data['descricao'] : "Repasse {$canal} {$dataFormatada}";
                            $lote = LoteRecebimento::create([
                                'data_recebimento' => $data['data_recebimento'],
                                'descricao' => $descricao,
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
                Tables\Actions\BulkAction::make('agrupar_lote')
                    ->label('Agrupar em Lote')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('conta_bancaria_id')
                            ->label('Banco')
                            ->options(fn () => \App\Models\ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->searchable()
                            ->placeholder('Selecione o banco'),
                        Forms\Components\TextInput::make('descricao')
                            ->label('Descrição do Lote')
                            ->placeholder('Ex: Repasse Madeira Madeira 11/05')
                            ->maxLength(255),
                    ])
                    ->modalHeading('Agrupar em Lote')
                    ->modalDescription('Agrupa os registros selecionados em um único lote no Caixa.')
                    ->modalSubmitActionLabel('Agrupar')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records, array $data) {
                        if ($records->isEmpty()) {
                            Notification::make()->title('Nenhum registro selecionado.')->warning()->send();
                            return;
                        }

                        $ids = $records->map(fn ($r) => $r->getKey())->toArray();
                        $valorTotal = (float) ContaReceber::whereIn('id_conta_receber', $ids)->sum('valor_parcela');
                        $dataRecebimento = $records->first()->data_recebimento ?? now()->toDateString();
                        $descricao = LoteRecebimento::gerarDescricao(
                            $data['conta_bancaria_id'] ? optional(\App\Models\ContaBancaria::find($data['conta_bancaria_id']))->nome : null,
                            $dataRecebimento,
                            $data['descricao'] ?? null,
                        );

                        $lote = LoteRecebimento::create([
                            'data_recebimento' => $dataRecebimento,
                            'descricao' => $descricao,
                            'valor_total' => round($valorTotal, 2),
                            'quantidade_contas' => $records->count(),
                        ]);

                        foreach ($records as $record) {
                            $record->update([
                                'lote_recebimento_id' => $lote->id,
                                'conta_bancaria_id' => $data['conta_bancaria_id'] ?? $record->conta_bancaria_id,
                            ]);
                        }

                        Notification::make()
                            ->title($records->count() . ' registro(s) agrupados no Lote #' . $lote->id . ' — R$ ' . number_format($valorTotal, 2, ',', '.'))
                            ->success()
                            ->send();
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
                Tables\Actions\BulkAction::make('marcar_ajuste_massa')
                    ->label('Marcar como Ajuste')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como Ajuste em Lote')
                    ->modalDescription('Os registros serão marcados como recebidos mas NÃO aparecerão no fluxo de caixa.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status !== 'pendente') continue;
                            $record->update([
                                'status' => 'recebido',
                                'data_recebimento' => $record->data_recebimento ?? now(),
                                'conta_bancaria_id' => null,
                            ]);
                            if ($record->id_venda) {
                                $pendentes = ContaReceber::where('id_venda', $record->id_venda)
                                    ->where('status', 'pendente')->count();
                                if ($pendentes === 0) {
                                    $record->venda?->update(['repasse_recebido' => true, 'data_recebimento' => $record->data_recebimento ?? now()]);
                                }
                            }
                            $count++;
                        }
                        Notification::make()->title("{$count} registro(s) marcado(s) como recebido (ajuste).")->success()->send();
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
