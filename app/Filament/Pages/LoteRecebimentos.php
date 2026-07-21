<?php

namespace App\Filament\Pages;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\LoteRecebimento;
use App\Models\Venda;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class LoteRecebimentos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Lote Recebimentos';
    protected static ?string $title = 'Lote de Recebimentos';
    protected static string $view = 'filament.pages.lote-recebimentos';
    protected static ?int $navigationSort = 3;

    public string $busca = '';
    public string $busca_multipla = '';
    public array $lote = [];
    public string $data_recebimento = '';
    public string $identificador_lote = '';
    public ?string $conta_bancaria_id = null;
    public array $descontos = [];
    public string $desconto_descricao = '';
    public string $desconto_valor = '';
    public string $busca_reembolso = '';
    public array $entradas_avulsas = [];
    public string $entrada_descricao = '';
    public string $entrada_valor = '';
    public string $entrada_canal = '';

    public function mount(): void
    {
        $this->data_recebimento = now()->format('Y-m-d');
    }

    public function getResultadosBuscaProperty()
    {
        if (strlen($this->busca) < 3) return collect();

        $contas = ContaReceber::with('venda.canal')
            ->where('status', 'pendente')
            ->whereNull('fatura_recebimento_id')
            ->where(function ($q) {
                $q->whereHas('venda', fn ($q2) => $q2->where('numero_pedido_canal', 'like', '%' . $this->busca . '%'))
                  ->orWhere('observacoes', 'like', '%' . $this->busca . '%');
            })
            ->whereNotIn('id_conta_receber', $this->lote)
            ->orderBy('numero_parcela')
            ->limit(20)
            ->get();

        // Recalcular valor em tempo real apenas se não foi editado manualmente
        foreach ($contas as $conta) {
            if ($conta->venda && !$conta->lancamento_manual && !str_contains($conta->forma_pagamento ?? '', 'Subsídio')) {
                if ($conta->updated_at->diffInSeconds($conta->created_at) > 5) continue;
                $repasse = $this->calcularRepasse($conta->venda);
                if ($repasse !== null && $conta->total_parcelas > 1) {
                    $conta->valor_parcela = round($repasse / $conta->total_parcelas, 2);
                } elseif ($repasse !== null) {
                    $conta->valor_parcela = round($repasse, 2);
                }
            }
        }

        return $contas;
    }

    public function getLoteItensProperty()
    {
        if (empty($this->lote)) return collect();
        return ContaReceber::with('venda.canal')->whereIn('id_conta_receber', $this->lote)->get();
    }

    public function getTotalLoteProperty(): float
    {
        return $this->loteItens->sum('valor_parcela');
    }

    public function getTotalDescontosProperty(): float
    {
        return collect($this->descontos)->sum('valor');
    }

    public function getTotalEntradasAvulsasProperty(): float
    {
        return collect($this->entradas_avulsas)->sum('valor');
    }

    public function getLiquidoLoteProperty(): float
    {
        return $this->totalLote + $this->totalEntradasAvulsas - $this->totalDescontos;
    }

    public function adicionarDesconto(): void
    {
        if (empty(trim($this->desconto_descricao)) || (float) $this->desconto_valor <= 0) {
            Notification::make()->title('Informe descrição e valor do desconto.')->warning()->send();
            return;
        }

        $this->descontos[] = [
            'descricao' => trim($this->desconto_descricao),
            'valor' => round((float) $this->desconto_valor, 2),
            'conta_pagar_id' => null,
        ];

        $this->desconto_descricao = '';
        $this->desconto_valor = '';
    }

    public function buscarReembolso(): void
    {
        if (empty(trim($this->busca_reembolso))) return;

        $contaPagar = ContaPagar::where('status', 'pendente')
            ->whereIn('forma_pagamento', ['Estorno', 'Reembolso'])
            ->where('observacoes', 'like', '%' . trim($this->busca_reembolso) . '%')
            ->first();

        if (!$contaPagar) {
            Notification::make()->title('Nenhum reembolso/estorno pendente encontrado para este pedido.')->warning()->send();
            return;
        }

        // Verificar se já foi adicionado
        foreach ($this->descontos as $d) {
            if (($d['conta_pagar_id'] ?? null) == $contaPagar->id_conta_pagar) {
                Notification::make()->title('Este reembolso já está no lote.')->warning()->send();
                return;
            }
        }

        $this->descontos[] = [
            'descricao' => "🔄 {$contaPagar->forma_pagamento} - Pedido #{$this->busca_reembolso}",
            'valor' => round((float) $contaPagar->valor_parcela, 2),
            'conta_pagar_id' => $contaPagar->id_conta_pagar,
        ];

        Notification::make()->title("Reembolso de R$ " . number_format($contaPagar->valor_parcela, 2, ',', '.') . " adicionado.")->success()->send();
        $this->busca_reembolso = '';
    }

    public function removerDesconto(int $index): void
    {
        unset($this->descontos[$index]);
        $this->descontos = array_values($this->descontos);
    }

    public function adicionarEntradaAvulsa(): void
    {
        if (empty(trim($this->entrada_descricao)) || (float) $this->entrada_valor <= 0) {
            Notification::make()->title('Informe descrição e valor da entrada.')->warning()->send();
            return;
        }

        $this->entradas_avulsas[] = [
            'descricao' => trim($this->entrada_descricao),
            'valor' => round((float) $this->entrada_valor, 2),
            'canal' => trim($this->entrada_canal) ?: 'Entrada Avulsa',
        ];

        $this->entrada_descricao = '';
        $this->entrada_valor = '';
        $this->entrada_canal = '';
    }

    public function removerEntradaAvulsa(int $index): void
    {
        unset($this->entradas_avulsas[$index]);
        $this->entradas_avulsas = array_values($this->entradas_avulsas);
    }

    public function adicionarAoLote(int $id): void
    {
        if (!in_array($id, $this->lote)) {
            $this->recalcularValorConta($id);
            $this->lote[] = $id;
        }
        $this->busca = '';
    }

    public function removerDoLote(int $id): void
    {
        $this->lote = array_values(array_filter($this->lote, fn ($i) => $i !== $id));
    }

    public function limparLote(): void
    {
        $this->lote = [];
    }

    public function adicionarMultiplos(): void
    {
        if (empty(trim($this->busca_multipla))) return;

        $pedidos = preg_split('/[\s,;]+/', trim($this->busca_multipla));
        $pedidos = array_filter(array_map('trim', $pedidos));

        $adicionados = 0;
        $naoEncontrados = [];

        foreach ($pedidos as $numeroPedido) {
            // Buscar todas as contas a receber pendentes do pedido
            $contas = ContaReceber::where('status', 'pendente')
                ->whereNull('fatura_recebimento_id')
                ->where(function ($q) use ($numeroPedido) {
                    $q->whereHas('venda', fn ($q2) => $q2->where('numero_pedido_canal', $numeroPedido))
                      ->orWhere('observacoes', 'like', '%' . $numeroPedido . '%');
                })
                ->orderBy('numero_parcela')
                ->get();

            // Se não tem conta, tentar criar a partir da venda
            if ($contas->isEmpty()) {
                $venda = \App\Models\Venda::where('numero_pedido_canal', $numeroPedido)->first();
                if ($venda) {
                    $conta = $this->criarContaReceber($venda);
                    if ($conta) $contas = collect([$conta]);
                }
            }

            if ($contas->isEmpty()) {
                $naoEncontrados[] = $numeroPedido;
                continue;
            }

            foreach ($contas as $conta) {
                if (!in_array($conta->id_conta_receber, $this->lote)) {
                    $this->recalcularValorConta($conta->id_conta_receber);
                    $this->lote[] = $conta->id_conta_receber;
                    $adicionados++;
                }
            }
        }

        $msg = "{$adicionados} adicionado(s) ao lote.";
        if (!empty($naoEncontrados)) {
            $msg .= " | Não encontrados: " . implode(', ', array_slice($naoEncontrados, 0, 5));
            if (count($naoEncontrados) > 5) $msg .= '...';
        }

        Notification::make()->title($msg)->{$adicionados > 0 ? 'success' : 'warning'}()->send();
        $this->busca_multipla = '';
    }

    /**
     * Recalcula o valor_parcela de uma conta a receber existente usando a mesma lógica da dashboard.
     */
    private function recalcularValorConta(int $contaId): void
    {
        $conta = ContaReceber::with('venda.canal')->find($contaId);
        if (!$conta || !$conta->venda || $conta->lancamento_manual) return;
        if (str_contains($conta->forma_pagamento ?? '', 'Subsídio')) return;

        // Se foi editado manualmente após criação, não sobrescrever
        if ($conta->updated_at->diffInSeconds($conta->created_at) > 5) return;

        $repasse = $this->calcularRepasse($conta->venda);
        if ($repasse === null) return;

        $totalParcelas = $conta->total_parcelas > 1 ? $conta->total_parcelas : 1;
        $valorParcela = round($repasse / $totalParcelas, 2);

        if ($valorParcela !== round((float) $conta->valor_parcela, 2)) {
            $conta->update(['valor_parcela' => $valorParcela]);
        }
    }

    /**
     * Calcula o repasse de uma venda (mesma lógica da dashboard).
     */
    private function calcularRepasse(\App\Models\Venda $venda): ?float
    {
        $canal = $venda->canal;
        $isMagalu = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'magalu');
        $isShopee = $canal && str_contains(strtolower($canal->nome_canal ?? ''), 'shopee');
        $isML = $canal && (str_contains(strtolower($canal->nome_canal ?? ''), 'mercado') || str_starts_with($venda->numero_pedido_canal ?? '', '2000'));

        if ($isMagalu) {
            $repasse = (float) $venda->valor_total_venda - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0);
        } elseif ($isML && (float) ($venda->ml_sale_fee ?? 0) > 0) {
            $mlSaleFee = (float) $venda->ml_sale_fee;
            $mlFreteCusto = (float) ($venda->ml_frete_custo ?? 0);
            $mlFreteReceita = (float) ($venda->ml_frete_receita ?? 0);
            $mlRebate = (float) ($venda->ml_valor_rebate ?? 0);
            if (in_array($venda->ml_tipo_frete, ['ME2', 'FULL'])) {
                $freteLiquido = $mlFreteCusto > 0 ? $mlFreteCusto - $mlFreteReceita : 0;
                $repasse = (float) $venda->total_produtos - $mlSaleFee - $freteLiquido - (float) ($venda->comissao_afiliado ?? 0);
            } else {
                $repasse = (float) $venda->total_produtos + $mlFreteReceita - $mlSaleFee + $mlRebate - (float) ($venda->comissao_afiliado ?? 0);
            }
        } else {
            $cupomShopeeR = $isShopee ? (float) ($venda->cupom_shopee ?? 0) : 0;
            $cupomPlataformaR = $isShopee ? (float) ($venda->cupom_plataforma ?? 0) : 0;
            $repasse = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao - (float) ($venda->comissao_afiliado ?? 0) - $cupomShopeeR - $cupomPlataformaR;
        }

        $subsidioPix = (float) ($venda->subsidio_pix ?? 0);
        if ($subsidioPix > 0 && !$isShopee && !$isMagalu) {
            $repasse += $subsidioPix;
        }

        return $repasse;
    }

    /**
     * Cria conta a receber para uma venda que não tem (força criação).
     */
    private function criarContaReceber(\App\Models\Venda $venda): ?ContaReceber
    {
        // Verificar se já existe (qualquer status)
        $existente = ContaReceber::where('id_venda', $venda->id_venda)->first();
        if ($existente) {
            return $existente->status === 'pendente' ? $existente : null;
        }

        $canal = $venda->canal;
        $repasse = $this->calcularRepasse($venda);

        return ContaReceber::create([
            'id_venda' => $venda->id_venda,
            'valor_parcela' => round($repasse, 2),
            'data_vencimento' => $venda->data_venda,
            'status' => 'pendente',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'forma_pagamento' => $canal?->nome_canal ?? 'Marketplace',
            'observacoes' => "Repasse #{$venda->numero_pedido_canal}",
            'lancamento_manual' => false,
        ]);
    }

    public function confirmarLote(): void
    {
        if (empty($this->lote)) {
            Notification::make()->title('Lote vazio.')->warning()->send();
            return;
        }

        if (empty($this->data_recebimento)) {
            Notification::make()->title('Informe a data de recebimento.')->warning()->send();
            return;
        }

        $count = 0;
        $valorTotal = 0;

        foreach ($this->lote as $id) {
            $conta = ContaReceber::find($id);
            if (!$conta || $conta->status !== 'pendente') continue;

            $conta->update([
                'status' => 'recebido',
                'data_recebimento' => $this->data_recebimento,
                'observacoes' => $this->identificador_lote ?: $conta->observacoes,
                'conta_bancaria_id' => $this->conta_bancaria_id ?: null,
            ]);

            if ($conta->venda) {
                $pendentes = ContaReceber::where('id_venda', $conta->id_venda)
                    ->where('status', 'pendente')->count();
                if ($pendentes === 0) {
                    $conta->venda->update([
                        'repasse_recebido' => true,
                        'data_recebimento' => $this->data_recebimento,
                    ]);
                }
            }
            $valorTotal += (float) $conta->valor_parcela;
            $count++;
        }

        $descricaoLote = LoteRecebimento::gerarDescricao(
            $this->conta_bancaria_id ? optional(\App\Models\ContaBancaria::find($this->conta_bancaria_id))->nome : null,
            $this->data_recebimento,
            $this->identificador_lote ?: null,
        );

        // Criar lote
        $lote = LoteRecebimento::create([
            'data_recebimento' => $this->data_recebimento,
            'descricao' => $descricaoLote,
            'valor_total' => round($valorTotal - collect($this->descontos)->sum('valor'), 2),
            'quantidade_contas' => $count,
        ]);

        // Vincular contas ao lote
        ContaReceber::whereIn('id_conta_receber', $this->lote)
            ->where('status', 'recebido')
            ->update(['lote_recebimento_id' => $lote->id]);

        // Lançar descontos como contas a pagar (já pagas) vinculadas ao lote
        foreach ($this->descontos as $desconto) {
            if (!empty($desconto['conta_pagar_id'])) {
                // Reembolso existente: dar baixa e vincular ao lote
                ContaPagar::where('id_conta_pagar', $desconto['conta_pagar_id'])->update([
                    'status' => 'pago',
                    'data_pagamento' => $this->data_recebimento,
                    'conta_bancaria_id' => $this->conta_bancaria_id ?: null,
                    'lote_recebimento_id' => $lote->id,
                ]);
            } else {
                ContaPagar::create([
                    'valor_parcela' => $desconto['valor'],
                    'data_vencimento' => $this->data_recebimento,
                    'data_pagamento' => $this->data_recebimento,
                    'status' => 'pago',
                    'numero_parcela' => 1,
                    'total_parcelas' => 1,
                    'forma_pagamento' => 'Desconto Canal',
                    'observacoes' => $desconto['descricao'] . ($this->identificador_lote ? " | {$this->identificador_lote}" : ''),
                    'lancamento_manual' => true,
                    'conta_bancaria_id' => $this->conta_bancaria_id ?: null,
                    'lote_recebimento_id' => $lote->id,
                ]);
            }
        }

        // Lançar entradas avulsas como contas a receber (já recebidas) vinculadas ao lote
        foreach ($this->entradas_avulsas as $entrada) {
            ContaReceber::create([
                'valor_parcela' => $entrada['valor'],
                'data_vencimento' => $this->data_recebimento,
                'data_recebimento' => $this->data_recebimento,
                'status' => 'recebido',
                'numero_parcela' => 1,
                'total_parcelas' => 1,
                'forma_pagamento' => $entrada['canal'] ?? 'Entrada Avulsa',
                'observacoes' => $entrada['descricao'] . ($this->identificador_lote ? " | {$this->identificador_lote}" : ''),
                'lancamento_manual' => true,
                'conta_bancaria_id' => $this->conta_bancaria_id ?: null,
                'lote_recebimento_id' => $lote->id,
            ]);
        }

        $msgDescontos = !empty($this->descontos) ? " | " . count($this->descontos) . " desconto(s)" : '';
        $msgEntradas = !empty($this->entradas_avulsas) ? " | " . count($this->entradas_avulsas) . " entrada(s) avulsa(s)" : '';
        Notification::make()->title("{$count} recebimento(s) confirmado(s){$msgDescontos}{$msgEntradas}. Lote #{$lote->id}")->success()->send();
        $this->lote = [];
        $this->descontos = [];
        $this->entradas_avulsas = [];
        $this->identificador_lote = '';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
