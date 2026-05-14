<?php

namespace App\Filament\Pages;

use App\Models\Cte;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ConsultaCtes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'CT-es Importados';
    protected static ?string $title = 'CT-es Importados';
    protected static string $view = 'filament.pages.consulta-ctes';

    public string $filtro = 'nao_utilizados';
    public string $busca = '';
    public string $transportadora = '';
    public string $periodo = '';
    public ?string $data_inicio = null;
    public ?string $data_fim = null;

    public function vincularManual(int $cteId): void
    {
        $cte = Cte::find($cteId);
        if (!$cte) return;

        // Buscar venda pelo destinatário (nome do cliente)
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

    public function vincularPorPedido(int $cteId, string $numeroPedido): void
    {
        $cte = Cte::find($cteId);
        if (!$cte) return;

        $venda = \App\Models\Venda::where('numero_pedido_canal', $numeroPedido)->first();
        if (!$venda) {
            \Filament\Notifications\Notification::make()
                ->title("Pedido '{$numeroPedido}' não encontrado")
                ->danger()->send();
            return;
        }

        // Somar com frete existente se já tem outro CT-e
        $freteAtual = $venda->frete_pago ? (float) $venda->valor_frete_transportadora : 0;
        $novoFrete = $freteAtual + (float) $cte->valor_frete;

        $venda->update([
            'valor_frete_transportadora' => round($novoFrete, 2),
            'nfe_chave_acesso' => $venda->nfe_chave_acesso ?: $cte->chave_nfe,
            'frete_pago' => true,
        ]);

        $cte->update([
            'utilizado' => true,
            'venda_id' => $venda->id_venda,
        ]);

        \App\Services\VendaRecalculoService::recalcularMargens($venda);

        \Filament\Notifications\Notification::make()
            ->title("CT-e {$cte->numero_cte} vinculado à venda #{$numeroPedido} — Frete total: R$ " . number_format($novoFrete, 2, ',', '.'))
            ->success()->send();
    }

    public function getCtesProperty()
    {
        $query = $this->buildCtesQuery();
        return $query->limit(300)->get();
    }

    public function getTotaisProperty(): array
    {
        return [
            'total' => Cte::count(),
            'utilizados' => Cte::where('utilizado', true)->count(),
            'nao_utilizados' => Cte::where('utilizado', false)->count(),
            'valor_nao_utilizado' => Cte::where('utilizado', false)->sum('valor_frete'),
        ];
    }

    public function getTransportadorasProperty(): array
    {
        return Cte::distinct()->whereNotNull('transportadora')
            ->where('transportadora', '!=', '')
            ->orderBy('transportadora')
            ->pluck('transportadora')
            ->toArray();
    }

    private function buildCtesQuery()
    {
        $query = Cte::orderByRaw('COALESCE(data_emissao, created_at) DESC');

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
                  ->orWhere('destinatario', 'like', "%{$busca}%")
                  ->orWhere('remetente', 'like', "%{$busca}%");
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
            // CT-es com data_emissao no período
            $q->where(function ($sub) use ($datas) {
                $sub->whereNotNull('data_emissao');
                if ($datas[0]) $sub->where('data_emissao', '>=', $datas[0]);
                if ($datas[1]) $sub->where('data_emissao', '<=', $datas[1]);
            });
            // OU CT-es sem data_emissao (sempre mostrar, pois não temos a data real)
            $q->orWhereNull('data_emissao');
        });
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
