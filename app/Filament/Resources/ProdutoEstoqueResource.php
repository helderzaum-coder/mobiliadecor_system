<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdutoEstoqueResource\Pages;
use App\Jobs\EspelharEstoqueJob;
use App\Models\ProdutoEstoque;
use App\Models\Tag;
use App\Models\TrocaTampoConfig;
use App\Services\EstoqueService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProdutoEstoqueResource extends Resource
{
    protected static ?string $model = ProdutoEstoque::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Produtos';
    protected static ?string $modelLabel = 'Produto';
    protected static ?string $pluralModelLabel = 'Produtos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sku')->label('SKU')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('codigo_barras')->label('Código de Barras')->maxLength(255),
            Forms\Components\TextInput::make('nome')->label('Nome')->required(),
            Forms\Components\TextInput::make('observacoes')->label('Observações (Nome simplificado)')->maxLength(255)
                ->helperText('Nome simplificado do produto para notificações Telegram'),
            Forms\Components\Select::make('formato')->label('Formato')->options([
                'S' => 'Simples',
                'E' => 'Kit/Estrutura',
            ])->default('S')->required(),
            Forms\Components\TextInput::make('saldo_fisico')->label('Saldo Físico')->numeric()->default(0),
            Forms\Components\TextInput::make('saldo_virtual')->label('Saldo Virtual (Dropshipping)')->numeric()->default(0),
            Forms\Components\TextInput::make('saldo')->label('Saldo Total')->numeric()->default(0)->disabled()->dehydrated(false)
                ->helperText('Calculado automaticamente: físico + virtual'),
            Forms\Components\TextInput::make('saldo_minimo')->label('Saldo Mínimo')->numeric()->default(0),
            Forms\Components\Toggle::make('ativo')->label('Ativo')->default(true),
            Forms\Components\Select::make('tags')
                ->label('Tags')
                ->relationship('tags', 'nome')
                ->multiple()
                ->preload()
                ->createOptionForm([
                    Forms\Components\TextInput::make('nome')->required()->maxLength(50),
                    Forms\Components\ColorPicker::make('cor')->default('#6b7280'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('codigo_barras')->label('Cód. Barras')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->sortable()->limit(40)->tooltip(fn ($record) => $record->nome),
                Tables\Columns\TextColumn::make('formato')->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'E', 'C' => 'Kit',
                        default => 'Simples',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'E', 'C' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('componentes_count')
                    ->label('Comp.')
                    ->counts('componentes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('saldo_fisico')->label('Físico')
                    ->sortable()
                    ->color('info')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('saldo_virtual')->label('Virtual')
                    ->sortable()
                    ->color('purple')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('saldo')->label('Total')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        if ($record->isKit() && $record->componentes_count > 0) {
                            $min = PHP_INT_MAX;
                            foreach ($record->componentes as $comp) {
                                $qtdNecessaria = $comp->pivot->quantidade ?: 1;
                                $disponivel = intdiv($comp->saldo, $qtdNecessaria);
                                $min = min($min, $disponivel);
                            }
                            return $min === PHP_INT_MAX ? 0 : $min;
                        }
                        return $record->saldo;
                    })
                    ->color(fn ($state, $record) => $state <= $record->saldo_minimo ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('saldo_tampo')
                    ->label('Tampo')
                    ->getStateUsing(function ($record) {
                        static $tampoCache = null;
                        if ($tampoCache === null) {
                            $configs = TrocaTampoConfig::all()->keyBy('sku_produto');
                            $skusTampo = $configs->pluck('sku_tampo')->unique()->filter();
                            $tampos = ProdutoEstoque::whereIn('sku', $skusTampo)->where('ativo', true)->pluck('saldo', 'sku');
                            $tampoCache = ['configs' => $configs, 'tampos' => $tampos];
                        }
                        $config = $tampoCache['configs']->get($record->sku);
                        if (!$config || empty($config->sku_tampo)) return null;
                        return $tampoCache['tampos']->get($config->sku_tampo);
                    })
                    ->placeholder('—')
                    ->color(fn ($state) => $state !== null && $state <= 1 ? 'danger' : 'warning')
                    ->alignCenter()
                    ->tooltip(function ($record) {
                        static $configCache = null;
                        if ($configCache === null) {
                            $configCache = TrocaTampoConfig::all()->keyBy('sku_produto');
                        }
                        $config = $configCache->get($record->sku);
                        return $config ? "Tampo: {$config->nome_tampo} ({$config->sku_tampo})" : null;
                    }),
                Tables\Columns\TextColumn::make('saldo_carcaca')
                    ->label('Carc.')
                    ->getStateUsing(function ($record) {
                        static $configCache2 = null;
                        if ($configCache2 === null) {
                            $configCache2 = TrocaTampoConfig::all()->pluck('sku_produto')->flip();
                        }
                        return $configCache2->has($record->sku) ? $record->saldo_carcaca : null;
                    })
                    ->placeholder('—')
                    ->color('info')
                    ->alignCenter()
                    ->tooltip('Carcaças reais deste SKU (antes da equalização)'),
                Tables\Columns\TextColumn::make('saldo_minimo')->label('Mín.')->sortable(),
                Tables\Columns\TextColumn::make('tags.nome')
                    ->label('Tags')
                    ->badge()
                    ->color(fn ($state, $record) => 'gray')
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('ativo')->label('Ativo')->boolean(),
            ])
            ->defaultSort('nome')
            ->filters([
                Tables\Filters\SelectFilter::make('formato')
                    ->options(['S' => 'Simples', 'E' => 'Kit']),
                Tables\Filters\TernaryFilter::make('ativo')->label('Ativo'),
                Tables\Filters\Filter::make('estoque_baixo')
                    ->label('Estoque Baixo')
                    ->query(fn ($query) => $query->whereColumn('saldo', '<=', 'saldo_minimo')),
                Tables\Filters\SelectFilter::make('tags')
                    ->label('Tag')
                    ->relationship('tags', 'nome')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_componentes')
                    ->label('Componentes')
                    ->icon('heroicon-o-queue-list')
                    ->color('gray')
                    ->visible(fn ($record) => $record->isKit())
                    ->modalHeading(fn ($record) => "Componentes: {$record->nome}")
                    ->modalContent(fn ($record) => view('filament.components.componentes-kit', ['produto' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Tables\Actions\Action::make('ver_kits')
                    ->label('Kits')
                    ->icon('heroicon-o-rectangle-group')
                    ->color('gray')
                    ->visible(fn ($record) => !$record->isKit() && $record->kits()->exists())
                    ->modalHeading(fn ($record) => "Kits que contêm: {$record->nome}")
                    ->modalContent(fn ($record) => view('filament.components.kits-do-produto', ['produto' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
                Tables\Actions\Action::make('entrada')
                    ->label('Entrada')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn ($record) => !$record->isKit())
                    ->modalHeading(fn ($record) => "Entrada: {$record->sku} - {$record->nome}")
                    ->form([
                        Forms\Components\Select::make('tipo_estoque')->label('Tipo de Estoque')
                            ->options(['fisico' => 'Físico', 'virtual' => 'Virtual (Dropshipping)'])
                            ->default('virtual')->required(),
                        Forms\Components\TextInput::make('quantidade')->label('Quantidade')->numeric()->required()->minValue(1),
                        Forms\Components\TextInput::make('referencia')->label('Referência/Obs')->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $res = EstoqueService::entrada($record->sku, (int) $data['quantidade'], 'manual', $data['referencia'] ?? null, auth()->id(), true, $data['tipo_estoque']);
                        if ($res['success']) {
                            $tipo = $data['tipo_estoque'] === 'fisico' ? 'físico' : 'virtual';
                            Notification::make()->title("Entrada {$tipo} (+{$data['quantidade']}) {$record->sku} → total {$res['saldo']}")->success()->send();
                        } else {
                            Notification::make()->title($res['erro'] ?? 'Erro')->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('saida')
                    ->label('Saída')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('danger')
                    ->visible(fn ($record) => !$record->isKit())
                    ->modalHeading(fn ($record) => "Saída: {$record->sku} - {$record->nome}")
                    ->form([
                        Forms\Components\Select::make('tipo_estoque')->label('Tipo de Estoque')
                            ->options(['fisico' => 'Físico', 'virtual' => 'Virtual (Dropshipping)'])
                            ->default('virtual')->required(),
                        Forms\Components\TextInput::make('quantidade')->label('Quantidade')->numeric()->required()->minValue(1),
                        Forms\Components\TextInput::make('referencia')->label('Referência/Obs')->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $res = EstoqueService::saida($record->sku, (int) $data['quantidade'], 'manual', $data['referencia'] ?? null, auth()->id(), true, $data['tipo_estoque']);
                        if ($res['success']) {
                            $tipo = $data['tipo_estoque'] === 'fisico' ? 'físico' : 'virtual';
                            Notification::make()->title("Saída {$tipo} (-{$data['quantidade']}) {$record->sku} → total {$res['saldo']}")->success()->send();
                        } else {
                            Notification::make()->title($res['erro'] ?? 'Erro')->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('balanco')
                    ->label('Balanço')
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->isKit())
                    ->modalHeading(fn ($record) => "Balanço: {$record->sku} - {$record->nome}")
                    ->form([
                        Forms\Components\Select::make('tipo_estoque')->label('Tipo de Estoque')
                            ->options(['fisico' => 'Físico', 'virtual' => 'Virtual (Dropshipping)'])
                            ->default('virtual')->required(),
                        Forms\Components\TextInput::make('novo_saldo')->label('Novo Saldo')->numeric()->required()->minValue(0),
                        Forms\Components\TextInput::make('referencia')->label('Referência/Obs')->maxLength(255),
                    ])
                    ->action(function ($record, array $data) {
                        $res = EstoqueService::balanco($record->sku, (int) $data['novo_saldo'], 'manual', $data['referencia'] ?? null, auth()->id(), true, $data['tipo_estoque']);
                        if ($res['success']) {
                            $tipo = $data['tipo_estoque'] === 'fisico' ? 'físico' : 'virtual';
                            Notification::make()->title("Balanço {$tipo} (={$data['novo_saldo']}) {$record->sku} → total {$res['saldo']}")->success()->send();
                        } else {
                            Notification::make()->title($res['erro'] ?? 'Erro')->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('balanco_carcaca')
                    ->label('Carcaças')
                    ->icon('heroicon-o-cube')
                    ->color('info')
                    ->visible(function ($record) {
                        static $configSkus = null;
                        if ($configSkus === null) {
                            $configSkus = TrocaTampoConfig::pluck('sku_produto')->flip();
                        }
                        return $configSkus->has($record->sku);
                    })
                    ->modalHeading(fn ($record) => "Carcaças: {$record->sku} - {$record->nome}")
                    ->modalDescription('Informe a quantidade real de carcaças físicas deste SKU específico (independente da equalização).')
                    ->form([
                        Forms\Components\TextInput::make('quantidade')
                            ->label('Quantidade de Carcaças')
                            ->numeric()->required()->minValue(0)
                            ->helperText(fn ($record) => "Saldo atual: {$record->saldo_carcaca}"),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['saldo_carcaca' => (int) $data['quantidade']]);
                        Notification::make()->title("Carcaças de {$record->sku} atualizadas para {$data['quantidade']}")->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportar_csv')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->color('gray')
                    ->action(function () {
                        $configs = TrocaTampoConfig::all()->keyBy('sku_produto');
                        $skusTampo = $configs->pluck('sku_tampo')->unique()->filter();
                        $tampos = ProdutoEstoque::whereIn('sku', $skusTampo)->where('ativo', true)->pluck('saldo', 'sku');

                        $produtos = ProdutoEstoque::where('ativo', true)
                            ->whereNotIn('formato', ['E', 'C'])
                            ->orderBy('sku')
                            ->get(['sku', 'nome', 'saldo_fisico', 'saldo_virtual', 'saldo_carcaca']);

                        $csv = "sku;nome;saldo_fisico;saldo_virtual;tampo;carcacas\n";
                        foreach ($produtos as $p) {
                            $config = $configs->get($p->sku);
                            $saldoTampo = $config ? ($tampos->get($config->sku_tampo) ?? '') : '';
                            $carcaca = $config ? ($p->saldo_carcaca ?? '') : '';
                            $csv .= "{$p->sku};" . str_replace(';', ',', $p->nome) . ";{$p->saldo_fisico};{$p->saldo_virtual};{$saldoTampo};{$carcaca}\n";
                        }

                        $path = storage_path('app/public/estoque_export.csv');
                        file_put_contents($path, $csv);

                        Notification::make()->title("Exportados {$produtos->count()} produtos simples.")->success()->send();
                        return response()->download($path, 'estoque_' . now()->format('Y-m-d') . '.csv');
                    }),
                Tables\Actions\Action::make('importar_csv')
                    ->label('Importar CSV')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('info')
                    ->form([
                        Forms\Components\FileUpload::make('arquivo')
                            ->label('Arquivo CSV (separador ;)')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->required()
                            ->disk('public')
                            ->directory('imports'),
                        Forms\Components\Toggle::make('sync_bling')
                            ->label('Sincronizar com Bling após importar')
                            ->default(true),
                    ])
                    ->action(function (array $data) {
                        $path = storage_path('app/public/' . $data['arquivo']);
                        $lines = array_filter(explode("\n", file_get_contents($path)));
                        array_shift($lines); // remove header

                        $atualizados = 0;
                        foreach ($lines as $line) {
                            $cols = str_getcsv($line, ';');
                            if (count($cols) < 4) continue;

                            [$sku, $nome, $fisico, $virtual] = $cols;
                            $produto = ProdutoEstoque::where('sku', trim($sku))->where('ativo', true)->first();
                            if (!$produto || $produto->isKit()) continue;

                            $produto->saldo_fisico = max(0, (int) $fisico);
                            $produto->saldo_virtual = max(0, (int) $virtual);
                            $produto->save();

                            if ($data['sync_bling']) {
                                \App\Jobs\SyncEstoqueBlingJob::dispatch($produto->sku, $produto->saldo, "Importação CSV", 'B');
                            }
                            $atualizados++;
                        }

                        @unlink($path);
                        Notification::make()->title("{$atualizados} produtos atualizados via CSV.")->success()->send();
                    }),
                Tables\Actions\Action::make('importar_bling')
                    ->label('Importar do Bling')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Importa o cadastro de produtos do Bling Primary (SKU, nome, código de barras). Produtos existentes NÃO terão saldo alterado.')
                    ->action(function () {
                        \App\Jobs\ImportarProdutosBlingJob::dispatch();
                        Notification::make()->title('Importação iniciada em background.')->info()->send();
                    }),
                Tables\Actions\Action::make('espelhar_estoque')
                    ->label('Espelhar Estoque Primary → Secondary')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Espelhar Estoque')
                    ->modalDescription('Isso vai copiar o saldo de TODOS os produtos da Primary (Geral + Virtual) para a Secondary. Pode demorar alguns minutos. Você receberá uma notificação ao concluir.')
                    ->action(function () {
                        EspelharEstoqueJob::dispatch();
                        Notification::make()
                            ->title('Espelhamento enviado para processamento')
                            ->body('Você receberá uma notificação quando concluir.')
                            ->info()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdutosEstoque::route('/'),
            'create' => Pages\CreateProdutoEstoque::route('/create'),
            'edit' => Pages\EditProdutoEstoque::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
