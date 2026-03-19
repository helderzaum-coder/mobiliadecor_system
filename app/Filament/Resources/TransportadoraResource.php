<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransportadoraResource\Pages;
use App\Models\Transportadora;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransportadoraResource extends Resource
{
    protected static ?string $model = Transportadora::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?string $modelLabel = 'Transportadora';
    protected static ?string $pluralModelLabel = 'Transportadoras';

    private static array $ufs = [
        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
        'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
        'RS','RO','RR','SC','SP','SE','TO',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados da Transportadora')->schema([
                Forms\Components\TextInput::make('nome_transportadora')
                    ->label('Nome')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('cnpj')
                    ->label('CNPJ')
                    ->required()
                    ->maxLength(18)
                    ->mask('99.999.999/9999-99'),
                Forms\Components\Toggle::make('ativo')
                    ->label('Ativo')
                    ->default(true),
                Forms\Components\Toggle::make('aplica_icms')
                    ->label('Aplica ICMS')
                    ->default(false)
                    ->helperText('Calcula ICMS por dentro sobre o total do frete'),
                Forms\Components\Toggle::make('cobertura_completa')
                    ->label('Cobertura Completa')
                    ->default(false)
                    ->helperText('Marque se a tabela de frete já cobre todos os destinos. Desmarcado = mostra "consultar" quando não encontrar faixa.'),
            ])->columns(4),

            Forms\Components\Section::make('UFs Atendidas')->schema([
                Forms\Components\CheckboxList::make('ufs_selecionadas')
                    ->label('')
                    ->options(array_combine(self::$ufs, self::$ufs))
                    ->columns(9)
                    ->afterStateHydrated(function ($component, $record) {
                        if ($record) {
                            $component->state(
                                $record->ufsAtendidas->pluck('uf')->toArray()
                            );
                        }
                    }),
            ]),

            Forms\Components\Section::make('Generalidades')->schema([
                Forms\Components\TextInput::make('taxa_despacho')
                    ->label('Despacho')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0),
                Forms\Components\TextInput::make('pedagio_valor')
                    ->label('Pedágio (valor)')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0),
                Forms\Components\TextInput::make('pedagio_fracao_kg')
                    ->label('Pedágio a cada (kg)')
                    ->numeric()
                    ->suffix('kg')
                    ->default(100)
                    ->helperText('Ex: R$ 4,50 a cada 100kg'),
                Forms\Components\TextInput::make('adv_percentual')
                    ->label('Ad Valorem (%)')
                    ->numeric()
                    ->suffix('%')
                    ->default(0)
                    ->helperText('Sobre valor da NF'),
                Forms\Components\TextInput::make('adv_minimo')
                    ->label('Ad Valorem Mínimo')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0),
                Forms\Components\TextInput::make('gris_percentual')
                    ->label('GRIS (%)')
                    ->numeric()
                    ->suffix('%')
                    ->default(0)
                    ->helperText('Sobre valor da NF'),
                Forms\Components\TextInput::make('gris_minimo')
                    ->label('GRIS Mínimo')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0),
                Forms\Components\TextInput::make('tas_valor')
                    ->label('TAS (valor fixo)')
                    ->numeric()
                    ->prefix('R$')
                    ->default(0)
                    ->helperText('Taxa de Administração do Seguro'),
            ])->columns(4),

            Forms\Components\Section::make('Tabela de Frete')->schema([
                Forms\Components\Placeholder::make('frete_tabela')
                    ->label('')
                    ->content(function ($record) {
                        if (!$record) {
                            return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-400">Salve a transportadora primeiro, depois importe a tabela via planilha.</p>');
                        }

                        $faixas = $record->tabelaFrete()->orderBy('uf')->orderBy('cep_inicio')->orderBy('peso_min')->get();

                        if ($faixas->isEmpty()) {
                            return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-400">Nenhuma faixa cadastrada. Use a página <strong>Importar Tabela Transportadora</strong> para importar.</p>');
                        }

                        $ufs = $faixas->pluck('uf')->unique()->sort()->join(', ');
                        $html  = '<p class="text-xs text-gray-400 mb-3">' . $faixas->count() . ' faixa(s) — UFs: ' . e($ufs) . '</p>';
                        $html .= '<div style="max-height:360px;overflow-y:auto">';
                        $html .= '<table class="w-full text-xs border-collapse">';
                        $html .= '<thead><tr class="border-b border-gray-600 text-gray-400">'
                            . '<th class="text-left p-1">#</th>'
                            . '<th class="text-left p-1">UF</th>'
                            . '<th class="text-left p-1">Região</th>'
                            . '<th class="text-left p-1">CEP Início</th>'
                            . '<th class="text-left p-1">CEP Fim</th>'
                            . '<th class="text-right p-1">Peso Min</th>'
                            . '<th class="text-right p-1">Peso Max</th>'
                            . '<th class="text-right p-1">Valor Fixo</th>'
                            . '<th class="text-right p-1">Valor/kg</th>'
                            . '<th class="text-right p-1">Despacho</th>'
                            . '<th class="text-right p-1">Pedágio</th>'
                            . '<th class="text-right p-1">ADV%</th>'
                            . '<th class="text-right p-1">GRIS%</th>'
                            . '</tr></thead><tbody>';

                        foreach ($faixas as $f) {
                            $html .= '<tr class="border-b border-gray-700 hover:bg-gray-700/30">'
                                . '<td class="p-1 text-gray-500 font-mono text-xs">' . $f->id . '</td>'
                                . '<td class="p-1 font-medium">' . e($f->uf ?? '-') . '</td>'
                                . '<td class="p-1">' . e($f->regiao ?? '-') . '</td>'
                                . '<td class="p-1 font-mono">' . e($f->cep_inicio ?? '-') . '</td>'
                                . '<td class="p-1 font-mono">' . e($f->cep_fim ?? '-') . '</td>'
                                . '<td class="text-right p-1">' . number_format((float)$f->peso_min, 2, ',', '.') . '</td>'
                                . '<td class="text-right p-1">' . number_format((float)$f->peso_max, 2, ',', '.') . '</td>'
                                . '<td class="text-right p-1">' . ($f->valor_fixo ? 'R$ ' . number_format((float)$f->valor_fixo, 2, ',', '.') : '-') . '</td>'
                                . '<td class="text-right p-1">' . ($f->valor_kg ? 'R$ ' . number_format((float)$f->valor_kg, 2, ',', '.') : '-') . '</td>'
                                . '<td class="text-right p-1">' . ($f->despacho ? 'R$ ' . number_format((float)$f->despacho, 2, ',', '.') : '-') . '</td>'
                                . '<td class="text-right p-1">' . ($f->pedagio_valor ? 'R$ ' . number_format((float)$f->pedagio_valor, 2, ',', '.') . '/' . number_format((float)$f->pedagio_fracao_kg, 0, ',', '.') . 'kg' : '-') . '</td>'
                                . '<td class="text-right p-1">' . ($f->adv_percentual ? number_format((float)$f->adv_percentual, 2, ',', '.') . '%' . ($f->adv_minimo ? ' (mín R$ ' . number_format((float)$f->adv_minimo, 2, ',', '.') . ')' : '') : '-') . '</td>'
                                . '<td class="text-right p-1">' . ($f->gris_percentual ? number_format((float)$f->gris_percentual, 2, ',', '.') . '%' . ($f->gris_minimo ? ' (mín R$ ' . number_format((float)$f->gris_minimo, 2, ',', '.') . ')' : '') : '-') . '</td>'
                                . '</tr>';
                        }

                        $html .= '</tbody></table></div>';
                        return new \Illuminate\Support\HtmlString($html);
                    }),
            ])->collapsible()->collapsed(fn ($record) => $record && $record->tabelaFrete()->count() > 0),

            Forms\Components\Section::make('Taxas Especiais (TDA, TRT, TAR)')->schema([
                Forms\Components\Placeholder::make('taxas_tabela')
                    ->label('')
                    ->content(function ($record) {
                        if (!$record) {
                            return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-400">Salve a transportadora primeiro, depois importe as taxas via planilha.</p>');
                        }

                        $taxas = $record->taxas()->orderBy('tipo_taxa')->orderBy('uf')->get();

                        if ($taxas->isEmpty()) {
                            return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-400">Nenhuma taxa especial cadastrada. Use a página <strong>Importar Tabela Transportadora</strong> para importar.</p>');
                        }

                        $html  = '<p class="text-xs text-gray-400 mb-3">' . $taxas->count() . ' taxa(s) cadastrada(s)</p>';
                        $html .= '<div style="max-height:300px;overflow-y:auto">';
                        $html .= '<table class="w-full text-xs border-collapse">';
                        $html .= '<thead><tr class="border-b border-gray-600 text-gray-400">'
                            . '<th class="text-left p-1">Tipo</th>'
                            . '<th class="text-left p-1">UF</th>'
                            . '<th class="text-left p-1">Cidade</th>'
                            . '<th class="text-left p-1">CEP Início</th>'
                            . '<th class="text-left p-1">CEP Fim</th>'
                            . '<th class="text-right p-1">Valor Fixo</th>'
                            . '<th class="text-right p-1">Percentual</th>'
                            . '<th class="text-left p-1">Obs</th>'
                            . '</tr></thead><tbody>';

                        foreach ($taxas as $t) {
                            $html .= '<tr class="border-b border-gray-700 hover:bg-gray-700/30">'
                                . '<td class="p-1"><span class="font-medium text-warning-400">' . e($t->tipo_taxa) . '</span></td>'
                                . '<td class="p-1">' . e($t->uf ?? '-') . '</td>'
                                . '<td class="p-1">' . e($t->cidade ?? '-') . '</td>'
                                . '<td class="p-1 font-mono">' . e($t->cep_inicio ?? '-') . '</td>'
                                . '<td class="p-1 font-mono">' . e($t->cep_fim ?? '-') . '</td>'
                                . '<td class="text-right p-1">' . ($t->valor_fixo ? 'R$ ' . number_format((float)$t->valor_fixo, 2, ',', '.') : '-') . '</td>'
                                . '<td class="text-right p-1">' . ($t->percentual ? number_format((float)$t->percentual, 2, ',', '.') . '%' : '-') . '</td>'
                                . '<td class="p-1 text-gray-400">' . e($t->observacao ?? '') . '</td>'
                                . '</tr>';
                        }

                        $html .= '</tbody></table></div>';
                        return new \Illuminate\Support\HtmlString($html);
                    }),
            ])->collapsible()->collapsed(fn ($record) => $record && $record->taxas()->count() > 0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_transportadora')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('cnpj')->label('CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('ufsAtendidas.uf')
                    ->label('UFs')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('tabela_frete_count')
                    ->label('Faixas Frete')
                    ->counts('tabelaFrete'),
                Tables\Columns\TextColumn::make('taxas_count')
                    ->label('Taxas')
                    ->counts('taxas'),
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
            'index' => Pages\ListTransportadoras::route('/'),
            'create' => Pages\CreateTransportadora::route('/create'),
            'edit' => Pages\EditTransportadora::route('/{record}/edit'),
        ];
    }
}
