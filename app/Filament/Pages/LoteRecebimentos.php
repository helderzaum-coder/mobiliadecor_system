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
    public array $lote = []; // array de id_conta_receber
    public string $data_recebimento = '';

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
            ]);

            if ($conta->venda) {
                $conta->venda->update([
                    'repasse_recebido' => true,
                    'data_recebimento' => $this->data_recebimento,
                ]);
            }
            $count++;
        }

        Notification::make()->title("{$count} recebimento(s) confirmado(s).")->success()->send();
        $this->lote = [];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
