<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Resources\ContaPagarResource;
use App\Models\ContaPagar;
use Carbon\Carbon;
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

        // Somar juros fixo ao valor_parcela
        if (($data['tipo_juros'] ?? null) === 'fixo' && (float) ($data['juros_atraso'] ?? 0) > 0) {
            $jurosAnterior = (float) ($this->record->juros_atraso ?? 0);
            $tipoAnterior = $this->record->tipo_juros ?? null;
            if ($tipoAnterior !== 'fixo' || $jurosAnterior != (float) $data['juros_atraso']) {
                $valorBase = (float) $data['valor_parcela'];
                if ($tipoAnterior === 'fixo' && $jurosAnterior > 0) {
                    $valorBase = $valorBase - $jurosAnterior;
                }
                $data['valor_parcela'] = round($valorBase + (float) $data['juros_atraso'], 2);
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record->fresh();

        // Só gerar parcelas se acabou de ativar recorrência (não tinha grupo antes)
        $eraRecorrente = (bool) $this->record->getOriginal('recorrente');
        if (!$record->recorrente || $eraRecorrente || empty($record->grupo_recorrencia)) {
            return;
        }

        $intervalo = $record->intervalo_recorrencia;
        if (!$intervalo) return;

        $vencimento = Carbon::parse($record->data_vencimento);
        $fim = $record->data_fim_recorrencia ? Carbon::parse($record->data_fim_recorrencia) : null;

        $defaultQtd = match ($intervalo) {
            'semanal' => 52,
            'quinzenal' => 26,
            default => 12,
        };

        $qtd = $fim ? min($defaultQtd, $this->calcularQuantidade($intervalo, $vencimento, $fim)) : $defaultQtd;

        // Atualizar o registro atual como parcela 1
        $record->update([
            'numero_parcela' => 1,
            'total_parcelas' => $qtd,
        ]);

        // Criar parcelas 2..N
        for ($i = 1; $i < $qtd; $i++) {
            $dataVenc = $this->proximaData($vencimento, $intervalo, $i);
            if ($fim && $dataVenc->gt($fim)) break;

            ContaPagar::create([
                'descricao' => $record->descricao,
                'valor_parcela' => $record->valor_parcela,
                'categoria_id' => $record->categoria_id,
                'conta_bancaria_id' => $record->conta_bancaria_id,
                'forma_pagamento' => $record->forma_pagamento,
                'data_lancamento' => $record->data_lancamento,
                'data_vencimento' => $dataVenc->toDateString(),
                'status' => 'pendente',
                'recorrente' => true,
                'intervalo_recorrencia' => $intervalo,
                'data_fim_recorrencia' => $record->data_fim_recorrencia,
                'grupo_recorrencia' => $record->grupo_recorrencia,
                'numero_parcela' => $i + 1,
                'total_parcelas' => $qtd,
                'juros_atraso' => $record->juros_atraso,
                'tipo_juros' => $record->tipo_juros,
                'observacoes' => $record->observacoes,
            ]);
        }

        Notification::make()
            ->title("Recorrência criada: " . ($qtd - 1) . " parcela(s) futura(s) gerada(s).")
            ->success()
            ->send();
    }

    private function proximaData(Carbon $base, string $intervalo, int $multiplicador): Carbon
    {
        return match ($intervalo) {
            'semanal'   => $base->copy()->addWeeks($multiplicador),
            'quinzenal' => $base->copy()->addDays(15 * $multiplicador),
            default     => $base->copy()->addMonths($multiplicador),
        };
    }

    private function calcularQuantidade(string $intervalo, Carbon $inicio, Carbon $fim): int
    {
        return match ($intervalo) {
            'semanal'   => (int) $inicio->diffInWeeks($fim),
            'quinzenal' => (int) floor($inicio->diffInDays($fim) / 15),
            default     => (int) $inicio->diffInMonths($fim),
        };
    }
