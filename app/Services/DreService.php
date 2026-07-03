<?php

namespace App\Services;

use App\Models\ContaPagar;
use App\Models\ImpostoMensal;
use App\Models\Venda;
use Carbon\Carbon;

class DreService
{
    /**
     * Monta o DRE para um período, opcionalmente filtrado por CNPJ e canal.
     */
    public static function calcular(string $dataInicio, string $dataFim, ?int $cnpjId = null, ?string $canalNome = null): array
    {
        $query = Venda::whereBetween('data_venda', [$dataInicio, $dataFim])
            ->where(fn ($q) => $q->where('cancelada', false)->orWhereNull('cancelada'));

        if ($cnpjId) {
            $query->where('id_cnpj', $cnpjId);
        }
        if ($canalNome) {
            $query->where('canal_nome', $canalNome);
        }

        $vendas = $query->get();

        // Cancelamentos/devoluções no período
        $canceladasQuery = Venda::whereBetween('data_venda', [$dataInicio, $dataFim])
            ->where('cancelada', true);
        if ($cnpjId) {
            $canceladasQuery->where('id_cnpj', $cnpjId);
        }
        if ($canalNome) {
            $canceladasQuery->where('canal_nome', $canalNome);
        }
        $canceladas = $canceladasQuery->get();

        // === RECEITA BRUTA ===
        $receitaProdutos = (float) $vendas->sum('total_produtos');
        $receitaFrete = (float) $vendas->sum('valor_frete_cliente');
        $receitaBruta = $receitaProdutos + $receitaFrete;

        // === IMPOSTOS ===
        // Usa valor_imposto já calculado em cada venda.
        // Se zerado, provisiona com alíquota do mês anterior (via ImpostoMensal).
        $deducaoImpostos = 0;
        foreach ($vendas as $venda) {
            if ((float) $venda->valor_imposto > 0) {
                $deducaoImpostos += (float) $venda->valor_imposto;
            } else {
                // Provisionar com alíquota do mês anterior
                $percentual = self::getAliquotaMesAnterior($venda->data_venda, $venda->id_cnpj);
                if ($percentual > 0) {
                    $base = (float) $venda->nfe_valor ?: (float) $venda->valor_total_venda;
                    $deducaoImpostos += round($base * ($percentual / 100), 2);
                }
            }
        }

        // === DEDUÇÕES ===
        $deducaoCancelamentos = (float) $canceladas->sum('valor_total_venda');
        $totalDeducoes = $deducaoCancelamentos + $deducaoImpostos;

        // === RECEITA LÍQUIDA ===
        $receitaLiquida = $receitaBruta - $totalDeducoes;

        // === CMV ===
        $cmv = (float) $vendas->sum('custo_produtos');

        // === CUSTOS VARIÁVEIS ===
        $custoFrete = (float) $vendas->sum('valor_frete_transportadora');
        $custoComissao = (float) $vendas->sum('comissao');
        $custoAfiliado = (float) $vendas->sum('comissao_afiliado');
        $custoSubsidioPix = (float) $vendas->sum('subsidio_pix');
        $custoSubsidioMagalu = (float) $vendas->sum('subsidio_magalu');
        $totalCustosVariaveis = $custoFrete + $custoComissao + $custoAfiliado + $custoSubsidioPix + $custoSubsidioMagalu;

        // === MARGEM DE CONTRIBUIÇÃO ===
        $margemContribuicao = $receitaLiquida - $cmv - $totalCustosVariaveis;

        // === DESPESAS FIXAS (contas a pagar por categoria) ===
        // Só inclui despesas fixas quando NÃO filtra por canal (despesas são da empresa toda)
        $despesasPorCategoria = [];
        $totalDespesasFixas = 0;

        if (!$canalNome) {
            $despesasQuery = ContaPagar::whereBetween('data_vencimento', [$dataInicio, $dataFim])
                ->where(fn ($q) => $q->where('status', 'pago')->orWhere('status', 'pendente'))
                ->whereNull('lote_recebimento_id')
                ->where('forma_pagamento', '!=', 'Transferência')
                ->where('forma_pagamento', '!=', 'Estorno')
                ->where('forma_pagamento', '!=', 'Reembolso');

            $despesas = $despesasQuery->with('categoria')->get();

            foreach ($despesas->groupBy(fn ($d) => $d->categoria?->nome ?? 'Sem Categoria') as $cat => $itens) {
                $valor = (float) $itens->sum('valor_parcela');
                $despesasPorCategoria[] = [
                    'categoria' => $cat,
                    'valor' => $valor,
                    'qtd' => $itens->count(),
                ];
                $totalDespesasFixas += $valor;
            }

            usort($despesasPorCategoria, fn ($a, $b) => $b['valor'] <=> $a['valor']);
        }

        // === RESULTADO OPERACIONAL ===
        $resultadoOperacional = $margemContribuicao - $totalDespesasFixas;

        return [
            'periodo' => ['inicio' => $dataInicio, 'fim' => $dataFim],
            'qtd_vendas' => $vendas->count(),
            'qtd_canceladas' => $canceladas->count(),

            'receita_produtos' => $receitaProdutos,
            'receita_frete' => $receitaFrete,
            'receita_bruta' => $receitaBruta,

            'deducao_cancelamentos' => $deducaoCancelamentos,
            'deducao_impostos' => $deducaoImpostos,
            'total_deducoes' => $totalDeducoes,

            'receita_liquida' => $receitaLiquida,

            'cmv' => $cmv,

            'custo_frete' => $custoFrete,
            'custo_comissao' => $custoComissao,
            'custo_afiliado' => $custoAfiliado,
            'custo_subsidio_pix' => $custoSubsidioPix,
            'custo_subsidio_magalu' => $custoSubsidioMagalu,
            'total_custos_variaveis' => $totalCustosVariaveis,

            'margem_contribuicao' => $margemContribuicao,
            'margem_contribuicao_pct' => $receitaBruta > 0 ? round(($margemContribuicao / $receitaBruta) * 100, 1) : 0,

            'despesas_por_categoria' => $despesasPorCategoria,
            'total_despesas_fixas' => $totalDespesasFixas,

            'resultado_operacional' => $resultadoOperacional,
            'resultado_pct' => $receitaBruta > 0 ? round(($resultadoOperacional / $receitaBruta) * 100, 1) : 0,
        ];
    }

