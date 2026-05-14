<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdutoEstoqueResource\Pages;
use App\Jobs\EspelharEstoqueJob;
use App\Models\ProdutoEstoque;
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
            Forms\Components\TextInput::make('nome')->label('Nome')->required(),
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
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
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
                    ->color(fn ($record) => $record->saldo <= $record->saldo_minimo ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('saldo_minimo')->label('Mín.')->sortable(),
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
                        $produtos = ProdutoEstoque::where('ativo', true)
                            ->whereNotIn('formato', ['E', 'C'])
                            ->orderBy('sku')
                            ->get(['sku', 'nome', 'saldo_fisico', 'saldo_virtual']);

                        $csv = "sku;nome;saldo_fisico;saldo_virtual\n";
                        foreach ($produtos as $p) {
                            $csv .= "{$p->sku};" . str_replace(';', ',', $p->nome) . ";{$p->saldo_fisico};{$p->saldo_virtual}\n";
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
                    ->modalDescription('Importa todos os produtos e saldos do Bling Primary. Produtos existentes terão saldo atualizado.')
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
