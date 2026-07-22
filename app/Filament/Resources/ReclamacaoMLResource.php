<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReclamacaoMLResource\Pages;
use App\Models\CategoriaFinanceira;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ReclamacaoML;
use App\Models\Venda;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReclamacaoMLResource extends Resource
{
    protected static ?string $model = ReclamacaoML::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Mercado Livre';
    protected static ?string $modelLabel = 'Reclamação ML';
    protected static ?string $pluralModelLabel = 'Reclamações ML';
    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Pedido')->schema([
                Forms\Components\Select::make('id_venda')
                    ->label('Venda')
                    ->getSearchResultsUsing(fn (string $search) => strlen($search) < 2 ? [] : Venda::where('numero_pedido_canal', 'like', "%{$search}%")
                        ->orWhere('cliente_nome', 'like', "%{$search}%")
                        ->orderByDesc('data_venda')
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn ($v) => [$v->id_venda => "{$v->numero_pedido_canal} — {$v->cliente_nome}"])
                        ->toArray())
                    ->getOptionLabelUsing(fn ($value) => optional(Venda::find($value))->numero_pedido_canal)
                    ->searchable()
                    ->noSearchResultsMessage('Nenhuma venda encontrada.')
                    ->searchPrompt('Digite o nº do pedido ou nome do cliente...')
                    ->placeholder('Digite para buscar...')
                    ->reactive()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if (!$state) return;
                        $venda = Venda::find($state);
                        if ($venda) {
                            $set('numero_pedido', $venda->numero_pedido_canal);
                            $set('valor', $venda->valor_total_venda);
                        }
                    }),
                Forms\Components\TextInput::make('numero_pedido')
                    ->label('Nº Pedido (manual)')
                    ->helperText('Preencha se não encontrar a venda acima')
                    ->maxLength(100),
            ])->columns(2),

            Forms\Components\Section::make('Detalhes')->schema([
                Forms\Components\TextInput::make('valor')
                    ->label('Valor Bloqueado')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                Forms\Components\DatePicker::make('data_abertura')
                    ->label('Data de Abertura')
                    ->default(now())
                    ->required(),
                Forms\Components\Select::make('conta_bancaria_id')
                    ->label('Conta Mercado Pago')
                    ->options(fn () => ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('motivo')
                    ->label('Motivo')
                    ->placeholder('Ex: Produto não recebido, Item diferente...')
                    ->maxLength(255),
                Forms\Components\Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                Tables\Columns\TextColumn::make('data_abertura')
                    ->label('Abertura')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('numero_pedido')
                    ->label('Pedido')
                    ->getStateUsing(fn (ReclamacaoML $r) => $r->numero_pedido ?? $r->venda?->numero_pedido_canal)
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('venda.cliente_nome')
                    ->label('Cliente')
                    ->placeholder('-')
                    ->limit(25),
                Tables\Columns\TextColumn::make('motivo')
                    ->label('Motivo')
                    ->placeholder('-')
                    ->limit(30),
                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor Bloqueado')
                    ->money('BRL')
                    ->sortable()
                    ->color(fn (ReclamacaoML $r) => $r->status === 'aberta' ? 'warning' : null)
                    ->summarize(
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('BRL')
                            ->label('Total')
                    ),
                Tables\Columns\TextColumn::make('contaBancaria.nome')
                    ->label('Conta')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'aberta'    => 'warning',
                        'liberada'  => 'success',
                        'estornada' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'aberta'    => '🔒 Bloqueado',
                        'liberada'  => '✅ Liberado',
                        'estornada' => '❌ Estornado',
                    }),
                Tables\Columns\TextColumn::make('data_resolucao')
                    ->label('Resolução')
                    ->date('d/m/Y')
                    ->placeholder('-'),
            ])
            ->defaultSort('data_abertura', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'aberta'    => '🔒 Bloqueado',
                        'liberada'  => '✅ Liberado',
                        'estornada' => '❌ Estornado',
                    ])
                    ->default('aberta'),
                Tables\Filters\SelectFilter::make('conta_bancaria_id')
                    ->label('Conta')
                    ->relationship('contaBancaria', 'nome'),
            ])
            ->actions([
                Tables\Actions\Action::make('liberar')
                    ->label('Liberar')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('data_resolucao')
                            ->label('Data de Liberação')
                            ->helperText('Data em que o valor voltou a ficar disponível na conta')
                            ->default(now())
                            ->required(),
                    ])
                    ->modalHeading('Confirmar Liberação')
                    ->modalDescription('O ML liberou o valor? Isso indica que a reclamação foi resolvida a seu favor.')
                    ->action(function (ReclamacaoML $record, array $data) {
                        $record->update([
                            'status'         => 'liberada',
                            'data_resolucao' => $data['data_resolucao'],
                        ]);
                        Notification::make()->title('Reclamação liberada. Valor disponível novamente.')->success()->send();
                    })
                    ->visible(fn (ReclamacaoML $r) => $r->status === 'aberta'),

                Tables\Actions\Action::make('estornar')
                    ->label('Estornar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->form([
                        Forms\Components\DatePicker::make('data_estorno')
                            ->label('Data do Estorno')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('categoria_id')
                            ->label('Categoria')
                            ->options(fn () => CategoriaFinanceira::whereIn('tipo', ['saida', 'ambos'])
                                ->where('ativo', true)->where('sistema', false)
                                ->orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->searchable()
                            ->required()
                            ->placeholder('Selecione a categoria'),
                        Forms\Components\Textarea::make('observacoes')
                            ->label('Observações')
                            ->placeholder('Ex: Cliente não recebeu o produto')
                            ->rows(2),
                    ])
                    ->modalHeading('Registrar Estorno')
                    ->modalDescription('O ML depositou o valor para o comprador. Isso criará uma saída no Contas a Pagar.')
                    ->action(function (ReclamacaoML $record, array $data) {
                        $numeroPedido = $record->numero_pedido ?? $record->venda?->numero_pedido_canal ?? "Reclamação #{$record->id}";

                        $contaPagar = ContaPagar::create([
                            'descricao'         => "Estorno ML — Pedido {$numeroPedido}",
                            'valor_parcela'     => $record->valor,
                            'data_lancamento'   => $data['data_estorno'],
                            'data_vencimento'   => $data['data_estorno'],
                            'data_pagamento'    => $data['data_estorno'],
                            'status'            => 'pago',
                            'forma_pagamento'   => 'debito_automatico',
                            'conta_bancaria_id' => $record->conta_bancaria_id,
                            'categoria_id'      => $data['categoria_id'],
                            'observacoes'       => $data['observacoes'] ?? null,
                            'numero_parcela'    => 1,
                            'total_parcelas'    => 1,
                            'lancamento_manual' => true,
                        ]);

                        $record->update([
                            'status'         => 'estornada',
                            'data_resolucao' => $data['data_estorno'],
                            'conta_pagar_id' => $contaPagar->id_conta_pagar,
                            'observacoes'    => $data['observacoes'] ?? $record->observacoes,
                        ]);

                        Notification::make()
                            ->title("Estorno de R$ " . number_format((float) $record->valor, 2, ',', '.') . " registrado no Contas a Pagar.")
                            ->danger()
                            ->send();
                    })
                    ->visible(fn (ReclamacaoML $r) => $r->status === 'aberta'),

                Tables\Actions\Action::make('ver_estorno')
                    ->label('Ver Saída')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (ReclamacaoML $r) => $r->conta_pagar_id
                        ? "/contas-pagar/{$r->conta_pagar_id}/edit"
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn (ReclamacaoML $r) => $r->status === 'estornada' && $r->conta_pagar_id),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListReclamacoesML::route('/'),
            'create' => Pages\CreateReclamacaoML::route('/create'),
            'edit'   => Pages\EditReclamacaoML::route('/{record}/edit'),
        ];
    }
}