    /**
     * Monta DRE diário (cada dia do período como uma coluna).
     */
    public static function calcularDiario(string $dataInicio, string $dataFim, ?int $cnpjId = null, ?string $canalNome = null): array
    {
        $dias = [];
        $current = Carbon::parse($dataInicio);
        $end = Carbon::parse($dataFim);

        while ($current->lte($end)) {
            $dia = $current->toDateString();
            $dias[] = [
                'data' => $dia,
                'label' => $current->format('d/m'),
                'dre' => self::calcular($dia, $dia, $cnpjId, $canalNome),
            ];
            $current->addDay();
        }

        return $dias;
    }

    /**
     * Busca a alíquota do mês anterior para provisionar imposto.
     * Ex: venda em junho/2025 → usa alíquota de maio/2025.
     */
    public static function getAliquotaMesAnterior($dataVenda, ?int $cnpjId): float
    {
        $data = Carbon::parse($dataVenda);
        $mesAnterior = $data->copy()->subMonth();

        $query = ImpostoMensal::where('mes_referencia', $mesAnterior->month)
            ->where('ano_referencia', $mesAnterior->year);

        if ($cnpjId) {
            $query->where('id_cnpj', $cnpjId);
        }

        return (float) ($query->first()?->percentual_imposto ?? 0);
    }

    /**
     * Recalcula impostos de todas as vendas de um período usando a alíquota real (do próprio mês).
     * Chamar quando a alíquota definitiva do mês for cadastrada.
     */
    public static function recalcularImpostosPeriodo(int $mes, int $ano, ?int $cnpjId = null): int
    {
        $imposto = ImpostoMensal::where('mes_referencia', $mes)
            ->where('ano_referencia', $ano)
            ->when($cnpjId, fn ($q) => $q->where('id_cnpj', $cnpjId))
            ->first();

        if (!$imposto || (float) $imposto->percentual_imposto <= 0) {
            return 0;
        }

        $inicio = Carbon::create($ano, $mes, 1)->startOfMonth()->toDateString();
        $fim = Carbon::create($ano, $mes, 1)->endOfMonth()->toDateString();

        $query = Venda::whereBetween('data_venda', [$inicio, $fim])
            ->where(fn ($q) => $q->where('cancelada', false)->orWhereNull('cancelada'));

        if ($cnpjId) {
            $query->where('id_cnpj', $cnpjId);
        }

        $vendas = $query->get();
        $count = 0;
        $percentual = (float) $imposto->percentual_imposto;

        foreach ($vendas as $venda) {
            $base = (float) $venda->nfe_valor ?: (float) $venda->valor_total_venda;
            $valorImposto = round($base * ($percentual / 100), 2);

            $venda->update([
                'base_imposto' => $base,
                'percentual_imposto' => $percentual,
                'valor_imposto' => $valorImposto,
            ]);

            // Recalcular margens
            if (class_exists(\App\Services\VendaRecalculoService::class)) {
                \App\Services\VendaRecalculoService::recalcularMargens($venda);
            }

            $count++;
        }

        return $count;
    }
}
