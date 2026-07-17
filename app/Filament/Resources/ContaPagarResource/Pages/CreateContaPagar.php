<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Resources\ContaPagarResource;
use App\Models\ContaPagar;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateContaPagar extends CreateRecord
{
    protected static string $resource = ContaPagarResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $data['data_lancamento'] = $data['data_lancamento'] ?? now()->toDateString();

        // Se datas não foram diferenciadas, usar data_lancamento para todas
        if (empty($data['datas_diferentes'])) {
            $data['data_vencimento'] = $data['data_lancamento'];
            $data['data_pagamento'] = $data['data_lancamento'];
        }
        unset($data['datas_diferentes']);

        if (!empty($data['recorrente']) && !empty($data['intervalo_recorrencia'])) {
            return $this->criarRecorrencias($data);
        }

        if (($data['total_parcelas'] ?? 1) > 1) {
            return $this->criarParcelas($data);
        }

        return static::getModel()::create($data);
    }

    private function criarParcelas(array $data): ContaPagar
    {
        $total = (int) $data['total_parcelas'];
        $vencimento = Carbon::parse($data['data_vencimento']);
        $primeiro = null;

        for ($i = 1; $i <= $total; $i++) {
            $parcela = array_merge($data, [
                'numero_parcela' => $i,
                'total_parcelas' => $total,
                'data_vencimento' => $vencimento->copy()->addMonths($i - 1)->toDateString(),
            ]);
            $record = ContaPagar::create($parcela);
            $primeiro = $primeiro ?? $record;
        }

        return $primeiro;
    }

    private function criarRecorrencias(array $data): ContaPagar
    {
        $grupoId = Str::uuid()->toString();
        $vencimento = Carbon::parse($data['data_vencimento']);
        $fim = !empty($data['data_fim_recorrencia']) ? Carbon::parse($data['data_fim_recorrencia']) : null;

        // Gera 1 ano à frente (ou até data fim)
        $defaultQtd = match ($data['intervalo_recorrencia']) {
            'semanal' => 52,
            'quinzenal' => 26,
            default => 12,
        };
        $meses = $fim
            ? min($defaultQtd, $this->calcularQuantidade($data['intervalo_recorrencia'], $vencimento, $fim))
            : $defaultQtd;

        $primeiro = null;

        for ($i = 0; $i < $meses; $i++) {
            $dataVenc = $this->proximaData($vencimento, $data['intervalo_recorrencia'], $i);

            if ($fim && $dataVenc->gt($fim)) break;

            $parcela = array_merge($data, [
                'grupo_recorrencia' => $grupoId,
                'data_vencimento' => $dataVenc->toDateString(),
                'numero_parcela' => $i + 1,
                'total_parcelas' => $meses,
                'status' => $i === 0 ? ($data['status'] ?? 'pendente') : 'pendente',
                'data_pagamento' => $i === 0 ? ($data['data_pagamento'] ?? null) : null,
            ]);

            $record = ContaPagar::create($parcela);
            $primeiro = $primeiro ?? $record;
        }

        return $primeiro;
    }

    private function proximaData(Carbon $base, string $intervalo, int $multiplicador): Carbon
    {
        return match ($intervalo) {
            'semanal' => $base->copy()->addWeeks($multiplicador),
            'quinzenal' => $base->copy()->addDays(15 * $multiplicador),
            'mensal' => $base->copy()->addMonths($multiplicador),
            default => $base->copy()->addMonths($multiplicador),
        };
    }

    private function calcularQuantidade(string $intervalo, Carbon $inicio, Carbon $fim): int
    {
        return match ($intervalo) {
            'semanal' => (int) $inicio->diffInWeeks($fim),
            'quinzenal' => (int) floor($inicio->diffInDays($fim) / 15),
            'mensal' => (int) $inicio->diffInMonths($fim),
            default => (int) $inicio->diffInMonths($fim),
        };
    }
}
