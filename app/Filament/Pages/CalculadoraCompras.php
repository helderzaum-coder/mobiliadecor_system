<?php

namespace App\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class CalculadoraCompras extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Ferramentas';
    protected static ?string $navigationLabel = 'Calculadora Compras';
    protected static ?string $title = 'Calculadora de Compras';
    protected static string $view = 'filament.pages.calculadora-compras';

    public ?string $mes_referencia = null;

    public function mount(): void
    {
        $this->mes_referencia = now()->format('Y-m');
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('mes_referencia')
                ->label('Mês de Referência')
                ->options(function () {
                    $options = [];
                    for ($i = 0; $i < 6; $i++) {
                        $d = now()->addMonths($i)->startOfMonth();
                        $options[$d->format('Y-m')] = ucfirst($d->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
                    }
                    return $options;
                })
                ->reactive()
                ->afterStateUpdated(fn () => null),
        ];
    }

    public function getCalendarioData(): array
    {
        $ref = Carbon::createFromFormat('Y-m', $this->mes_referencia ?? now()->format('Y-m'))->startOfMonth();
        $prazos = [14, 28, 42];

        // Calcular o 6º dia útil de cada mês relevante (mês atual + próximos 2)
        $diasBloqueados = [];
        for ($m = 0; $m <= 2; $m++) {
            $mes = $ref->copy()->addMonths($m);
            // Dia 20
            $dia20 = $mes->copy()->day(20);
            $diasBloqueados[] = $dia20->format('Y-m-d');

            // 6º dia útil
            $sextoUtil = $this->getSextoDiaUtil($mes);
            if ($sextoUtil) {
                $diasBloqueados[] = $sextoUtil->format('Y-m-d');
            }
        }

        // Para cada dia do mês de referência, verificar se algum vencimento cai em dia bloqueado
        $diasDoMes = [];
        $totalDias = $ref->daysInMonth;

        for ($dia = 1; $dia <= $totalDias; $dia++) {
            $dataCompra = $ref->copy()->day($dia);
            $vencimentos = [];
            $bloqueado = false;
            $motivos = [];

            foreach ($prazos as $prazo) {
                $venc = $dataCompra->copy()->addDays($prazo);
                $vencStr = $venc->format('Y-m-d');
                $cairEmBloqueado = in_array($vencStr, $diasBloqueados);

                if ($cairEmBloqueado) {
                    $bloqueado = true;
                    $motivo = $venc->day == 20
                        ? "Dia 20 ({$prazo}d)"
                        : "6º dia útil ({$prazo}d)";
                    $motivos[] = $motivo;
                }

                $vencimentos[] = [
                    'prazo' => $prazo,
                    'data' => $venc->format('d/m'),
                    'bloqueado' => $cairEmBloqueado,
                ];
            }

            $diasDoMes[] = [
                'dia' => $dia,
                'data' => $dataCompra->format('Y-m-d'),
                'dia_semana' => $dataCompra->locale('pt_BR')->isoFormat('ddd'),
                'fim_semana' => $dataCompra->isWeekend(),
                'bloqueado' => $bloqueado,
                'motivos' => $motivos,
                'vencimentos' => $vencimentos,
            ];
        }

        // Info dos dias bloqueados para exibir
        $infoBloqueados = [];
        foreach ($diasBloqueados as $db) {
            $d = Carbon::parse($db);
            $infoBloqueados[] = $d->format('d/m/Y') . ' (' . ($d->day == 20 ? 'Dia 20' : '6º dia útil') . ')';
        }

        return [
            'mes_label' => ucfirst($ref->locale('pt_BR')->isoFormat('MMMM [de] YYYY')),
            'dias' => $diasDoMes,
            'dias_bloqueados_info' => $infoBloqueados,
            'prazos' => $prazos,
        ];
    }

    private function getSextoDiaUtil(Carbon $mes): ?Carbon
    {
        // Feriados nacionais fixos (pode expandir)
        $feriadosFixos = [
            '01-01', // Confraternização
            '04-21', // Tiradentes
            '05-01', // Trabalho
            '09-07', // Independência
            '10-12', // N.S. Aparecida
            '11-02', // Finados
            '11-15', // Proclamação
            '12-25', // Natal
        ];

        $ano = $mes->year;
        $feriados = array_map(fn ($f) => "{$ano}-{$f}", $feriadosFixos);

        $dia = $mes->copy()->startOfMonth();
        $uteis = 0;

        while ($uteis < 6) {
            if (!$dia->isWeekend() && !in_array($dia->format('Y-m-d'), $feriados)) {
                $uteis++;
                if ($uteis === 6) {
                    return $dia->copy();
                }
            }
            $dia->addDay();
        }

        return null;
    }
}
