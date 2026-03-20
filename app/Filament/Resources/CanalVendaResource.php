<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CanalVendaResource\Pages;
use App\Models\CanalVenda;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CanalVendaResource extends Resource
{
    protected static ?string $model = CanalVenda::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $modelLabel = 'Canal de Venda';
    protected static ?string $pluralModelLabel = 'Canais de Venda';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Canal')->schema([
                Forms\Components\TextInput::make('nome_canal')
                    ->label('Nome do Canal')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('tipo_nota')
                    ->label('Tipo de Nota Fiscal')
                    ->options([
                        'cheia' => 'Nota Cheia (total do pedido)',
                        'produto' => 'Somente Produto (sem frete)',
                        'meia_nota' => 'Meia Nota (metade do produto)',
                    ])
                    ->required()
                    ->default('cheia'),
                Forms\Components\Toggle::make('ativo')
                    ->label('Ativo')
                    ->default(true),
                Forms\Components\Toggle::make('comissao_sobre_frete')
                    ->label('Comissão sobre Frete')
                    ->helperText('Cobra comissão do canal sobre o valor do frete')
                    ->default(false),
            ])->columns(2),

            Forms\Components\Section::make('Regras de Comissão')->schema([
                Forms\Components\Repeater::make('regrasComissao')
                    ->relationship()
                    ->label('')
                    ->schema([
                        Forms\Components\TextInput::make('nome_regra')
                            ->label('Nome da Regra')
                            ->required()
                            ->maxLength(191),
                        Forms\Components\Select::make('ml_tipo_anuncio')
                            ->label('Tipo Anúncio ML')
                            ->options([
                                'Clássico' => 'Clássico',
                                'Premium' => 'Premium',
                            ])
                            ->placeholder('Todos')
                            ->helperText('Só para canal Mercadolivre'),
                        Forms\Components\Select::make('ml_tipo_frete')
                            ->label('Tipo Frete ML')
                            ->options([
                                'ME1' => 'ME1 (Coleta)',
                                'ME2' => 'ME2 (Drop-off)',
                                'FULL' => 'FULL (Fulfillment)',
                            ])
                            ->placeholder('Todos')
                            ->helperText('Só para canal Mercadolivre'),
                        Forms\Components\Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2),
                        Forms\Components\TextInput::make('percentual')
                            ->label('Comissão (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required(),
                        Forms\Components\TextInput::make('valor_fixo')
                            ->label('Valor Fixo')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0),
                        Forms\Components\TextInput::make('faixa_valor_min')
                            ->label('Valor Mínimo (faixa)')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('faixa_valor_max')
                            ->label('Valor Máximo (faixa)')
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('subsidio_pix')
                            ->label('Subsídio Pix (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default(0),
                        Forms\Components\Toggle::make('ativo')
                            ->label('Ativa')
                            ->default(true),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->addActionLabel('Adicionar Regra')
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['nome_regra'] ?? null),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_canal')->label('Canal')->searchable(),
                Tables\Columns\TextColumn::make('tipo_nota')->label('Tipo Nota')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cheia' => 'Nota Cheia',
                        'produto' => 'Só Produto',
                        'meia_nota' => 'Meia Nota',
                        default => $state,
                    })->badge(),
                Tables\Columns\TextColumn::make('regras_comissao_count')
                    ->label('Regras')
                    ->counts('regrasComissao'),
                Tables\Columns\IconColumn::make('ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('ativo'),
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
            'index' => Pages\ListCanaisVenda::route('/'),
            'create' => Pages\CreateCanalVenda::route('/create'),
            'edit' => Pages\EditCanalVenda::route('/{record}/edit'),
        ];
    }
}
