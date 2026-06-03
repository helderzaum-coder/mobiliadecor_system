<?php

namespace App\Console\Commands;

use App\Models\ContaPagar;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProcessarContasRecorrentes extends Command
{
    protected $signature = 'contas:processar-recorrentes';
    protected $description = 'Gera próximas parcelas de contas recorrentes e marca atrasadas';

    public function handle(): void
    {
        $this->marcarAtrasadas();
        $this->gerarProximasRecorrentes();
        $this->info('Contas processadas com sucesso.');
    }

    private function marcarAtrasadas(): void
    {
        $count = ContaPagar::where('status', 'pendente')
            ->whereDate('data_vencimento', '<', now())
            ->update(['status' => 'atrasado']);

        $this->info("{$count} conta(s) marcada(s) como atrasada(s).");
    }

    private function gerarProximasRecorrentes(): void
    {
        // Pega o último registro de cada grupo recorrente ativo
        $grupos = ContaPagar::where('recorrente', true)
            ->whereNotNull('grupo_recorrencia')
            ->whereIn('status', ['pendente', 'pago', 'atrasado'])
            ->selectRaw('grupo_recorrencia, MAX(data_vencimento) as ultima_data, intervalo_recorrencia, data_fim_recorrencia')
            ->groupBy('grupo_recorrencia', 'intervalo_recorrencia', 'data_fim_recorrencia')
            ->get();

        $count = 0;

        foreach ($grupos as $grupo) {
            $ultimaData = Carbon::parse($grupo->ultima_data);
            $fim = $grupo->data_fim_recorrencia ? Carbon::parse($grupo->data_fim_recorrencia) : null;
            $proximaData = $this->proximaData($ultimaData, $grupo->intervalo_recorrencia);

            // Gerar até 3 meses à frente
            $limite = now()->addMonths(3);

            while ($proximaData->lte($limite)) {
                if ($fim && $proximaData->gt($fim)) break;

                // Verifica se já existe
                $existe = ContaPagar::where('grupo_recorrencia', $grupo->grupo_recorrencia)
                    ->whereDate('data_vencimento', $proximaData->toDateString())
                    ->exists();

                if (!$existe) {
                    // Copia dados do último registro do grupo
                    $modelo = ContaPagar::where('grupo_recorrencia', $grupo->grupo_recorrencia)
                        ->orderByDesc('data_vencimento')
                        ->first();

                    if ($modelo) {
                        ContaPagar::create([
                            'id_fatura' => $modelo->id_fatura,
                            'descricao' => $modelo->descricao,
                            'valor_parcela' => $modelo->valor_parcela,
                            'data_vencimento' => $proximaData->toDateString(),
                            'data_lancamento' => now()->toDateString(),
                            'status' => 'pendente',
                            'recorrente' => true,
                            'intervalo_recorrencia' => $modelo->intervalo_recorrencia,
                            'data_fim_recorrencia' => $modelo->data_fim_recorrencia,
                            'juros_atraso' => $modelo->juros_atraso,
                            'tipo_juros' => $modelo->tipo_juros,
                            'grupo_recorrencia' => $modelo->grupo_recorrencia,
                            'forma_pagamento' => $modelo->forma_pagamento,
                            'categoria_id' => $modelo->categoria_id,
                            'conta_bancaria_id' => $modelo->conta_bancaria_id,
                            'lancamento_manual' => false,
                        ]);
                        $count++;
                    }
                }

                $proximaData = $this->proximaData($proximaData, $grupo->intervalo_recorrencia);
            }
        }

        $this->info("{$count} parcela(s) recorrente(s) gerada(s).");
    }

    private function proximaData(Carbon $base, string $intervalo): Carbon
    {
        return match ($intervalo) {
            'semanal' => $base->copy()->addWeek(),
            'quinzenal' => $base->copy()->addDays(15),
            'mensal' => $base->copy()->addMonth(),
            default => $base->copy()->addMonth(),
        };
    }
}
