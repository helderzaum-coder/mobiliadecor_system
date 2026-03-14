<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoBlingStagingResource\Pages;
use App\Models\PedidoBlingStaging;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PedidoBlingStagingResource extends Resource
{
    protected static ?string $model = PedidoBlingStaging::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'Revisão de Pedidos';
    protected static ?string $modelLabel = 'Pedido (Revisão)';
    protected static ?string $pluralModelLabel = 'Pedidos (Revisão)';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Pedido')->schema([
                Forms\Components\TextInput::make('numero_pedido')
                    ->label('Nº Pedido Bling')
                    ->disabled(),
                Forms\Components\TextInput::make('numero_loja')
                    ->label('Nº Pedido Canal')
                    ->required(),
                Forms\Components\TextInput::make('canal')
                    ->label('Canal de Venda')
                    ->required(),
                Forms\Components\DatePicker::make('data_pedido')
                    ->label('Data do Pedido')
                    ->required(),
                Forms\Components\TextInput::make('cliente_nome')
                    ->label('Cliente')
                    ->disabled(),
                Forms\Components\TextInput::make('cliente_documento')
                    ->label('CPF/CNPJ')
                    ->disabled(),
            ])->columns(2),

            Forms\Components\Section::make('Valores (editáveis)')->schema([
                Forms\Components\TextInput::make('total_produtos')
                    ->label('Total Produtos')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                Forms\Components\TextInput::make('total_pedido')
                    ->label('Total Pedido')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                Forms\Components\TextInput::make('frete')
                    ->label('Frete Cliente')
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('custo_frete')
                    ->label('Custo Frete (Transportadora)')
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('nota_fiscal')
                    ->label('Nota Fiscal'),
            ])->columns(2),

            Forms\Components\Section::make('Observações')->schema([
                Forms\Components\Textarea::make('observacoes')
                    ->label('Observações')
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Comissão e Impostos (editáveis)')->schema([
                Forms\Components\TextInput::make('comissao_calculada')
                    ->label('Comissão')
                    ->numeric()
                    ->prefix('R$')
                    ->helperText('Pré-calculado pelas regras do canal. Edite se necessário.'),
                Forms\Components\TextInput::make('subsidio_pix')
                    ->label('Subsídio Pix')
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('base_imposto')
                    ->label('Base de Cálculo Imposto')
                    ->numeric()
                    ->prefix('R$'),
                Forms\Components\TextInput::make('percentual_imposto')
                    ->label('Imposto (%)')
                    ->numeric()
                    ->suffix('%'),
                Forms\Components\TextInput::make('valor_imposto')
                    ->label('Valor Imposto')
                    ->numeric()
                    ->prefix('R$'),
            ])->columns(3),

            Forms\Components\Section::make('Itens do Pedido')->schema([
                Forms\Components\Repeater::make('itens')
                    ->label('')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')->label('SKU')->disabled(),
                        Forms\Components\TextInput::make('descricao')->label('Descrição')->disabled(),
                        Forms\Components\TextInput::make('quantidade')->label('Qtd')->numeric(),
                        Forms\Components\TextInput::make('valor')->label('Valor')->numeric()->prefix('R$'),
                    ])
                    ->columns(4)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bling_account')->label('Conta')
                    ->formatStateUsing(fn (string $state) => $state === 'primary' ? 'Mobilia' : 'HES'),
                Tables\Columns\TextColumn::make('numero_pedido')->label('Pedido')->searchable(),
                Tables\Columns\TextColumn::make('numero_loja')->label('Pedido Canal')->searchable(),
                Tables\Columns\TextColumn::make('canal')->label('Canal'),
                Tables\Columns\TextColumn::make('cliente_nome')->label('Cliente')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('data_pedido')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('total_pedido')->label('Total')->money('BRL'),
                Tables\Columns\TextColumn::make('frete')->label('Frete')->money('BRL'),
                Tables\Columns\TextColumn::make('comissao_calculada')->label('Comissão')->money('BRL'),
                Tables\Columns\TextColumn::make('valor_imposto')->label('Imposto')->money('BRL'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pendente' => 'warning',
                        'aprovado' => 'success',
                        'rejeitado' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('data_pedido', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pendente' => 'Pendente',
                        'aprovado' => 'Aprovado',
                        'rejeitado' => 'Rejeitado',
                    ])
                    ->default('pendente'),
                Tables\Filters\SelectFilter::make('bling_account')
                    ->label('Conta')
                    ->options([
                        'primary' => 'Mobilia Decor',
                        'secondary' => 'HES Móveis',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Revisar'),
                Tables\Actions\Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (PedidoBlingStaging $record) => $record->update(['status' => 'aprovado']))
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (PedidoBlingStaging $record) => $record->update(['status' => 'rejeitado']))
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('aprovar_selecionados')
                    ->label('Aprovar Selecionados')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['status' => 'aprovado'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPedidosBlingStaging::route('/'),
            'edit' => Pages\EditPedidoBlingStaging::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
