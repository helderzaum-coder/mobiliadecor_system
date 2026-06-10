<?php

namespace App\Filament\Pages;

use App\Models\PedidoBlingStaging;
use App\Models\ProdutoEstoque;
use App\Models\Venda;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class RelatorioUnidadesVendidas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?string $navigationLabel = 'Unidades Vendidas';
    protected static ?string $title = 'Relatório de Unidades Vendidas';
    protected static string $view = 'filament.pages.relatorio-unidades-vendidas';

    public string $periodo = 'este_mes';
    public string $data_inicio = '';
    public string $data_fim = '';
    public string $busca = '';
    public array $resultados = [];
    public bool $consultaRealizada = false;
    public int $totalUnidades = 0;

    public function consultar(): void
    {
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

        $blingIds = $query->whereNotNull('bling_id')->pluck('bling_id')->toArray();

        if (empty($blingIds)) {
            $this->resultados = [];
            $this->totalUnidades = 0;
            $this->consultaRealizada = true;
            return;
        }

        // Buscar itens dos stagings
        $stagings = PedidoBlingStaging::whereIn('bling_id', $blingIds)->get();

        // Carregar mapa de kits: kit_id => [{componente_id, sku, quantidade}]
        $kitsMap = DB::table('produto_estoque_componentes')
            ->join('produtos_estoque as comp', 'comp.id', '=', 'produto_estoque_componentes.componente_id')
            ->join('produtos_estoque as kit', 'kit.id', '=', 'produto_estoque_componentes.kit_id')
            ->select('kit.sku as kit_sku', 'comp.sku as comp_sku', 'comp.nome as comp_nome', 'produto_estoque_componentes.quantidade')
            ->get()
            ->groupBy('kit_sku');

        // Produtos simples (para saber nome)
        $produtosMap = ProdutoEstoque::where('ativo', true)->pluck('nome', 'sku')->toArray();

        // Contagem por SKU simples
        $contagem = [];

        foreach ($stagings as $staging) {
            foreach ($staging->itens ?? [] as $item) {
                $sku = $item['codigo'] ?? '';
                $qtd = (int) ($item['quantidade'] ?? 1);

                if (!$sku) continue;

                if ($kitsMap->has($sku)) {
                    // É kit: explodir nos componentes
                    foreach ($kitsMap[$sku] as $comp) {
                        $compSku = $comp->comp_sku;
                        $compQtd = $qtd * (int) $comp->quantidade;
                        if (!isset($contagem[$compSku])) {
                            $contagem[$compSku] = ['sku' => $compSku, 'nome' => $comp->comp_nome, 'qtd_direta' => 0, 'qtd_kit' => 0];
                        }
                        $contagem[$compSku]['qtd_kit'] += $compQtd;
                    }
                } else {
                    // Produto simples vendido diretamente
                    if (!isset($contagem[$sku])) {
                        $contagem[$sku] = ['sku' => $sku, 'nome' => $produtosMap[$sku] ?? $item['descricao'] ?? '', 'qtd_direta' => 0, 'qtd_kit' => 0];
                    }
                    $contagem[$sku]['qtd_direta'] += $qtd;
                }
            }
        }

        // Calcular total e ordenar
        $resultados = collect($contagem)->map(function ($r) {
            $r['total'] = $r['qtd_direta'] + $r['qtd_kit'];
            return $r;
        });

        // Filtro de busca
        if (!empty($this->busca)) {
            $busca = mb_strtolower($this->busca);
            $resultados = $resultados->filter(fn ($r) =>
                str_contains(mb_strtolower($r['sku']), $busca) ||
                str_contains(mb_strtolower($r['nome']), $busca)
            );
        }

        $resultados = $resultados->sortByDesc('total')->values()->toArray();

        $this->resultados = $resultados;
        $this->totalUnidades = array_sum(array_column($resultados, 'total'));
        $this->consultaRealizada = true;
    }

    public function exportarCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Nome', 'Venda Direta', 'Via Kit', 'Total']);
            foreach ($this->resultados as $r) {
                fputcsv($handle, [$r['sku'], $r['nome'], $r['qtd_direta'], $r['qtd_kit'], $r['total']]);
            }
            fclose($handle);
        }, 'unidades_vendidas_' . now()->format('Y-m-d') . '.csv');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
