<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaturaTransportadoraResource\Pages;
use App\Models\Cte;
use App\Models\ContaPagar;
use App\Models\FaturaTransportadora;
use App\Models\Transportadora;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class FaturaTransportadoraResource extends Resource
{
    protected static ?string $model = FaturaTransportadora::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Transporte';
    protected static ?string $modelLabel = 'Fatura Transportadora';
    protected static ?string $pluralModelLabel = 'Faturas Transportadoras';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('id_transportadora')
                ->label('Transportadora')
                ->relationship('transportadora', 'nome_transportadora')
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('numero_fatura')
                ->label('Nº Fatura')
                ->required()
                ->maxLength(50),
            Forms\Components\DatePicker::make('data_emissao')
                ->label('Data Emissão')
                ->required(),
            Forms\Components\TextInput::make('valor_total')
                ->label('Valor Total')
                ->required()
                ->numeric()
                ->prefix('R$'),
            Forms\Components\DatePicker::make('data_vencimento')
                ->label('Vencimento')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transportadora.nome_transportadora')->label('Transportadora')->searchable(),
                Tables\Columns\TextColumn::make('numero_fatura')->label('Nº Fatura')->searchable(),
                Tables\Columns\TextColumn::make('data_emissao')->label('Emissão')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('valor_total')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('data_vencimento')->label('Vencimento')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('ctes_count')
                    ->label('CTEs')
                    ->counts('ctes')
                    ->badge()
                    ->color('info'),
            ])
            ->defaultSort('data_emissao', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('fechar_fatura')
                    ->label('Fechar Fatura')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('id_transportadora')
                            ->label('Transportadora')
                            ->options(fn () => Transportadora::where('ativo', true)->orderBy('nome_transportadora')->pluck('nome_transportadora', 'id_transportadora')->toArray())
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('ctes_selecionados', [])),
                        Forms\Components\FileUpload::make('csv_import')
                            ->label('Importar CSV (opcional)')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->helperText('Coluna K = NUMERO CT-E. Pré-seleciona os CTEs encontrados.')
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state || !$get('id_transportadora')) return;
                                $path = collect($state)->first();
                                if (!$path) return;
                                $fullPath = storage_path('app/public/' . $path);
                                if (!file_exists($fullPath)) return;

                                $numeros = [];
                                if (($handle = fopen($fullPath, 'r')) !== false) {
                                    $row = 0;
                                    while (($line = fgetcsv($handle, 0, ';')) !== false) {
                                        $row++;
                                        if ($row <= 2) continue; // pula cabeçalhos
                                        $numero = trim($line[10] ?? ''); // coluna K (index 10)
                                        if ($numero && is_numeric($numero)) {
                                            $numeros[] = $numero;
                                        }
                                    }
                                    fclose($handle);
                                }
                                @unlink($fullPath);

                                if (empty($numeros)) return;

                                $transportadora = Transportadora::find($get('id_transportadora'));
                                $nomes = collect([$transportadora->nome_transportadora])
                                    ->merge($transportadora->aliases ?? [])
                                    ->filter()->toArray();

                                $ids = Cte::whereNull('id_fatura')
                                    ->whereIn('transportadora', $nomes)
                                    ->whereIn('numero_cte', $numeros)
                                    ->pluck('id')
                                    ->toArray();

                                $set('ctes_selecionados', $ids);
                            })
                            ->visible(fn (Forms\Get $get) => (bool) $get('id_transportadora')),
                        Forms\Components\CheckboxList::make('ctes_selecionados')
                            ->label('CTEs pendentes')
                            ->options(function (Forms\Get $get) {
                                $transportadoraId = $get('id_transportadora');
                                if (!$transportadoraId) return [];
                                $transportadora = Transportadora::find($transportadoraId);
                                if (!$transportadora) return [];

                                $nomes = collect([$transportadora->nome_transportadora])
                                    ->merge($transportadora->aliases ?? [])
                                    ->filter()
                                    ->toArray();

                                return Cte::whereNull('id_fatura')
                                    ->whereIn('transportadora', $nomes)
                                    ->orderBy('data_emissao', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn ($cte) => [
                                        $cte->id => "CTe {$cte->numero_cte} — {$cte->destinatario} — R$ " . number_format((float) $cte->valor_frete, 2, ',', '.') . " — " . ($cte->data_emissao?->format('d/m/Y') ?? '-'),
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->columns(1)
                            ->visible(fn (Forms\Get $get) => (bool) $get('id_transportadora')),
                        Forms\Components\TextInput::make('numero_fatura')
                            ->label('Nº Fatura')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\DatePicker::make('data_emissao')
                            ->label('Data Emissão')
                            ->default(now())
                            ->required(),
                        Forms\Components\DatePicker::make('data_vencimento')
                            ->label('Vencimento')
                            ->required(),
                        Forms\Components\Select::make('conta_bancaria_id')
                            ->label('Banco (conta a pagar)')
                            ->options(fn () => \App\Models\ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->searchable()
                            ->placeholder('Selecione o banco'),
                    ])
                    ->modalHeading('Fechar Fatura de Transportadora')
                    ->modalSubmitActionLabel('Fechar Fatura')
                    ->action(function (array $data) {
                        $ctes = Cte::whereIn('id', $data['ctes_selecionados'])->get();
                        $valorTotal = (float) $ctes->sum('valor_frete');

                        $fatura = FaturaTransportadora::create([
                            'id_transportadora' => $data['id_transportadora'],
                            'numero_fatura' => $data['numero_fatura'],
                            'data_emissao' => $data['data_emissao'],
                            'valor_total' => round($valorTotal, 2),
                            'data_vencimento' => $data['data_vencimento'],
                        ]);

                        Cte::whereIn('id', $data['ctes_selecionados'])->update(['id_fatura' => $fatura->id_fatura]);

                        $transportadora = Transportadora::find($data['id_transportadora']);
                        ContaPagar::create([
                            'id_fatura' => $fatura->id_fatura,
                            'descricao' => "Fatura {$data['numero_fatura']} — {$transportadora->nome_transportadora}",
                            'valor_parcela' => round($valorTotal, 2),
                            'data_vencimento' => $data['data_vencimento'],
                            'data_lancamento' => $data['data_emissao'],
                            'status' => 'pendente',
                            'numero_parcela' => 1,
                            'total_parcelas' => 1,
                            'forma_pagamento' => 'Boleto',
                            'lancamento_manual' => true,
                            'conta_bancaria_id' => $data['conta_bancaria_id'] ?? null,
                        ]);

                        Notification::make()
                            ->title("Fatura #{$data['numero_fatura']} criada — " . $ctes->count() . " CTe(s) — R$ " . number_format($valorTotal, 2, ',', '.'))
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListFaturasTransportadoras::route('/'),
            'create' => Pages\CreateFaturaTransportadora::route('/create'),
            'edit' => Pages\EditFaturaTransportadora::route('/{record}/edit'),
        ];
    }
}
