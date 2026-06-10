<?php

namespace App\Filament\Pages;

use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class RastreioVendasProduto extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?string $navigationLabel = 'Rastreio Vendas por SKU';
    protected static ?string $title = 'Rastreio de Vendas por Produto';
    protected static string $view = 'filament.pages.rastreio-vendas-produto';

    public string $sku = '';
    public string $periodo = 'este_mes';
    public string $data_inicio = '';
    public string $data_fim = '';
    public array $resultados = [];
    public bool $consultaRealizada = false;
    public int $totalUnidades = 0;
    public string $nomeProduto = '';

    public function consultar(): void
    {
        $this->resultados = [];
        $this->totalUnidades = 0;
        $this->nomeProduto = '';

        if (empty($this->sku)) {
            $this->consultaRealizada = false;
            return;
        }

        $skuBusca = trim($this->sku);

        // Buscar nome do produto
        $produto = \App\Models\ProdutoEstoque::where('sku', $skuBusca)->first();
        $this->nomeProduto = $produto->nome ?? '';

        // Buscar kits que contêm este SKU como componente
        $kitsComEste = [];
        if ($produto) {
            $kitsComEste = DB::table('produto_estoque_componentes')
                ->join('produtos_estoque', 'produtos_estoque.id', '=', 'produto_estoque_componentes.kit_id')
                ->where('produto_estoque_componentes.componente_id', $produto->id)
                ->select('produtos_estoque.sku', 'produto_estoque_componentes.quantidade')
                ->get()
                ->keyBy('sku');
        }

        // Filtrar vendas por período
        $query = Venda::query()
            ->where(fn ($q) => $q->where('cancelada', false)->orWhereNull('cancelada'));

        $query = match ($this->periodo) {
            'hoje' => $query->whereDate('data_venda', today()),
            'esta_semana' => $query->whereBetween('data_venda', [now()->startOfWeek(), now()->endOfWeek()]),
            'este_mes' => $query->whereBetween('data_venda', [now()->startOfMonth(), now()->endOfMonth()]),
            'mes_passado' => $query->whereBetween('data_venda', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
            'customizado' => $query
                ->when($this->data_inicio, fn ($q) => $q->whereDate('data_venda', '>=', $this->data_inicio))
                ->when($this->data_fim, fn ($q) => $q->whereDate('data_venda', '<=', $this->data_fim)),
            default => $query,
        };

        $vendas = $query->whereNotNull('bling_id')->orderBy('data_venda', 'desc')->get();
        $blingIds = $vendas->pluck('bling_id')->toArray();

        if (empty($blingIds)) {
            $this->consultaRealizada = true;
            return;
        }

        $stagings = PedidoBlingStaging::whereIn('bling_id', $blingIds)->get()->keyBy('bling_id');

        $resultados = [];

        foreach ($vendas as $venda) {
            $staging = $stagings[$venda->bling_id] ?? null;
            if (!$staging) continue;

            foreach ($staging->itens ?? [] as $item) {
                $itemSku = $item['codigo'] ?? '';
                if (!$itemSku) continue;

                // Venda direta do SKU
                if ($itemSku === $skuBusca) {
                    $qtd = (int) ($item['quantidade'] ?? 1);
                    $resultados[] = [
                        'data' => $venda->data_venda,
                        'pedido' => $venda->numero_pedido_canal ?? $venda->bling_id,
                        'nfe' => $venda->numero_nota_fiscal ?? '-',
                        'canal' => $venda->canal_nome ?? '-',
                        'tipo' => 'Direta',
                        'kit_sku' => '-',
                        'qtd' => $qtd,
                    ];
                    $this->totalUnidades += $qtd;
                }

                // Venda via kit
                if ($kitsComEste->has($itemSku)) {
                    $comp = $kitsComEste[$itemSku];
                    $qtdItem = (int) ($item['quantidade'] ?? 1);
                    $qtd = $qtdItem * (int) $comp->quantidade;
                    $resultados[] = [
                        'data' => $venda->data_venda,
                        'pedido' => $venda->numero_pedido_canal ?? $venda->bling_id,
                        'nfe' => $venda->numero_nota_fiscal ?? '-',
                        'canal' => $venda->canal_nome ?? '-',
                        'tipo' => 'Via Kit',
                        'kit_sku' => $itemSku,
                        'qtd' => $qtd,
                    ];
                    $this->totalUnidades += $qtd;
                }
            }
        }

        $this->resultados = $resultados;
        $this->consultaRealizada = true;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
