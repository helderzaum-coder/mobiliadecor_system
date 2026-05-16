<?php

namespace App\Filament\Pages;

use App\Models\ContaReceber;
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

    public function mount(): void
    {
        $this->data_recebimento = now()->format('Y-m-d');
    }

    public function getResultadosBuscaProperty()
    {
        if (strlen($this->busca) < 3) return collect();

        return ContaReceber::with('venda')
            ->where('status', 'pendente')
            ->whereHas('venda', fn ($q) => $q->where('numero_pedido_canal', 'like', '%' . $this->busca . '%'))
            ->whereNotIn('id_conta_receber', $this->lote)
            ->limit(10)
            ->get();
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

    public function getLiquidoLoteProperty(): float
    {
        return $this->totalLote - $this->totalDescontos;
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
        ];

        $this->desconto_descricao = '';
        $this->desconto_valor = '';
    }

    public function removerDesconto(int $index): void
    {
        unset($this->descontos[$index]);
        $this->descontos = array_values($this->descontos);
    }

    public function adicionarAoLote(int $id): void
    {
        if (!in_array($id, $this->lote)) {
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

        // Separar por vírgula, ponto-e-vírgula, quebra de linha ou espaço
        $pedidos = preg_split('/[\s,;]+/', trim($this->busca_multipla));
        $pedidos = array_filter(array_map('trim', $pedidos));

        $adicionados = 0;
        $naoEncontrados = [];

        foreach ($pedidos as $numeroPedido) {
            $conta = ContaReceber::where('status', 'pendente')
                ->whereHas('venda', fn ($q) => $q->where('numero_pedido_canal', $numeroPedido))
                ->first();

            if ($conta && !in_array($conta->id_conta_receber, $this->lote)) {
                $this->lote[] = $conta->id_conta_receber;
                $adicionados++;
            } elseif (!$conta) {
                $naoEncontrados[] = $numeroPedido;
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
                $conta->venda->update([
                    'repasse_recebido' => true,
                    'data_recebimento' => $this->data_recebimento,
                ]);
            }
            $count++;
        }

        // Lançar descontos como contas a pagar (já pagas)
        foreach ($this->descontos as $desconto) {
            \App\Models\ContaPagar::create([
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
            ]);
        }

        $msgDescontos = !empty($this->descontos) ? " | " . count($this->descontos) . " desconto(s) lançado(s)" : '';
        Notification::make()->title("{$count} recebimento(s) confirmado(s){$msgDescontos}.")->success()->send();
        $this->lote = [];
        $this->descontos = [];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
