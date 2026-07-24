<?php

namespace App\Filament\Resources\LoteRecebimentoResource\Pages;

use App\Filament\Resources\LoteRecebimentoResource;
use App\Models\ContaReceber;
use App\Models\LoteRecebimento;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class ViewLoteRecebimento extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = LoteRecebimentoResource::class;
    protected static string $view = 'filament.resources.lote-recebimento.view';

    public $record;

    public function mount($record): void
    {
        $this->record = LoteRecebimento::findOrFail($record);
    }

    public function getTitle(): string
    {
        return "Lote #{$this->record->id} — " . $this->record->data_recebimento->format('d/m/Y');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ContaReceber::where('lote_recebimento_id', $this->record->id))
            ->columns([
                Tables\Columns\TextColumn::make('venda.numero_pedido_canal')
                    ->label('Pedido')
                    ->searchable(),
                Tables\Columns\TextColumn::make('forma_pagamento')
                    ->label('Canal')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('venda.cliente_nome')
                    ->label('Cliente')
                    ->limit(30),
                Tables\Columns\TextColumn::make('valor_parcela')
                    ->label('Valor')
                    ->money('BRL')
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('data_recebimento')
                    ->label('Recebido em')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'recebido' => 'success',
                        'pendente' => 'warning',
                        default => 'gray',
                    }),
            ]);
    }

    public function corrigirPendentesAction(): Action
    {
        return Action::make('corrigirPendentes')
            ->label('Corrigir Pendentes')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Corrigir contas pendentes neste lote')
            ->modalDescription('Vai marcar como recebido todas as contas deste lote que ainda estão pendentes. Continuar?')
            ->visible(fn () => ContaReceber::where('lote_recebimento_id', $this->record->id)->where('status', 'pendente')->exists())
            ->action(function () {
                $contas = ContaReceber::where('lote_recebimento_id', $this->record->id)
                    ->where('status', 'pendente')
                    ->get();

                foreach ($contas as $conta) {
                    $conta->update([
                        'status' => 'recebido',
                        'data_recebimento' => $this->record->data_recebimento,
                    ]);

                    if ($conta->id_venda) {
                        $pendentes = ContaReceber::where('id_venda', $conta->id_venda)
                            ->where('status', 'pendente')->count();
                        if ($pendentes === 0) {
                            $conta->venda?->update([
                                'repasse_recebido' => true,
                                'data_recebimento' => $this->record->data_recebimento,
                            ]);
                        }
                    }
                }

                Notification::make()
                    ->title($contas->count() . ' conta(s) corrigida(s) para recebido.')
                    ->success()
                    ->send();
            });
    }

    public function desfazerLoteAction(): Action
    {
        return Action::make('desfazerLote')
            ->label('Desfazer Lote Inteiro')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Desfazer Lote de Recebimento')
            ->modalDescription('Isso vai reverter TODOS os recebimentos e descontos deste lote para pendente. Tem certeza?')
            ->action(function () {
                $contas = $this->record->contasReceber;
                foreach ($contas as $conta) {
                    $conta->update([
                        'status' => 'pendente',
                        'data_recebimento' => null,
                        'lote_recebimento_id' => null,
                    ]);
                    if ($conta->venda) {
                        $conta->venda->update([
                            'repasse_recebido' => false,
                            'data_recebimento' => null,
                        ]);
                    }
                }

                // Remover descontos vinculados
                $this->record->descontos()->delete();

                // Deletar o lote
                $this->record->delete();

                Notification::make()
                    ->title('Lote desfeito. Todos os recebimentos voltaram para pendente e descontos foram removidos.')
                    ->success()
                    ->send();

                $this->redirect(LoteRecebimentoResource::getUrl('index'));
            });
    }
}
