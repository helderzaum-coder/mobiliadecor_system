<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFinanceira;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\FaturaRecebimento;
use App\Models\ReclamacaoML;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CaixaExportController extends Controller
{
    private string $periodo;
    private ?string $mes_selecionado;
    private ?string $data_inicio;
    private ?string $data_fim;
    private ?string $conta_bancaria_id;
    private ?string $categoria_id;
    private ?string $visao;
    private ?string $tipo_movimento;
    private bool $exibir_transferencias;
    private bool $exibir_previsoes;

    public function export(Request $request)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $this->periodo             = $request->get('periodo', 'este_mes');
        $this->mes_selecionado     = $request->get('mes_selecionado');
        $this->data_inicio         = $request->get('data_inicio');
        $this->data_fim            = $request->get('data_fim');
        $this->conta_bancaria_id   = $request->get('conta_bancaria_id');
        $this->categoria_id        = $request->get('categoria_id');
        $this->visao               = $request->get('visao', 'diaria');
        $this->tipo_movimento      = $request->get('tipo_movimento');
        $this->exibir_transferencias = (bool) $request->get('exibir_transferencias', false);
        $this->exibir_previsoes    = (bool) $request->get('exibir_previsoes', false);

        $entradas = $this->tipo_movimento === 'saidas'   ? collect() : $this->getEntradas();
        $saidas   = $this->tipo_movimento === 'entradas' ? collect() : $this->getSaidas();
        $todas    = $entradas->concat($saidas)->sortBy('data')->values();

        $filename = 'fluxo_caixa_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($todas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Data', 'Tipo', 'Descrição', 'Categoria', 'Banco', 'Valor'], ';');
            foreach ($todas as $item) {
                fputcsv($out, [
                    Carbon::parse($item['data'])->format('d/m/Y'),
                    $item['tipo'] === 'entrada' ? 'Entrada' : 'Saída',
                    $item['descricao'],
                    $item['categoria'],
                    $item['banco'],
                    number_format($item['valor'], 2, ',', '.'),
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function getDataRange(): array
    {
        return match ($this->periodo) {
            'este_mes'      => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'mes_passado'   => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'selecionar_mes' => $this->mes_selecionado
                ? [Carbon::createFromFormat('Y-m', $this->mes_selecionado)->startOfMonth()->toDateString(),
                   Carbon::createFromFormat('Y-m', $this->mes_selecionado)->endOfMonth()->toDateString()]
                : [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'customizado'   => [
                $this->data_inicio ?? now()->startOfMonth()->toDateString(),
                $this->data_fim    ?? now()->endOfMonth()->toDateString(),
            ],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    private function isCategoriaTransferencia(): bool
    {
        if (!$this->categoria_id) return false;
        return CategoriaFinanceira::where('id', $this->categoria_id)->where('sistema', true)->where('nome', 'Transferência')->exists();
    }

    private function getEntradas(): Collection
    {
        [$inicio, $fim] = $this->getDataRange();
        $filtrandoTransferencia = $this->isCategoriaTransferencia();

        $query = ContaReceber::with(['venda', 'contaBancaria', 'categoria', 'loteRecebimento'])
            ->where('status', 'recebido')
            ->whereNotNull('data_recebimento')
            ->whereBetween('data_recebimento', [$inicio, $fim]);

        if (!$this->exibir_transferencias && !$this->conta_bancaria_id && !$filtrandoTransferencia) {
            $query->where('forma_pagamento', '!=', 'Transferência');
        }
        if ($this->conta_bancaria_id) {
            $query->where('conta_bancaria_id', $this->conta_bancaria_id);
        }
        if ($filtrandoTransferencia) {
            $query->where(fn ($q) => $q->where('categoria_id', $this->categoria_id)->orWhere('forma_pagamento', 'Transferência'));
        } elseif ($this->categoria_id) {
            $query->where('categoria_id', $this->categoria_id);
        }

        $registros = $query->get();
        $comLote   = $registros->filter(fn ($r) => !empty($r->lote_recebimento_id));
        $semLote   = $registros->filter(fn ($r) => empty($r->lote_recebimento_id));
        $resultado = collect();

        foreach ($comLote->groupBy('lote_recebimento_id') as $loteId => $itensLote) {
            $lote           = $itensLote->first()->loteRecebimento;
            $totalEntradas  = (float) $itensLote->sum('valor_parcela');
            $descontosLote  = ContaPagar::where('lote_recebimento_id', $loteId)->sum('valor_parcela');
            $valorLiquido   = $totalEntradas - (float) $descontosLote;
            $descricao      = $lote?->descricao ?? $itensLote->first()->observacoes ?? 'Lote #' . $loteId;

            $resultado->push([
                'data'      => $itensLote->first()->data_recebimento->format('Y-m-d'),
                'tipo'      => 'entrada',
                'descricao' => $descricao,
                'categoria' => $itensLote->first()->categoria?->nome ?? $itensLote->first()->forma_pagamento ?? '-',
                'banco'     => $itensLote->first()->contaBancaria?->nome ?? '-',
                'valor'     => round($valorLiquido, 2),
            ]);
        }

        $transferencias = $semLote->filter(fn ($r) => !empty($r->transferencia_id));
        $naoTransf      = $semLote->filter(fn ($r) => empty($r->transferencia_id));
        $comObs         = $naoTransf->filter(fn ($r) => !empty($r->observacoes) && !str_starts_with($r->observacoes, 'Repasse #'));
        $semObs         = $naoTransf->filter(fn ($r) => empty($r->observacoes) || str_starts_with($r->observacoes, 'Repasse #'));

        foreach ($transferencias as $r) {
            $resultado->push(['data' => $r->data_recebimento->format('Y-m-d'), 'tipo' => 'entrada', 'descricao' => $r->observacoes ?: 'Transferência', 'categoria' => $r->categoria?->nome ?? $r->forma_pagamento ?? '-', 'banco' => $r->contaBancaria?->nome ?? '-', 'valor' => (float) $r->valor_parcela]);
        }
        foreach ($comObs->groupBy(fn ($r) => $r->observacoes . '|' . $r->data_recebimento->format('Y-m-d')) as $itensLote) {
            $resultado->push(['data' => $itensLote->first()->data_recebimento->format('Y-m-d'), 'tipo' => 'entrada', 'descricao' => $itensLote->first()->observacoes, 'categoria' => $itensLote->first()->categoria?->nome ?? $itensLote->first()->forma_pagamento ?? '-', 'banco' => $itensLote->first()->contaBancaria?->nome ?? '-', 'valor' => (float) $itensLote->sum('valor_parcela')]);
        }
        foreach ($semObs as $r) {
            $resultado->push(['data' => $r->data_recebimento->format('Y-m-d'), 'tipo' => 'entrada', 'descricao' => $r->venda ? "Repasse #{$r->venda->numero_pedido_canal}" : ($r->observacoes ?: 'Recebimento'), 'categoria' => $r->categoria?->nome ?? $r->forma_pagamento ?? '-', 'banco' => $r->contaBancaria?->nome ?? '-', 'valor' => (float) $r->valor_parcela]);
        }

        if ($this->exibir_previsoes) {
            FaturaRecebimento::with(['canal', 'contaBancaria'])->where('status', 'aberta')->whereBetween('data_prevista', [$inicio, $fim])
                ->when($this->conta_bancaria_id, fn ($q) => $q->where('conta_bancaria_id', $this->conta_bancaria_id))
                ->get()->each(fn ($f) => $resultado->push(['data' => $f->data_prevista->format('Y-m-d'), 'tipo' => 'entrada', 'descricao' => '🔮 ' . ($f->descricao ?: ($f->canal?->nome_canal ?? 'Fatura #' . $f->id)), 'categoria' => $f->canal?->nome_canal ?? 'Previsto', 'banco' => $f->contaBancaria?->nome ?? '-', 'valor' => (float) $f->valor_total]));
        }

        if (!$this->categoria_id) {
            ReclamacaoML::where('status', 'liberada')->whereBetween('data_resolucao', [$inicio, $fim])
                ->when($this->conta_bancaria_id, fn ($q) => $q->where('conta_bancaria_id', $this->conta_bancaria_id))
                ->get()->each(fn ($r) => $resultado->push(['data' => $r->data_resolucao->format('Y-m-d'), 'tipo' => 'entrada', 'descricao' => '✅ Reclamação liberada — Pedido ' . ($r->numero_pedido ?? "#{$r->id}"), 'categoria' => 'Reclamação ML', 'banco' => $r->contaBancaria?->nome ?? '-', 'valor' => (float) $r->valor]));
        }

        return $resultado;
    }

    private function getSaidas(): Collection
    {
        [$inicio, $fim] = $this->getDataRange();
        $filtrandoTransferencia = $this->isCategoriaTransferencia();

        $query = ContaPagar::with(['fatura', 'contaBancaria', 'categoria'])
            ->where('status', 'pago')
            ->whereNotNull('data_pagamento')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->whereNull('lote_recebimento_id');

        if (!$this->exibir_transferencias && !$this->conta_bancaria_id && !$filtrandoTransferencia) {
            $query->where('forma_pagamento', '!=', 'Transferência');
        }
        if ($this->conta_bancaria_id) {
            $query->where('conta_bancaria_id', $this->conta_bancaria_id);
        }
        if ($filtrandoTransferencia) {
            $query->where(fn ($q) => $q->where('categoria_id', $this->categoria_id)->orWhere('forma_pagamento', 'Transferência'));
        } elseif ($this->categoria_id) {
            $query->where('categoria_id', $this->categoria_id);
        }

        $resultado = $query->get()->map(fn ($r) => [
            'data'      => $r->data_pagamento->format('Y-m-d'),
            'tipo'      => 'saida',
            'descricao' => $r->descricao ?: $r->observacoes ?: ($r->fatura ? "Fatura #{$r->fatura->id_fatura}" : 'Pagamento'),
            'categoria' => $r->categoria?->nome ?? $r->forma_pagamento ?? '-',
            'banco'     => $r->contaBancaria?->nome ?? '-',
            'valor'     => (float) $r->valor_parcela,
        ]);

        if ($this->exibir_previsoes) {
            ContaPagar::with(['contaBancaria', 'categoria'])->whereIn('status', ['aberto', 'pendente'])
                ->whereBetween('data_vencimento', [$inicio, $fim])->whereNull('lote_recebimento_id')
                ->when($this->conta_bancaria_id, fn ($q) => $q->where('conta_bancaria_id', $this->conta_bancaria_id))
                ->when($this->categoria_id, fn ($q) => $q->where('categoria_id', $this->categoria_id))
                ->get()->each(fn ($r) => $resultado->push(['data' => $r->data_vencimento->format('Y-m-d'), 'tipo' => 'saida', 'descricao' => $r->descricao ?: $r->observacoes ?: 'Conta a pagar', 'categoria' => $r->categoria?->nome ?? $r->forma_pagamento ?? '-', 'banco' => $r->contaBancaria?->nome ?? '-', 'valor' => (float) $r->valor_parcela]));
        }

        if (!$this->categoria_id) {
            ReclamacaoML::where('status', '!=', 'estornada')->whereBetween('data_abertura', [$inicio, $fim])
                ->when($this->conta_bancaria_id, fn ($q) => $q->where('conta_bancaria_id', $this->conta_bancaria_id))
                ->get()->each(fn ($r) => $resultado->push(['data' => $r->data_abertura->format('Y-m-d'), 'tipo' => 'saida', 'descricao' => '🔒 Reclamação bloqueada — Pedido ' . ($r->numero_pedido ?? "#{$r->id}"), 'categoria' => 'Reclamação ML', 'banco' => $r->contaBancaria?->nome ?? '-', 'valor' => (float) $r->valor]));
        }

        return $resultado;
    }
}
