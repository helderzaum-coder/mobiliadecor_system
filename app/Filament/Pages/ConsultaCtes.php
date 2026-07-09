<?php

namespace App\Filament\Pages;

use App\Models\Cte;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ConsultaCtes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Transporte';
    protected static ?string $navigationLabel = 'CT-es Importados';
    protected static ?string $title = 'CT-es Importados';
    protected static string $view = 'filament.pages.consulta-ctes';

    public string $filtro = 'nao_utilizados';
    public string $busca = '';
    public string $transportadora = '';
    public string $periodo = '';
    public ?string $data_inicio = null;
    public ?string $data_fim = null;

    // Modal de confirmação
    public bool $modalAberto = false;
    public ?int $modalCteId = null;
    public ?array $modalVendaDados = null;
    public ?string $modalTipoPendente = null;

    // Modal conta a pagar
    public bool $modalContaPagar = false;
    public ?int $contaPagarCteId = null;
    public string $contaPagarDescricao = '';
    public ?string $contaPagarData = null;

    public function vincularManual(int $cteId): void
    {
        $cte = Cte::find($cteId);
        if (!$cte) return;

        $venda = \App\Models\Venda::where('cliente_nome', 'like', '%' . $cte->destinatario . '%')
            ->where('frete_pago', false)
            ->first();

        if (!$venda) {
            \Filament\Notifications\Notification::make()
                ->title("Nenhuma venda pendente encontrada para '{$cte->destinatario}'")
                ->warning()->send();
            return;
        }

        $venda->update([
            'valor_frete_transportadora' => $cte->valor_frete,
            'nfe_chave_acesso' => $cte->chave_nfe,
            'frete_pago' => true,
        ]);

        $cte->update([
            'utilizado' => true,
            'venda_id' => $venda->id_venda,
        ]);

        \App\Services\VendaRecalculoService::recalcularMargens($venda);

        \Filament\Notifications\Notification::make()
            ->title("CT-e {$cte->numero_cte} vinculado à venda #{$venda->numero_pedido_canal} — R$ " . number_format($cte->valor_frete, 2, ',', '.'))
            ->success()->send();
    }

    public function alterarTipo(int $cteId, string $novoTipo): void
    {
        $cte = Cte::find($cteId);
        if (!$cte) return;

        // Se não está vinculado e tipo exige pedido, abrir modal para vincular
        if (!$cte->venda_id && in_array($novoTipo, ['reentrega', 'devolucao', 'assistencia'])) {
            $cte->update(['tipo' => $novoTipo]);
            $this->modalCteId = $cte->id;
            $this->modalVendaDados = null;
            $this->modalTipoPendente = $novoTipo;
            $this->modalAberto = true;
            return;
        }

        $cte->update(['tipo' => $novoTipo]);

        // Recalcular frete da venda vinculada (só soma CT-es tipo entrega)
        if ($cte->venda_id) {
            $venda = \App\Models\Venda::find($cte->venda_id);
            if ($venda) {
                $totalFrete = Cte::where('venda_id', $venda->id_venda)
                    ->where('tipo', 'entrega')
                    ->sum('valor_frete');
                $venda->update(['valor_frete_transportadora' => round($totalFrete, 2)]);
                \App\Services\VendaRecalculoService::recalcularMargens($venda);
            }
        }

        $label = match ($novoTipo) {
            'reentrega' => 'Reentrega',
            'devolucao' => 'Devolução',
            'assistencia' => 'Assistência',
            default => 'Entrega',
        };

        \Filament\Notifications\Notification::make()
            ->title("CT-e {$cte->numero_cte} marcado como: {$label}")
            ->success()->send();
    }

    public function buscarPedidoParaVincular(int $cteId, string $numeroPedido): void
    {
        $cte = Cte::find($cteId);
        if (!$cte) return;

        $venda = \App\Models\Venda::where('numero_pedido_canal', $numeroPedido)->first();
        if (!$venda) {
            $blingId = \App\Models\PedidoBlingStaging::where('numero_pedido', $numeroPedido)->value('bling_id');
            if ($blingId) {
                $venda = \App\Models\Venda::where('bling_id', $blingId)->first();
            }
        }

        if (!$venda) {
            \Filament\Notifications\Notification::make()
                ->title("Pedido '{$numeroPedido}' não encontrado")
                ->danger()->send();
            return;
        }

        $this->modalCteId = $cte->id;
        $this->modalTipoPendente = null;
        $this->modalVendaDados = [
            'id_venda' => $venda->id_venda,
            'numero_pedido_canal' => $venda->numero_pedido_canal,
            'cliente_nome' => $venda->cliente_nome,
            'nota_fiscal' => $venda->numero_nota_fiscal ?: 'N/A',
            'canal' => $venda->canal_nome,
            'valor_total' => number_format((float) $venda->valor_total_venda, 2, ',', '.'),
            'data_venda' => $venda->data_venda?->format('d/m/Y'),
            'cte_numero' => $cte->numero_cte,
            'cte_valor' => number_format((float) $cte->valor_frete, 2, ',', '.'),
            'cte_destinatario' => $cte->destinatario,
        ];
        $this->modalAberto = true;
    }

    public function buscarPedidoModal(string $numeroPedido): void
    {
        if (!$this->modalCteId) return;
        $this->buscarPedidoParaVincular($this->modalCteId, $numeroPedido);
    }

    public function confirmarVinculacao(): void
    {
        if (!$this->modalCteId || !$this->modalVendaDados) return;

        $cte = Cte::find($this->modalCteId);
        $venda = \App\Models\Venda::find($this->modalVendaDados['id_venda']);

        if (!$cte || !$venda) {
            $this->fecharModal();
            return;
        }

        $cte->update([
            'utilizado' => true,
            'venda_id' => $venda->id_venda,
        ]);

        $totalFrete = Cte::where('venda_id', $venda->id_venda)
            ->where('tipo', 'entrega')
            ->sum('valor_frete');

        $venda->update([
            'valor_frete_transportadora' => round($totalFrete, 2),
            'nfe_chave_acesso' => $venda->nfe_chave_acesso ?: $cte->chave_nfe,
            'frete_pago' => true,
        ]);

        \App\Services\VendaRecalculoService::recalcularMargens($venda);

        \Filament\Notifications\Notification::make()
            ->title("CT-e {$cte->numero_cte} vinculado à venda #{$venda->numero_pedido_canal} — Frete: R$ " . number_format($totalFrete, 2, ',', '.'))
            ->success()->send();

        $this->fecharModal();
    }

    public function fecharModal(): void
    {
        $this->modalAberto = false;
        $this->modalCteId = null;
        $this->modalVendaDados = null;
        $this->modalTipoPendente = null;
    }

    public function abrirModalContaPagar(int $cteId): void
    {
        $cte = Cte::find($cteId);
        if (!$cte) return;

        $this->contaPagarCteId = $cteId;
        $this->contaPagarDescricao = "Frete Devolução - CT-e {$cte->numero_cte} - {$cte->destinatario}";
        $this->contaPagarData = $cte->data_emissao?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->modalContaPagar = true;
    }

    public function confirmarContaPagar(): void
    {
        $cte = Cte::find($this->contaPagarCteId);
        if (!$cte) {
            $this->modalContaPagar = false;
            return;
        }

        $categoria = \App\Models\CategoriaFinanceira::firstOrCreate(
            ['nome' => 'Frete Assistência/Devolução'],
            ['tipo' => 'saida', 'ativo' => true, 'sistema' => false]
        );

        \App\Models\ContaPagar::create([
            'descricao' => $this->contaPagarDescricao,
            'valor_parcela' => $cte->valor_frete,
            'data_vencimento' => $this->contaPagarData,
            'data_lancamento' => $this->contaPagarData,
            'status' => 'pendente',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'forma_pagamento' => 'boleto',
            'lancamento_manual' => true,
            'categoria_id' => $categoria->id,
            'observacoes' => "CT-e #{$cte->numero_cte} | Chave: {$cte->chave_cte}",
        ]);

        $cte->update(['utilizado' => true]);

        $this->modalContaPagar = false;
        $this->contaPagarCteId = null;

        \Filament\Notifications\Notification::make()
            ->title("Conta a pagar criada: R$ " . number_format($cte->valor_frete, 2, ',', '.'))
            ->success()->send();
    }

    public function fecharModalContaPagar(): void
    {
        $this->modalContaPagar = false;
        $this->contaPagarCteId = null;
    }

    public function getCtesProperty()
    {
        $query = $this->buildCtesQuery();
        return $query->limit(300)->get();
    }

    public function getTotaisProperty(): array
    {
        $base = Cte::where('transportadora', 'not like', '%TIKTOK%');
        return [
            'total' => (clone $base)->count(),
            'utilizados' => (clone $base)->where('utilizado', true)->count(),
            'nao_utilizados' => (clone $base)->where('utilizado', false)->count(),
            'valor_nao_utilizado' => (clone $base)->where('utilizado', false)->sum('valor_frete'),
        ];
    }

    public function getTransportadorasProperty(): array
    {
        return Cte::distinct()->whereNotNull('transportadora')
            ->where('transportadora', '!=', '')
            ->where('transportadora', 'not like', '%TIKTOK%')
            ->orderBy('transportadora')
            ->pluck('transportadora')
            ->toArray();
    }

    private function buildCtesQuery()
    {
        $query = Cte::where('transportadora', 'not like', '%TIKTOK%')
            ->orderByRaw('COALESCE(data_emissao, created_at) DESC');

        if ($this->filtro === 'nao_utilizados') {
            $query->where('utilizado', false);
        } elseif ($this->filtro === 'utilizados') {
            $query->where('utilizado', true);
        }

        if ($this->busca) {
            $busca = $this->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero_cte', 'like', "%{$busca}%")
                  ->orWhere('chave_nfe', 'like', "%{$busca}%")
                  ->orWhere('chave_cte', 'like', "%{$busca}%")
                  ->orWhere('numero_nfe', 'like', "%{$busca}%")
                  ->orWhere('destinatario', 'like', "%{$busca}%")
                  ->orWhere('dest_documento', 'like', "%{$busca}%")
                  ->orWhere('remetente', 'like', "%{$busca}%")
                  ->orWhere('rem_documento', 'like', "%{$busca}%");
            });
        }

        if ($this->transportadora) {
            $query->where('transportadora', $this->transportadora);
        }

        if ($this->periodo) {
            $this->aplicarFiltroPeriodo($query);
        }

        return $query;
    }

    private function aplicarFiltroPeriodo($query): void
    {
        $datas = match ($this->periodo) {
            'hoje' => [today()->toDateString(), today()->toDateString()],
            'esta_semana' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'este_mes' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'mes_passado' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'customizado' => [$this->data_inicio, $this->data_fim],
            default => null,
        };

        if (!$datas) return;

        $query->where(function ($q) use ($datas) {
            $q->where(function ($sub) use ($datas) {
                $sub->whereNotNull('data_emissao');
                if ($datas[0]) $sub->where('data_emissao', '>=', $datas[0]);
                if ($datas[1]) $sub->where('data_emissao', '<=', $datas[1]);
            });
            $q->orWhereNull('data_emissao');
        });
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
