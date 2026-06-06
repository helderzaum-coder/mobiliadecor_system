<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Resources\ContaPagarResource;
use App\Models\ContaPagar;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditContaPagar extends EditRecord
{
    protected static string $resource = ContaPagarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('salvar_recorrencia')
                ->label('Salvar para toda recorrência')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Aplicar alterações para toda a recorrência')
                ->modalDescription('Isso vai atualizar valor, categoria, banco, forma de pagamento e juros de todas as parcelas PENDENTES futuras deste grupo.')
                ->action(function () {
                    $record = $this->record;
                    $data = $this->form->getState();

                    // Salva o registro atual
                    $record->update($data);

                    if (empty($record->grupo_recorrencia)) {
                        Notification::make()->title('Esta conta não pertence a um grupo recorrente.')->warning()->send();
                        return;
                    }

                    // Atualiza todas as pendentes futuras do mesmo grupo
                    $atualizados = ContaPagar::where('grupo_recorrencia', $record->grupo_recorrencia)
                        ->where('id_conta_pagar', '!=', $record->id_conta_pagar)
                        ->where('status', 'pendente')
                        ->where('data_vencimento', '>=', now()->toDateString())
                        ->update([
                            'descricao' => $data['descricao'] ?? $record->descricao,
                            'valor_parcela' => $data['valor_parcela'] ?? $record->valor_parcela,
                            'categoria_id' => $data['categoria_id'] ?? $record->categoria_id,
                            'conta_bancaria_id' => $data['conta_bancaria_id'] ?? $record->conta_bancaria_id,
                            'forma_pagamento' => $data['forma_pagamento'] ?? $record->forma_pagamento,
                            'juros_atraso' => $data['juros_atraso'] ?? $record->juros_atraso,
                            'tipo_juros' => $data['tipo_juros'] ?? $record->tipo_juros,
                            'intervalo_recorrencia' => $data['intervalo_recorrencia'] ?? $record->intervalo_recorrencia,
                            'data_fim_recorrencia' => $data['data_fim_recorrencia'] ?? $record->data_fim_recorrencia,
                        ]);

                    Notification::make()
                        ->title("Recorrência atualizada: {$atualizados} parcela(s) futura(s) alterada(s).")
                        ->success()->send();
                })
                ->visible(fn () => $this->record->recorrente && $this->record->grupo_recorrencia),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['datas_diferentes'] = (
            ($data['data_vencimento'] ?? null) !== ($data['data_lancamento'] ?? null) ||
            ($data['data_pagamento'] ?? null) !== ($data['data_lancamento'] ?? null)
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['datas_diferentes'])) {
            $data['data_vencimento'] = $data['data_lancamento'];
            $data['data_pagamento'] = $data['data_lancamento'];
        }
        unset($data['datas_diferentes']);

        if (!empty($data['recorrente']) && empty($data['grupo_recorrencia'])) {
            $data['grupo_recorrencia'] = Str::uuid()->toString();
        }

        return $data;
    }
}
