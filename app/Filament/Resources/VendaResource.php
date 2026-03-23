<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendaResource\Pages;
use App\Models\CanalVenda;
use App\Models\Venda;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendaResource extends Resource
{
    protected static ?string $model = Venda::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Vendas';
    protected static ?string $modelLabel = 'Venda';
    protected static ?string $pluralModelLabel = 'Vendas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Resumo Rápido')
                ->schema([
                    Forms\Components\Placeholder::make('resumo_pedido')
                        ->label('Nº Pedido Canal')
                        ->content(fn ($record) => $record?->numero_pedido_canal ?? '-'),
                    Forms\Components\Placeholder::make('resumo_canal')
                        ->label('Canal')
                        ->content(fn ($record) => $record?->canal?->nome_canal ?? '-'),
                    Forms\Components\Placeholder::make('resumo_total')
                        ->label('Total Pedido')
                        ->content(fn ($record) => $record ? 'R$ ' . number_format((float) $record->valor_total_venda, 2, ',', '.') : '-'),
                    Forms\Components\Placeholder::make('resumo_repasse')
                        ->label('Repasse Estimado')
                        ->content(fn ($record) => $record ? 'R$ ' . number_format(
                            round((float) $record->valor_total_venda - (float) $record->comissao - (float) $record->subsidio_pix, 2),
                            2, ',', '.'
                        ) : '-'),
                    Forms\Components\Placeholder::make('resumo_lucro')
                        ->label('Lucro Final')
                        ->content(fn ($record) => $record ? 'R$ ' . number_format((float) $record->margem_venda_total, 2, ',', '.') . ' (' . $record->margem_contribuicao . '%)' : '-'),
                ])
                ->columns(5)
                ->visible(fn ($record) => $record !== null),

            Forms\Components\Section::make('Dados da Venda')->schema([
                Forms\Components\TextInput::make('numero_pedido_canal')
                    ->label('Nº Pedido Canal')->required()->maxLength(50),
                Forms\Components\TextInput::make('numero_nota_fiscal')
                    ->label('NF')->maxLength(50),
                Forms\Components\Select::make('id_canal')
                    ->label('Canal')->relationship('canal', 'nome_canal')
                    ->required()->searchable()->preload(),
                Forms\Components\Select::make('id_cnpj')
                    ->label('CNPJ')->relationship('cnpj', 'razao_social')
                    ->required()->searchable()->preload(),
                Forms\Components\DatePicker::make('data_venda')
                    ->label('Data')->required(),
                Forms\Components\TextInput::make('cliente_nome')
                    ->label('Cliente')->disabled(),
            ])->columns(3),

            Forms\Components\Section::make('Valores')->schema([
                Forms\Components\TextInput::make('total_produtos')
                    ->label('Subtotal Produtos')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('custo_produtos')
                    ->label('Custo Produtos')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('valor_total_venda')
                    ->label('Total Pedido')->numeric()->prefix('R$')->required(),
                Forms\Components\TextInput::make('valor_frete_cliente')
                    ->label('Frete')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('valor_frete_transportadora')
                    ->label('Frete Pago (Transp.)')->numeric()->prefix('R$'),
                Forms\Components\Toggle::make('frete_pago')->label('Frete Pago'),
            ])->columns(3),

            Forms\Components\Section::make('Comissão e Impostos')->schema([
                Forms\Components\TextInput::make('comissao')
                    ->label('Comissão')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('subsidio_pix')
                    ->label('Subsídio Pix')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('percentual_imposto')
                    ->label('Imposto (%)')->numeric()->suffix('%'),
                Forms\Components\TextInput::make('valor_imposto')
                    ->label('Valor Imposto')->numeric()->prefix('R$'),
            ])->columns(4),

            Forms\Components\Section::make('Mercado Livre')
                ->schema([
                    Forms\Components\TextInput::make('ml_tipo_anuncio')
                        ->label('Tipo Anúncio'),
                    Forms\Components\TextInput::make('ml_tipo_frete')
                        ->label('Tipo Frete (ME1/ME2)'),
                    Forms\Components\TextInput::make('ml_sale_fee')
                        ->label('Sale Fee (Comissão ML)')->numeric()->prefix('R$'),
                    Forms\Components\TextInput::make('ml_valor_rebate')
                        ->label('Rebate')->numeric()->prefix('R$'),
                    Forms\Components\TextInput::make('ml_frete_custo')
                        ->label('Frete ML Custo')->numeric()->prefix('R$'),
                    Forms\Components\TextInput::make('ml_frete_receita')
                        ->label('Frete ML Receita')->numeric()->prefix('R$'),
                ])->columns(3)
                ->visible(fn ($record) => $record && self::isMLVenda($record)),

            Forms\Components\Section::make('Margens')->schema([
                Forms\Components\TextInput::make('margem_frete')
                    ->label('Margem Frete')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('margem_produto')
                    ->label('Margem Produto')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('margem_venda_total')
                    ->label('Lucro Final')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('margem_contribuicao')
                    ->label('Lucro Final (%)')->numeric()->suffix('%'),
            ])->columns(4),
        ]);
    }

    public static function isMLVenda(Venda $venda): bool
    {
        $canal = $venda->canal?->nome_canal ?? '';
        return str_contains(strtolower($canal), 'mercado')
            || str_starts_with($venda->numero_pedido_canal ?? '', '2000');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero_pedido_canal')
                    ->label('Pedido')->searchable()->copyable()->copyMessage('Copiado!'),
                Tables\Columns\TextColumn::make('numero_nota_fiscal')
                    ->label('NF')->searchable()->copyable()->copyMessage('Copiado!'),
                Tables\Columns\TextColumn::make('canal.nome_canal')
                    ->label('Canal'),
                Tables\Columns\TextColumn::make('ml_tipo_anuncio')
                    ->label('Anúncio')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Premium' => 'warning',
                        'Clássico' => 'info',
                        default => 'gray',
                    })
                    ->visible(fn () => true),
                Tables\Columns\TextColumn::make('ml_tipo_frete')
                    ->label('Frete ML')
                    ->badge()
                    ->visible(fn () => true),
                Tables\Columns\TextColumn::make('cnpj.razao_social')
                    ->label('CNPJ')->limit(20),
                Tables\Columns\TextColumn::make('data_venda')
                    ->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('total_produtos')
                    ->label('Subtotal')->money('BRL'),
                Tables\Columns\TextColumn::make('custo_produtos')
                    ->label('Custo Prod.')->money('BRL'),
                Tables\Columns\TextColumn::make('comissao')
                    ->label('Comissão')->money('BRL'),
                Tables\Columns\TextColumn::make('subsidio_pix')
                    ->label('Subsídio Pix')->money('BRL')
                    ->color('info')
                    ->default(0),
                Tables\Columns\TextColumn::make('valor_imposto')
                    ->label('Imposto')->money('BRL'),
                Tables\Columns\TextColumn::make('ml_valor_rebate')
                    ->label('Rebate')->money('BRL')
                    ->visible(fn () => true)
                    ->color('info')
                    ->default(0),
                Tables\Columns\TextColumn::make('margem_produto')
                    ->label('Margem Produto')->money('BRL')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('margem_produto_pct')
                    ->label('Margem Prod. %')
                    ->getStateUsing(fn (Venda $r) => $r->total_produtos > 0
                        ? round(($r->margem_produto / $r->total_produtos) * 100, 1) . '%'
                        : '-'),
                Tables\Columns\TextColumn::make('valor_frete_cliente')
                    ->label('Frete')->money('BRL'),
                Tables\Columns\TextColumn::make('valor_frete_transportadora')
                    ->label('Frete Pago')->money('BRL'),
                Tables\Columns\TextColumn::make('margem_frete')
                    ->label('Margem Frete')->money('BRL')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('margem_frete_pct')
                    ->label('Margem Frete %')
                    ->getStateUsing(fn (Venda $r) => $r->valor_frete_cliente > 0
                        ? round(($r->margem_frete / $r->valor_frete_cliente) * 100, 1) . '%'
                        : '-'),
                Tables\Columns\TextColumn::make('valor_total_venda')
                    ->label('Total')->money('BRL'),
                Tables\Columns\TextColumn::make('repasse_estimado')
                    ->label('Repasse Est.')
                    ->money('BRL')
                    ->getStateUsing(fn (Venda $r) => round(
                        (float) $r->valor_total_venda - (float) $r->comissao - (float) $r->subsidio_pix,
                        2
                    ))
                    ->color('info'),
                Tables\Columns\TextColumn::make('margem_venda_total')
                    ->label('Lucro Final')->money('BRL')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('margem_contribuicao')
                    ->label('Lucro %')->suffix('%')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('data_venda', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('id_canal')
                    ->label('Canal')
                    ->relationship('canal', 'nome_canal')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('id_cnpj')
                    ->label('CNPJ')
                    ->relationship('cnpj', 'razao_social'),
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        Forms\Components\Select::make('periodo_rapido')
                            ->label('Período')
                            ->options([
                                'hoje'             => 'Hoje',
                                'esta_semana'      => 'Esta semana',
                                'semana_passada'   => 'Semana passada',
                                'este_mes'         => 'Este mês',
                                'mes_passado'      => 'Mês passado',
                                'selecionar_mes'   => 'Selecionar mês',
                                'customizado'      => 'Período customizado',
                            ])
                            ->reactive()
                            ->placeholder('Selecione um período'),
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
                            ->displayFormat('d/m/Y')
                            ->visible(fn ($get) => $get('periodo_rapido') === 'customizado'),
                        Forms\Components\DatePicker::make('data_fim')
                            ->label('Até')
                            ->displayFormat('d/m/Y')
                            ->visible(fn ($get) => $get('periodo_rapido') === 'customizado'),
                    ])
                    ->query(function ($query, array $data) {
                        $periodo = $data['periodo_rapido'] ?? null;
                        if (!$periodo) return $query;

                        return match ($periodo) {
                            'hoje' => $query->whereDate('data_venda', today()),
                            'esta_semana' => $query->whereBetween('data_venda', [
                                now()->startOfWeek(), now()->endOfWeek(),
                            ]),
                            'semana_passada' => $query->whereBetween('data_venda', [
                                now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek(),
                            ]),
                            'este_mes' => $query->whereBetween('data_venda', [
                                now()->startOfMonth(), now()->endOfMonth(),
                            ]),
                            'mes_passado' => $query->whereBetween('data_venda', [
                                now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(),
                            ]),
                            'selecionar_mes' => $data['mes_selecionado']
                                ? $query->whereBetween('data_venda', [
                                    now()->createFromFormat('Y-m', $data['mes_selecionado'])->startOfMonth(),
                                    now()->createFromFormat('Y-m', $data['mes_selecionado'])->endOfMonth(),
                                ])
                                : $query,
                            'customizado' => $query
                                ->when($data['data_inicio'], fn ($q) => $q->whereDate('data_venda', '>=', $data['data_inicio']))
                                ->when($data['data_fim'],    fn ($q) => $q->whereDate('data_venda', '<=', $data['data_fim'])),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data) {
                        $periodo = $data['periodo_rapido'] ?? null;
                        if (!$periodo) return null;
                        return match ($periodo) {
                            'hoje'           => 'Hoje',
                            'esta_semana'    => 'Esta semana',
                            'semana_passada' => 'Semana passada',
                            'este_mes'       => 'Este mês',
                            'mes_passado'    => 'Mês passado',
                            'selecionar_mes' => $data['mes_selecionado'] ? 'Mês: ' . $data['mes_selecionado'] : 'Mês selecionado',
                            'customizado'    => trim(($data['data_inicio'] ?? '') . ' → ' . ($data['data_fim'] ?? '')),
                            default          => null,
                        };
                    }),
            ])
            ->filtersFormColumns(3)
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
            'index' => Pages\ListVendas::route('/'),
            'create' => Pages\CreateVenda::route('/create'),
            'edit' => Pages\EditVenda::route('/{record}/edit'),
        ];
    }
}
