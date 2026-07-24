<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoteRecebimentoResource\Pages;
use App\Models\LoteRecebimento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LoteRecebimentoResource extends Resource
{
    protected static ?string $model = LoteRecebimento::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Lotes De Recebimento';
    protected static ?string $modelLabel = 'Lote de Recebimento';
    protected static ?string $pluralModelLabel = 'Lotes de Recebimento';
    protected static ?string $slug = 'lotes-recebimento';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data_recebimento')
                ->label('Data do Recebimento')
                ->required(),
            Forms\Components\TextInput::make('descricao')
                ->label('Descrição')
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Lote #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_recebimento')
                    ->label('Data Recebimento')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('quantidade_contas')
                    ->label('Qtd. Pedidos')
                    ->sortable(),
                Tables\Columns\TextColumn::make('valor_total')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('periodo')
                    ->form([
                        Select::make('periodo')
                            ->label('Período')
                            ->options([
                                'hoje'          => 'Hoje',
                                'dia_especifico' => 'Dia específico',
                                'esta_semana'   => 'Esta semana',
                                'este_mes'      => 'Este mês',
                                'mes_passado'   => 'Mês passado',
                                'selecionar_mes' => 'Selecionar mês',
                                'customizado'   => 'Período customizado',
                            ])
                            ->reactive(),
                        DatePicker::make('dia_especifico')
                            ->label('Dia')
                            ->visible(fn ($get) => $get('periodo') === 'dia_especifico'),
                        Select::make('mes_selecionado')
                            ->label('Mês')
                            ->options(function () {
                                $options = [];
                                for ($i = 0; $i < 24; $i++) {
                                    $d = now()->subMonths($i)->startOfMonth();
                                    $options[$d->format('Y-m')] = ucfirst($d->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
                                }
                                return $options;
                            })
                            ->visible(fn ($get) => $get('periodo') === 'selecionar_mes'),
                        DatePicker::make('data_inicio')
                            ->label('De')
                            ->visible(fn ($get) => $get('periodo') === 'customizado'),
                        DatePicker::make('data_fim')
                            ->label('Até')
                            ->visible(fn ($get) => $get('periodo') === 'customizado'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $periodo = $data['periodo'] ?? null;
                        return match ($periodo) {
                            'hoje'           => $query->whereDate('data_recebimento', today()),
                            'dia_especifico' => $query->when($data['dia_especifico'] ?? null, fn ($q, $v) => $q->whereDate('data_recebimento', $v)),
                            'esta_semana'    => $query->whereBetween('data_recebimento', [now()->startOfWeek(), now()->endOfWeek()]),
                            'este_mes'       => $query->whereBetween('data_recebimento', [now()->startOfMonth(), now()->endOfMonth()]),
                            'mes_passado'    => $query->whereBetween('data_recebimento', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
                            'selecionar_mes' => isset($data['mes_selecionado'])
                                ? $query->whereBetween('data_recebimento', [
                                    Carbon::createFromFormat('Y-m', $data['mes_selecionado'])->startOfMonth(),
                                    Carbon::createFromFormat('Y-m', $data['mes_selecionado'])->endOfMonth(),
                                ])
                                : $query,
                            'customizado'    => $query
                                ->when($data['data_inicio'] ?? null, fn ($q, $v) => $q->whereDate('data_recebimento', '>=', $v))
                                ->when($data['data_fim'] ?? null, fn ($q, $v) => $q->whereDate('data_recebimento', '<=', $v)),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['periodo'] ?? null) {
                            'hoje'           => 'Hoje',
                            'dia_especifico' => 'Dia: ' . ($data['dia_especifico'] ?? ''),
                            'esta_semana'    => 'Esta semana',
                            'este_mes'       => 'Este mês',
                            'mes_passado'    => 'Mês passado',
                            'selecionar_mes' => 'Mês: ' . ($data['mes_selecionado'] ?? ''),
                            'customizado'    => 'De ' . ($data['data_inicio'] ?? '') . ' até ' . ($data['data_fim'] ?? ''),
                            default => null,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_contas')
                    ->label('Ver Pedidos')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LoteRecebimento $record) => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLotesRecebimento::route('/'),
            'view' => Pages\ViewLoteRecebimento::route('/{record}'),
        ];
    }
}
