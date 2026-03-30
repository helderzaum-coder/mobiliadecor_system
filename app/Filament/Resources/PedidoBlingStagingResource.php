<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoBlingStagingResource\Pages;
use App\Models\PedidoBlingStaging;
use App\Jobs\SyncEstoquePedidoJob;
use App\Services\Bling\BlingClient;
use App\Services\Bling\BlingImportService;
use App\Services\AprovacaoVendaService;
use App\Services\CotacaoFreteService;
use App\Services\CotacaoWhatsappService;
use App\Services\CteService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
                Forms\Components\TextInput::make('nfe_chave_acesso')
                    ->label('Chave de Acesso NF-e')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('nfe_valor')
                    ->label('Valor NF-e')
                    ->numeric()
                    ->prefix('R$')
                    ->disabled(),
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

            Forms\Components\Section::make('Mercado Livre')->schema([
                Forms\Components\TextInput::make('ml_tipo_anuncio')
                    ->label('Tipo Anúncio')
                    ->disabled(),
                Forms\Components\TextInput::make('ml_tipo_frete')
                    ->label('Tipo Frete')
                    ->disabled(),
                Forms\Components\TextInput::make('ml_sale_fee')
                    ->label('Comissão ML (sale_fee)')
                    ->numeric()
                    ->prefix('R$')
                    ->disabled()
                    ->helperText('Comissão real cobrada pelo ML'),
                Forms\Components\TextInput::make('ml_frete_custo')
                    ->label('Frete ML Custo')
                    ->numeric()
                    ->prefix('R$')
                    ->disabled()
                    ->helperText('Tarifa de envio cobrada pelo ML (list_cost)'),
                Forms\Components\TextInput::make('ml_frete_receita')
                    ->label('Frete ML Receita')
                    ->numeric()
                    ->prefix('R$')
                    ->disabled()
                    ->helperText('Valor pago pelo comprador (cost)'),
                Forms\Components\Toggle::make('ml_tem_rebate')
                    ->label('Tem Rebate'),
                Forms\Components\TextInput::make('ml_valor_rebate')
                    ->label('Valor Rebate')
                    ->numeric()
                    ->prefix('R$')
                    ->helperText('Informe o valor do rebate/desconto do ML'),
            ])->columns(3)
            ->visible(fn ($record) => $record && self::isML($record)),

            Forms\Components\Section::make('Dados de Envio')->schema([
                Forms\Components\TextInput::make('dest_cep')
                    ->label('CEP Destino')
                    ->disabled(),
                Forms\Components\TextInput::make('dest_cidade')
                    ->label('Cidade')
                    ->disabled(),
                Forms\Components\TextInput::make('dest_uf')
                    ->label('UF')
                    ->disabled(),
                Forms\Components\TextInput::make('peso_bruto')
                    ->label('Peso Bruto (kg)')
                    ->numeric()
                    ->suffix('kg')
                    ->disabled(),
                Forms\Components\TextInput::make('embalagem_largura')
                    ->label('Largura (cm)')
                    ->numeric()
                    ->suffix('cm')
                    ->disabled(),
                Forms\Components\TextInput::make('embalagem_altura')
                    ->label('Altura (cm)')
                    ->numeric()
                    ->suffix('cm')
                    ->disabled(),
                Forms\Components\TextInput::make('embalagem_comprimento')
                    ->label('Comprimento (cm)')
                    ->numeric()
                    ->suffix('cm')
                    ->disabled(),
            ])->columns(4),

            Forms\Components\Section::make('Itens do Pedido')->schema([
                Forms\Components\Repeater::make('itens')
                    ->label('')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')->label('SKU')->disabled(),
                        Forms\Components\TextInput::make('descricao')->label('Descrição')->disabled(),
                        Forms\Components\TextInput::make('quantidade')->label('Qtd')->numeric(),
                        Forms\Components\TextInput::make('valor')->label('Valor')->numeric()->prefix('R$'),
                        Forms\Components\TextInput::make('custo')->label('Custo')->numeric()->prefix('R$'),
                    ])
                    ->columns(5)
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
                Tables\Columns\TextColumn::make('nota_fiscal')->label('NF')->searchable(),
                Tables\Columns\TextColumn::make('comissao_calculada')->label('Comissão')->money('BRL'),
                Tables\Columns\TextColumn::make('subsidio_pix')->label('Subsídio Pix')->money('BRL')
                    ->color('info')
                    ->default(0),
                Tables\Columns\TextColumn::make('valor_imposto')->label('Imposto')->money('BRL'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pendente' => 'warning',
                        'aprovado' => 'success',
                        'rejeitado' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('estoque_sincronizado')
                    ->label('Estoque')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\IconColumn::make('planilha_shopee')
                    ->label('Planilha')
                    ->boolean()
                    ->visible(fn () => true)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->getStateUsing(fn (PedidoBlingStaging $record) => self::isShopee($record) ? $record->planilha_shopee : null),
                Tables\Columns\TextColumn::make('pronto_aprovacao')
                    ->label('Pronto')
                    ->html()
                    ->getStateUsing(function (PedidoBlingStaging $record) {
                        $checks = self::verificarProntoAprovacao($record);
                        $pendentes = array_filter($checks, fn ($v) => !$v['ok']);

                        if (empty($pendentes)) {
                            return '<span title="Pronto para aprovar" class="text-success-500">✅</span>';
                        }

                        $faltam = implode('&#10;', array_map(fn ($v) => '❌ ' . $v['label'], $pendentes));
                        return '<span title="' . $faltam . '" class="text-danger-500 cursor-help">❌ ' . count($pendentes) . '</span>';
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
                Tables\Filters\SelectFilter::make('canal')
                    ->label('Canal')
                    ->options(fn () => \App\Models\PedidoBlingStaging::distinct()
                        ->orderBy('canal')
                        ->pluck('canal', 'canal')
                        ->toArray()
                    )
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('pronto')
                    ->label('Pronto p/ Aprovar')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('nfe_chave_acesso')->where('custo_frete', '>', 0),
                        false: fn ($query) => $query->where(fn ($q) => $q->whereNull('nfe_chave_acesso')->orWhere('custo_frete', '<=', 0)->orWhereNull('custo_frete')),
                    ),
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
                            'hoje' => $query->whereDate('data_pedido', today()),
                            'esta_semana' => $query->whereBetween('data_pedido', [
                                now()->startOfWeek(), now()->endOfWeek(),
                            ]),
                            'semana_passada' => $query->whereBetween('data_pedido', [
                                now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek(),
                            ]),
                            'este_mes' => $query->whereBetween('data_pedido', [
                                now()->startOfMonth(), now()->endOfMonth(),
                            ]),
                            'mes_passado' => $query->whereBetween('data_pedido', [
                                now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth(),
                            ]),
                            'selecionar_mes' => $data['mes_selecionado']
                                ? $query->whereBetween('data_pedido', [
                                    now()->createFromFormat('Y-m', $data['mes_selecionado'])->startOfMonth(),
                                    now()->createFromFormat('Y-m', $data['mes_selecionado'])->endOfMonth(),
                                ])
                                : $query,
                            'customizado' => $query
                                ->when($data['data_inicio'], fn ($q) => $q->whereDate('data_pedido', '>=', $data['data_inicio']))
                                ->when($data['data_fim'],    fn ($q) => $q->whereDate('data_pedido', '<=', $data['data_fim'])),
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
                Tables\Actions\EditAction::make()->label('Revisar'),
                Tables\Actions\Action::make('ver_estrutura')
                    ->label('Estrutura')
                    ->icon('heroicon-o-cube')
                    ->color('gray')
                    ->modalHeading('Estrutura dos Produtos')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(function (PedidoBlingStaging $record) {
                        $client = new BlingClient($record->bling_account);
                        $itens = $record->itens ?? [];

                        $html = '<table class="w-full text-sm border-collapse text-gray-700 dark:text-gray-200">';
                        $html .= '<thead><tr class="border-b border-gray-300 dark:border-gray-600">'
                            . '<th class="text-left p-2">SKU</th>'
                            . '<th class="text-left p-2">Descrição</th>'
                            . '<th class="text-center p-2">Tipo</th>'
                            . '<th class="text-center p-2">Qtd</th>'
                            . '</tr></thead><tbody>';

                        foreach ($itens as $item) {
                            $sku = $item['codigo'] ?? '';
                            $desc = $item['descricao'] ?? '';
                            $qtd = $item['quantidade'] ?? 1;
                            $formato = '—';
                            $componentes = [];

                            if ($sku) {
                                $produto = $client->getProductBySku($sku);
                                if ($produto) {
                                    $f = strtoupper($produto['formato'] ?? 'S');
                                    $formato = match ($f) {
                                        'S' => '<span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Simples</span>',
                                        'V' => '<span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Variação</span>',
                                        'E', 'C' => '<span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">Kit</span>',
                                        default => $f,
                                    };

                                    if (in_array($f, ['E', 'C'])) {
                                        $detalhe = $client->getProductById((int) $produto['id']);
                                        if ($detalhe) {
                                            foreach ($detalhe['estrutura']['componentes'] ?? [] as $comp) {
                                                $compProd = isset($comp['produto']['id'])
                                                    ? $client->getProductById((int) $comp['produto']['id'])
                                                    : null;
                                                $componentes[] = [
                                                    'sku' => $compProd['codigo'] ?? '—',
                                                    'descricao' => $comp['produto']['nome'] ?? $compProd['nome'] ?? '—',
                                                    'quantidade' => $comp['quantidade'] ?? 1,
                                                ];
                                            }
                                        }
                                    }
                                }
                            }

                            $html .= '<tr class="border-b border-gray-200 dark:border-gray-700">'
                                . '<td class="p-2 font-mono">' . e($sku) . '</td>'
                                . '<td class="p-2">' . e($desc) . '</td>'
                                . '<td class="p-2 text-center">' . $formato . '</td>'
                                . '<td class="p-2 text-center">' . $qtd . '</td>'
                                . '</tr>';

                            foreach ($componentes as $comp) {
                                $html .= '<tr class="border-b border-gray-200 dark:border-gray-700">'
                                    . '<td class="p-2 pl-6 font-mono text-xs text-gray-700 dark:text-gray-200">↳ ' . e($comp['sku']) . '</td>'
                                    . '<td class="p-2 text-xs text-gray-700 dark:text-gray-200">' . e($comp['descricao']) . '</td>'
                                    . '<td class="p-2 text-center"><span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-800 dark:bg-gray-600 dark:text-gray-100">Componente</span></td>'
                                    . '<td class="p-2 text-center text-xs text-gray-700 dark:text-gray-200">' . ($comp['quantidade'] * $qtd) . '</td>'
                                    . '</tr>';
                            }
                        }

                        $html .= '</tbody></table>';

                        return new HtmlString($html);
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('sincronizar_estoque')
                    ->label('Sync Estoque')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar Estoque')
                    ->modalDescription('A sincronização será processada em background. Você receberá uma notificação quando concluir.')
                    ->action(function (PedidoBlingStaging $record) {
                        SyncEstoquePedidoJob::dispatch($record->id);
                        Notification::make()
                            ->title('Sincronização enviada para processamento')
                            ->body("Pedido #{$record->numero_pedido} será sincronizado em background.")
                            ->info()->send();
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente' && !$record->estoque_sincronizado),
                Tables\Actions\Action::make('buscar_nfe')
                    ->label('Buscar NF-e')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Buscar NF-e no Bling')
                    ->modalDescription('Isso vai buscar a NF-e vinculada a este pedido na API do Bling. Pode demorar alguns segundos.')
                    ->action(function (PedidoBlingStaging $record) {
                        $found = BlingImportService::buscarNfePorPedido($record);
                        if ($found) {
                            Notification::make()->title('NF-e encontrada e vinculada.')->success()->send();
                        } else {
                            Notification::make()->title('NF-e não encontrada para este pedido.')->warning()->send();
                        }
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente' && empty($record->nfe_chave_acesso)),
                Tables\Actions\Action::make('buscar_dados_envio')
                    ->label('Buscar Envio')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Buscar Dados de Envio')
                    ->modalDescription('Vai buscar CEP, cidade, UF e dimensões do pedido no Bling.')
                    ->action(function (PedidoBlingStaging $record) {
                        $found = BlingImportService::buscarDadosEnvio($record);
                        if ($found) {
                            Notification::make()->title('Dados de envio atualizados.')->success()->send();
                        } else {
                            Notification::make()->title('Não foi possível obter dados de envio.')->warning()->send();
                        }
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente' && (empty($record->dest_cep) || empty($record->dest_uf))),
                Tables\Actions\Action::make('buscar_cte')
                    ->label('CT-e')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (PedidoBlingStaging $record) {
                        $result = CteService::processarCte($record);
                        if ($result['success']) {
                            Notification::make()->title($result['msg'])->success()->send();
                        } else {
                            Notification::make()->title($result['msg'])->warning()->send();
                        }
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente' && !empty($record->nfe_chave_acesso) && (float) $record->custo_frete == 0),
                Tables\Actions\Action::make('cotar_frete')
                    ->label('Cotar Frete')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->modalHeading('Cotação de Frete')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(function (PedidoBlingStaging $record) {
                        if (!$record->dest_uf || !$record->dest_cep || !$record->peso_bruto) {
                            return new HtmlString('<p class="text-sm text-danger-500">Dados de envio incompletos (UF, CEP ou peso). Reimporte o pedido.</p>');
                        }

                        $valorNf = (float) ($record->nfe_valor ?: $record->total_pedido);

                        // Buscar volumes via CotacaoWhatsappService
                        $waData = CotacaoWhatsappService::gerar($record);
                        $volumes = $waData['volumes'] ?: 1;

                        $cotacoes = CotacaoFreteService::cotar(
                            $record->dest_uf,
                            $record->dest_cep,
                            (float) $record->peso_bruto,
                            $valorNf,
                            $record->dest_cidade
                        );

                        if (empty($cotacoes)) {
                            return new HtmlString('<p class="text-sm text-warning-500">Nenhuma transportadora encontrada para este destino/peso.</p>');
                        }

                        $html = '<div class="text-xs text-gray-400 mb-3">'
                            . "Destino: {$record->dest_cidade}/{$record->dest_uf} - CEP {$record->dest_cep} | "
                            . "Peso: {$record->peso_bruto}kg | NF: R$ " . number_format($valorNf, 2, ',', '.')
                            . '</div>';

                        $html .= '<table class="w-full text-sm border-collapse text-gray-700 dark:text-gray-200">';
                        $html .= '<thead><tr class="border-b border-gray-300 dark:border-gray-600">'
                            . '<th class="text-left p-2">Transportadora</th>'
                            . '<th class="text-left p-2">Região</th>'
                            . '<th class="text-right p-2">Frete</th>'
                            . '<th class="text-right p-2">Despacho</th>'
                            . '<th class="text-right p-2">Pedágio</th>'
                            . '<th class="text-right p-2">ADV</th>'
                            . '<th class="text-right p-2">GRIS</th>'
                            . '<th class="text-right p-2">TAS</th>'
                            . '<th class="text-right p-2">Taxas</th>'
                            . '<th class="text-right p-2">ICMS</th>'
                            . '<th class="text-right p-2 font-bold">Total</th>'
                            . '</tr></thead><tbody>';

                        foreach ($cotacoes as $c) {
                            $isConsulta = !empty($c['somente_consulta']);
                            $taxasInfo = '';
                            foreach ($c['taxas_especiais'] as $t) {
                                $taxasInfo .= $t['tipo'] . ': R$ ' . number_format($t['valor'], 2, ',', '.') . ' ';
                            }

                            if ($isConsulta) {
                                $temTaxa = !empty($c['taxas_especiais']);
                                $taxaBadge = $temTaxa
                                    ? '<span class="text-xs bg-amber-100 dark:bg-amber-700 text-amber-800 dark:text-amber-100 px-1.5 py-0.5 rounded">TDA: R$ ' . number_format($c['taxas_especiais_total'], 2, ',', '.') . '</span>'
                                    : '<span class="text-xs bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-100 px-1.5 py-0.5 rounded">Sem TDA</span>';
                                $html .= '<tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">'
                                    . '<td class="p-2 font-medium">' . e($c['nome']) . ' <span class="text-xs text-blue-600 dark:text-blue-400">(consultar)</span></td>'
                                    . '<td class="p-2">' . e($c['uf_faixa']) . '</td>'
                                    . '<td colspan="7" class="p-2 text-center text-gray-500 dark:text-gray-400 text-xs">Atende a região — solicitar cotação direta ' . $taxaBadge . '</td>'
                                    . '<td class="text-right p-2">-</td>'
                                    . '<td class="text-right p-2 font-bold text-blue-600 dark:text-blue-400">Consultar</td>'
                                    . '</tr>';
                            } else {
                                $html .= '<tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">'
                                    . '<td class="p-2">' . e($c['nome']) . '</td>'
                                    . '<td class="p-2">' . e($c['regiao']) . ' / ' . e($c['uf_faixa']) . '</td>'
                                    . '<td class="text-right p-2">R$ ' . number_format($c['frete_peso'], 2, ',', '.') . '</td>'
                                    . '<td class="text-right p-2">R$ ' . number_format($c['despacho'], 2, ',', '.') . '</td>'
                                    . '<td class="text-right p-2">R$ ' . number_format($c['pedagio'], 2, ',', '.') . '</td>'
                                    . '<td class="text-right p-2">R$ ' . number_format($c['advalorem'], 2, ',', '.') . '</td>'
                                    . '<td class="text-right p-2">R$ ' . number_format($c['gris'], 2, ',', '.') . '</td>'
                                    . '<td class="text-right p-2">R$ ' . number_format($c['tas'] ?? 0, 2, ',', '.') . '</td>'
                                    . '<td class="text-right p-2" title="' . e($taxasInfo) . '">R$ ' . number_format($c['taxas_especiais_total'], 2, ',', '.') . '</td>'
                                    . '<td class="text-right p-2">' . (($c['icms_percentual'] ?? 0) > 0 ? 'R$ ' . number_format($c['icms_valor'], 2, ',', '.') . ' <span class="text-xs text-gray-500 dark:text-gray-400">(' . $c['icms_percentual'] . '%)</span>' : '-') . '</td>'
                                    . '<td class="text-right p-2 font-bold">R$ ' . number_format($c['total'], 2, ',', '.') . '</td>'
                                    . '</tr>';
                            }
                        }

                        $html .= '</tbody></table>';

                        // Gerar textos WhatsApp para cada cotação
                        $waTextos = [];
                        foreach ($cotacoes as $i => $c) {
                            $isConsulta = !empty($c['somente_consulta']);
                            $temTda = !empty($c['taxas_especiais']);
                            $tdaTexto = $temTda
                                ? 'TDA: R$ ' . number_format($c['taxas_especiais_total'], 2, ',', '.')
                                : 'Sem TDA';

                            if ($isConsulta) {
                                $waTextos[$i] = strtoupper($record->cliente_nome) . "\n"
                                    . $record->dest_cidade . '/' . $record->dest_uf . ' - CEP ' . preg_replace('/(\d{5})(\d{3})/', '$1-$2', $record->dest_cep) . "\n"
                                    . number_format((float)$record->peso_bruto, 2, ',', '.') . 'kg'
                                    . ' - ' . $volumes . ' vol'
                                    . ' - NF R$ ' . number_format($valorNf, 2, ',', '.') . "\n"
                                    . $c['nome'] . ': SOLICITAR COTAÇÃO'
                                    . ' (' . $tdaTexto . ')';
                            } else {
                                $icmsTexto = ($c['icms_percentual'] ?? 0) > 0
                                    ? ' + ICMS ' . $c['icms_percentual'] . '%'
                                    : '';
                                $waTextos[$i] = strtoupper($record->cliente_nome) . "\n"
                                    . $record->dest_cidade . '/' . $record->dest_uf . ' - CEP ' . preg_replace('/(\d{5})(\d{3})/', '$1-$2', $record->dest_cep) . "\n"
                                    . number_format((float)$record->peso_bruto, 2, ',', '.') . 'kg'
                                    . ' - ' . $volumes . ' vol'
                                    . ' - R$ ' . number_format($valorNf, 2, ',', '.') . "\n"
                                    . $c['nome'] . ': R$ ' . number_format($c['total'], 2, ',', '.')
                                    . ' (' . $tdaTexto . $icmsTexto . ')';
                            }
                        }

                        $html .= '<div class="mt-4 flex flex-wrap gap-2">';
                        foreach ($cotacoes as $i => $c) {
                            $isConsulta = !empty($c['somente_consulta']);
                            $btnStyle = $isConsulta
                                ? 'background:#2563eb;color:#fff;padding:4px 12px;font-size:12px;border-radius:6px;border:none;cursor:pointer;'
                                : 'background:#16a34a;color:#fff;padding:4px 12px;font-size:12px;border-radius:6px;border:none;cursor:pointer;';
                            $btnLabel = $isConsulta
                                ? '📋 ' . e($c['nome']) . ' (consultar)'
                                : '📋 ' . e($c['nome']);
                            $html .= '<button onclick="navigator.clipboard.writeText(this.dataset.texto).then(()=>{this.innerText=\'Copiado!\';setTimeout(()=>this.innerText=this.dataset.label,2000)})" '
                                . 'data-texto="' . str_replace('"', '&quot;', $waTextos[$i]) . '" '
                                . 'data-label="' . $btnLabel . '" '
                                . 'style="' . $btnStyle . '">'
                                . $btnLabel
                                . '</button>';
                        }
                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente' && $record->dest_uf && $record->dest_cep && $record->peso_bruto),
                Tables\Actions\Action::make('aplicar_cotacao')
                    ->label('Aplicar Frete')
                    ->icon('heroicon-o-check')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('id_transportadora')
                            ->label('Transportadora')
                            ->options(function (PedidoBlingStaging $record) {
                                if (!$record->dest_uf || !$record->dest_cep || !$record->peso_bruto) {
                                    return [];
                                }
                                $valorNf = (float) ($record->nfe_valor ?: $record->total_pedido);
                                $cotacoes = CotacaoFreteService::cotar(
                                    $record->dest_uf, $record->dest_cep,
                                    (float) $record->peso_bruto, $valorNf, $record->dest_cidade
                                );
                                $options = [];
                                foreach ($cotacoes as $c) {
                                    if (!empty($c['somente_consulta'])) continue;
                                    $options[$c['id_transportadora']] = $c['nome'] . ' - R$ ' . number_format($c['total'], 2, ',', '.') . ' (' . $c['uf_faixa'] . ' ' . $c['regiao'] . ')';
                                }
                                return $options;
                            })
                            ->required(),
                    ])
                    ->action(function (PedidoBlingStaging $record, array $data) {
                        $valorNf = (float) ($record->nfe_valor ?: $record->total_pedido);
                        $cotacoes = CotacaoFreteService::cotar(
                            $record->dest_uf, $record->dest_cep,
                            (float) $record->peso_bruto, $valorNf, $record->dest_cidade
                        );
                        $selecionada = collect($cotacoes)->firstWhere('id_transportadora', (int) $data['id_transportadora']);
                        if ($selecionada) {
                            $record->update(['custo_frete' => $selecionada['total']]);
                            Notification::make()
                                ->title("Frete aplicado: {$selecionada['nome']} - R$ " . number_format($selecionada['total'], 2, ',', '.'))
                                ->success()->send();
                        }
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente' && $record->dest_uf && $record->dest_cep && $record->peso_bruto),
                Tables\Actions\Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (PedidoBlingStaging $record) {
                        if (self::isShopee($record) && !$record->planilha_shopee) {
                            Notification::make()->title('Processe a planilha Shopee antes de aprovar.')->danger()->send();
                            return;
                        }
                        AprovacaoVendaService::aprovar($record);
                        Notification::make()->title('Pedido aprovado e venda criada.')->success()->send();
                    })
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente'),
                Tables\Actions\Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (PedidoBlingStaging $record) => $record->update(['status' => 'rejeitado']))
                    ->visible(fn (PedidoBlingStaging $record) => $record->status === 'pendente'),

                Tables\Actions\Action::make('cotacao_whatsapp')
                    ->label('Cotação WA')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->modalHeading('Cotação para WhatsApp')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(function (PedidoBlingStaging $record) {
                        $resultado = CotacaoWhatsappService::gerar($record);

                        if ($resultado['erro']) {
                            return new HtmlString('<p class="text-sm text-danger-500">' . e($resultado['erro']) . '</p>');
                        }

                        $tipoBadge = match($resultado['tipo_produto']) {
                            'kit'    => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Kit</span>',
                            'misto'  => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Misto</span>',
                            default  => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Simples</span>',
                        };

                        // Detalhes dos itens
                        $itensHtml = '<table class="w-full text-xs mt-3 mb-4 border-collapse">';
                        $itensHtml .= '<thead><tr class="border-b border-gray-600 text-gray-400">'
                            . '<th class="text-left p-1">SKU</th>'
                            . '<th class="text-left p-1">Descrição</th>'
                            . '<th class="text-center p-1">Tipo</th>'
                            . '<th class="text-center p-1">Qtd</th>'
                            . '<th class="text-center p-1">Vol/un</th>'
                            . '<th class="text-center p-1">Total Vol</th>'
                            . '</tr></thead><tbody>';
                        foreach ($resultado['itens_detalhes'] as $item) {
                            $aviso = isset($item['aviso']) ? ' <span class="text-warning-400">⚠ ' . e($item['aviso']) . '</span>' : '';
                            $itensHtml .= '<tr class="border-b border-gray-700">'
                                . '<td class="p-1 font-mono">' . e($item['sku']) . '</td>'
                                . '<td class="p-1">' . e($item['descricao']) . $aviso . '</td>'
                                . '<td class="p-1 text-center capitalize">' . e($item['tipo']) . '</td>'
                                . '<td class="p-1 text-center">' . $item['quantidade'] . '</td>'
                                . '<td class="p-1 text-center">' . $item['volumes_unitario'] . '</td>'
                                . '<td class="p-1 text-center font-medium">' . ($item['volumes_unitario'] * $item['quantidade']) . '</td>'
                                . '</tr>';
                        }
                        $itensHtml .= '</tbody></table>';

                        $texto = e($resultado['texto']);
                        $html  = '<div class="flex items-center gap-2 mb-3">';
                        $html .= '<span class="text-sm text-gray-400">Tipo:</span> ' . $tipoBadge;
                        $html .= '<span class="text-sm text-gray-400 ml-4">Volumes totais:</span> <span class="font-bold text-white">' . $resultado['volumes'] . '</span>';
                        $html .= '</div>';
                        $html .= $itensHtml;
                        $html .= '<div class="relative">';
                        $html .= '<pre id="cotacao-texto" class="bg-gray-900 border border-gray-600 rounded-lg p-4 text-sm text-white whitespace-pre-wrap font-mono leading-relaxed">' . $texto . '</pre>';
                        $html .= '<button onclick="navigator.clipboard.writeText(document.getElementById(\'cotacao-texto\').innerText).then(()=>{this.innerText=\'Copiado!\';setTimeout(()=>this.innerText=\'Copiar\',2000)})" '
                            . 'class="absolute top-2 right-2 px-3 py-1 text-xs bg-primary-600 hover:bg-primary-500 text-white rounded-md transition">Copiar</button>';
                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('sincronizar_estoque_massa')
                    ->label('Sync Estoque')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sincronizar Estoque em Massa')
                    ->modalDescription('Os pedidos selecionados serão sincronizados em background. Você receberá notificações ao concluir.')
                    ->action(function ($records) {
                        $enviados = 0;
                        foreach ($records as $record) {
                            if ($record->estoque_sincronizado) continue;
                            SyncEstoquePedidoJob::dispatch($record->id);
                            $enviados++;
                        }
                        if ($enviados > 0) {
                            Notification::make()->title("{$enviados} pedido(s) enviados para sincronização.")->info()->send();
                        } else {
                            Notification::make()->title('Nenhum pedido para sincronizar.')->info()->send();
                        }
                    }),
                Tables\Actions\BulkAction::make('aprovar_selecionados')
                    ->label('Aprovar Selecionados')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $bloqueados = 0;
                        $aprovados = 0;
                        foreach ($records as $record) {
                            if (self::isShopee($record) && !$record->planilha_shopee) {
                                $bloqueados++;
                                continue;
                            }
                            AprovacaoVendaService::aprovar($record);
                            $aprovados++;
                        }
                        if ($aprovados > 0) {
                            Notification::make()->title("{$aprovados} pedido(s) aprovados e vendas criadas.")->success()->send();
                        }
                        if ($bloqueados > 0) {
                            Notification::make()->title("{$bloqueados} pedido(s) Shopee aguardando planilha.")->warning()->send();
                        }
                    }),
                Tables\Actions\BulkAction::make('rejeitar_selecionados')
                    ->label('Rejeitar Selecionados')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $rejeitados = 0;
                        foreach ($records as $record) {
                            if ($record->status === 'pendente') {
                                $record->update(['status' => 'rejeitado']);
                                $rejeitados++;
                            }
                        }
                        if ($rejeitados > 0) {
                            Notification::make()->title("{$rejeitados} pedido(s) rejeitados.")->success()->send();
                        }
                    }),
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
        return auth()->user()?->hasAnyRole(['admin', 'operador']) ?? false;
    }

    public static function isShopee(PedidoBlingStaging $record): bool
    {
        return stripos($record->canal ?? '', 'shopee') !== false;
    }

    public static function isML(PedidoBlingStaging $record): bool
    {
        return str_contains(strtolower($record->canal ?? ''), 'mercado')
            || str_starts_with($record->numero_loja ?? '', '2000');
    }

    /**
     * Verifica se um pedido está pronto para aprovação.
     * Retorna array de checks com 'ok' e 'label'.
     *
     * ⚠️ NÃO ALTERAR: Regras de aprovação por canal:
     *  - NF-e obrigatória para todos
     *  - Custo frete obrigatório EXCETO: ML (qualquer tipo) e Shopee Xpress (frete=0)
     *  - ML: requer planilha ML processada (rebate)
     *  - Shopee: requer planilha Shopee processada
     */
    public static function verificarProntoAprovacao(PedidoBlingStaging $record): array
    {
        $checks = [];
        $isML = self::isML($record);
        $isShopee = self::isShopee($record);

        // NF-e — obrigatório para todos
        $checks[] = [
            'ok' => !empty($record->nfe_chave_acesso),
            'label' => 'NF-e',
        ];

        // Custo frete — obrigatório exceto ML e Shopee Xpress (frete = 0 após planilha)
        if (!$isML) {
            $shopeeXpress = $isShopee && $record->planilha_shopee && (float) ($record->frete ?? 0) == 0;
            if (!$shopeeXpress) {
                $checks[] = [
                    'ok' => (float) ($record->custo_frete ?? 0) > 0,
                    'label' => 'Custo Frete',
                ];
            }
        }

        // ML: rebate processado (planilha ML importada)
        if ($isML) {
            $checks[] = [
                'ok' => $record->ml_tem_rebate !== null,
                'label' => 'Planilha ML (Rebate)',
            ];
        }

        // Shopee: planilha processada
        if ($isShopee) {
            $checks[] = [
                'ok' => (bool) $record->planilha_shopee,
                'label' => 'Planilha Shopee',
            ];
        }

        return $checks;
    }

    public static function isProntoAprovacao(PedidoBlingStaging $record): bool
    {
        $checks = self::verificarProntoAprovacao($record);
        return empty(array_filter($checks, fn ($v) => !$v['ok']));
    }
}
