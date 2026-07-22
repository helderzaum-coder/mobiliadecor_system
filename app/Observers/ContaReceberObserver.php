<?php

namespace App\Observers;

use App\Models\ContaReceber;
use App\Models\FaturaRecebimento;
use App\Models\LoteRecebimento;

class ContaReceberObserver
{
    public function saved(ContaReceber $conta): void
    {
        $this->recalcularLote($conta->lote_recebimento_id);
        $this->recalcularFatura($conta->fatura_recebimento_id);
    }

    public function deleted(ContaReceber $conta): void
    {
        $this->recalcularLote($conta->lote_recebimento_id);
        $this->recalcularFatura($conta->fatura_recebimento_id);
    }

    private function recalcularLote(?int $loteId): void
    {
        if (!$loteId) return;

        $lote = LoteRecebimento::find($loteId);
        if (!$lote) return;

        $contas = ContaReceber::where('lote_recebimento_id', $loteId)->get();

        $lote->valor_total = $contas->sum('valor_parcela');
        $lote->quantidade_contas = $contas->count();
        $lote->saveQuietly();
    }

    private function recalcularFatura(?int $faturaId): void
    {
        if (!$faturaId) return;

        $fatura = FaturaRecebimento::find($faturaId);
        if (!$fatura) return;

        $fatura->valor_total = ContaReceber::where('fatura_recebimento_id', $faturaId)->sum('valor_parcela');
        $fatura->saveQuietly();
    }
}
