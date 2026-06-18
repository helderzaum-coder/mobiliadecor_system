<?php

namespace App\Filament\Pages;

use App\Models\PedidoBlingStaging;
use App\Models\ProdutoEstoque;
use App\Services\Bling\BlingClient;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class ListagemPedidos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?string $navigationLabel = 'Listagem de Pedidos';
    protected static ?string $title = 'Listagem de Pedidos';
    protected static string $view = 'filament.pages.listagem-pedidos';

    public string $periodo = 'este_mes';
    public string $data_inicio = '';
    public string $data_fim = '';
    public string $filtro_canal = '';
    public string $filtro_conta = '';
    public array $filtro_situacao = [];
    public array $resultados = [];
    public bool $consultaRealizada = false;
    public array $situacoesDisponiveis = [];
    public bool $atualizando = false;

    public function atualizarSituacoes(): void
    {
        // Limpa cache de situações de todos os pedidos do resultado atual
        $query = PedidoBlingStaging::query()->where('status', '!=', 'rejeitado');

        $query = match ($this->periodo) {
            'hoje' => $query->whereDate('data_pedido', today()),
            'esta_semana' => $query->whereBetween('data_pedido', [now()->startOfWeek(), now()->endOfWeek()]),
            'este_mes' => $query->whereBetween('data_pedido', [now()->startOfMonth(), now()->endOfMonth()]),
            'mes_passado' => $query->whereBetween('data_pedido', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
            'customizado' => $query
                ->when($this->data_inicio, fn ($q) => $q->whereDate('data_pedido', '>=', $this->data_inicio))
                ->when($this->data_fim, fn ($q) => $q->whereDate('data_pedido', '<=', $this->data_fim)),
            default => $query,
        };

        if ($this->filtro_canal) {
            $query->where('canal', $this->filtro_canal);
        }
        if ($this->filtro_conta) {
            $query->where('bling_account', $this->filtro_conta);
        }

        $pedidos = $query->limit(150)->pluck('bling_id');

        // Limpar cache de todos
        foreach ($pedidos as $blingId) {
            Cache::store('file')->forget("bling_sit_v2_{$blingId}");
        }

        // Reconsultar com cache limpo (vai buscar tudo fresh da API)
        $this->consultar();
    }

    public function consultar(): void
    {
        // Validação: customizado sem datas não pode rodar
        if ($this->periodo === 'customizado' && empty($this->data_inicio) && empty($this->data_fim)) {
            $this->resultados = [];
            $this->consultaRealizada = true;
            return;
        }

        $query = PedidoBlingStaging::query()
            ->where('status', '!=', 'rejeitado');

        // Filtro de período
        $query = match ($this->periodo) {
            'hoje' => $query->whereDate('data_pedido', today()),
            'esta_semana' => $query->whereBetween('data_pedido', [now()->startOfWeek(), now()->endOfWeek()]),
            'este_mes' => $query->whereBetween('data_pedido', [now()->startOfMonth(), now()->endOfMonth()]),
            'mes_passado' => $query->whereBetween('data_pedido', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
            'customizado' => $query
                ->when($this->data_inicio, fn ($q) => $q->whereDate('data_pedido', '>=', $this->data_inicio))
                ->when($this->data_fim, fn ($q) => $q->whereDate('data_pedido', '<=', $this->data_fim)),
            default => $query,
        };

        if ($this->filtro_canal) {
            $query->where('canal', $this->filtro_canal);
        }
        if ($this->filtro_conta) {
            $query->where('bling_account', $this->filtro_conta);
        }

        $pedidos = $query->orderBy('data_pedido', 'desc')->limit(150)->get();

        // Buscar mapa de nomes de situações (para traduzir IDs)
        $situacoesMapPrimary = $this->getSituacoesMap('primary');
        $situacoesMapSecondary = $this->getSituacoesMap('secondary');

        // Buscar situação ATUAL de cada pedido na API
        $situacoesAtuais = $this->buscarSituacoesAtuais($pedidos, $situacoesMapPrimary, $situacoesMapSecondary);

        // Coletar situações disponíveis para o filtro
        $this->situacoesDisponiveis = array_unique(array_values($situacoesAtuais));
        sort($this->situacoesDisponiveis);

        // Filtrar por situação Bling (pós-API)
        if (!empty($this->filtro_situacao)) {
            $pedidos = $pedidos->filter(fn ($p) => in_array($situacoesAtuais[$p->bling_id] ?? '', $this->filtro_situacao));
        }

        $this->resultados = $pedidos->flatMap(function ($pedido) use ($situacoesAtuais) {
            $itens = $pedido->itens ?? [];
            $situacao = $situacoesAtuais[$pedido->bling_id] ?? '—';
            $cnpj = $pedido->bling_account === 'primary' ? 'Mobilia Decor' : 'HES Móveis';

            // Data liberação etiqueta ML (buffering.date do shipping)
            $liberacaoEtiqueta = '—';
            $isML = str_contains(strtolower($pedido->canal ?? ''), 'mercado');
            if ($isML) {
                $dadosOriginais = $pedido->dados_originais ?? [];
                $dataDespacho = $dadosOriginais['_data_despacho'] ?? null;

                // Tentar buscar buffering.date dos dados originais do shipping (mais preciso)
                if (!$dataDespacho) {
                    $dataDespacho = $dadosOriginais['transporte']['etiqueta']['buffering']['date'] ?? null;
                }

                if ($dataDespacho) {
                    try {
                        $liberacaoEtiqueta = \Carbon\Carbon::parse($dataDespacho)->format('d/m/Y');
                    } catch (\Exception $e) {}
                }
            }

            $base = [
                'data' => $pedido->data_pedido?->format('d/m/Y') ?? '—',
                'situacao_bling' => $situacao,
                'cnpj' => $cnpj,
                'canal' => $pedido->canal ?? '—',
                'pedido_bling' => $pedido->numero_pedido,
                'pedido_canal' => $pedido->numero_loja ?? '—',
                'cliente' => $pedido->cliente_nome ?? '—',
                'cidade_uf' => trim(($pedido->dest_cidade ?? '') . '/' . ($pedido->dest_uf ?? ''), '/'),
                'liberacao_etiqueta' => $liberacaoEtiqueta,
                'is_ml' => $isML,
            ];

            if (empty($itens)) {
                return [array_merge($base, ['produto' => '—', 'quantidade' => '—'])];
            }

            return collect($itens)->map(fn ($item) => array_merge($base, [
                'produto' => ($item['codigo'] ?? '') . ' - ' . ($this->getNomeProduto($item)),
                'quantidade' => $item['quantidade'] ?? 1,
            ]))->toArray();
        })->toArray();

        $this->consultaRealizada = true;
    }

    public function exportarCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Data', 'Status Bling', 'CNPJ', 'Canal', 'Produto', 'Qtd', 'Pedido Bling', 'Pedido Canal', 'Cliente', 'Cidade/UF', 'Liberação Etiqueta'], ';');
            foreach ($this->resultados as $r) {
                fputcsv($handle, [
                    $r['data'], $r['situacao_bling'], $r['cnpj'], $r['canal'],
                    $r['produto'], $r['quantidade'], $r['pedido_bling'],
                    $r['pedido_canal'], $r['cliente'], $r['cidade_uf'], $r['liberacao_etiqueta'],
                ], ';');
            }
            fclose($handle);
        }, 'listagem_pedidos_' . now()->format('Y-m-d') . '.csv');
    }

    /**
     * Busca mapa de situações do Bling (1 chamada por conta, cache 1h).
     * Retorna [situacao_id => nome].
     */
    private function getSituacoesMap(string $account): array
    {
        $client = new BlingClient($account);

        $modulos = $client->get('/situacoes/modulos');
        $moduloVendasId = null;

        if ($modulos['success']) {
            foreach ($modulos['body']['data'] ?? [] as $mod) {
                if ($mod['nome'] === 'Vendas') {
                    $moduloVendasId = $mod['id'];
                    break;
                }
            }
        }

        if (!$moduloVendasId) {
            return [];
        }

        $response = $client->getSituacoes($moduloVendasId);

        if (!$response['success'] || empty($response['body']['data'])) {
            return [];
        }

        $map = [];
        foreach ($response['body']['data'] as $sit) {
            $map[$sit['id']] = $sit['nome'] ?? ('ID ' . $sit['id']);
        }
        return $map;
    }

    /**
     * Busca situação ATUAL de cada pedido via API do Bling.
     * Cache de 10 minutos por bling_id. Limite de 50 pedidos sem cache.
     */
    private function buscarSituacoesAtuais($pedidos, array $mapPrimary, array $mapSecondary): array
    {
        $resultado = [];
        $pedidosUnicos = $pedidos->unique('bling_id');
        $clients = [];
        $chamadas = 0;

        foreach ($pedidosUnicos as $pedido) {
            $cacheKey = "bling_sit_v2_{$pedido->bling_id}";
            $cached = Cache::store('file')->get($cacheKey);

            if ($cached) {
                $resultado[$pedido->bling_id] = $cached;
                continue;
            }

            // Limitar chamadas à API para evitar timeout
            if ($chamadas >= 150) {
                $sitMap = $pedido->bling_account === 'primary' ? $mapPrimary : $mapSecondary;
                $resultado[$pedido->bling_id] = $sitMap[$pedido->situacao_id] ?? ('ID ' . ($pedido->situacao_id ?? '—'));
                continue;
            }

            if (!isset($clients[$pedido->bling_account])) {
                $clients[$pedido->bling_account] = new BlingClient($pedido->bling_account);
            }

            $response = $clients[$pedido->bling_account]->getPedido((int) $pedido->bling_id);

            if ($response['success']) {
                $sitId = $response['body']['data']['situacao']['id'] ?? null;
                $sitMap = $pedido->bling_account === 'primary' ? $mapPrimary : $mapSecondary;
                $nome = $sitMap[$sitId] ?? ('ID ' . $sitId);
                Cache::store('file')->put($cacheKey, $nome, 600);
                $resultado[$pedido->bling_id] = $nome;
            } elseif (($response['http_code'] ?? 0) === 404) {
                // Pedido não existe mais no Bling — marcar como rejeitado
                $pedido->update(['status' => 'rejeitado']);
                $resultado[$pedido->bling_id] = 'Removido do Bling';
            } else {
                $sitMap = $pedido->bling_account === 'primary' ? $mapPrimary : $mapSecondary;
                $resultado[$pedido->bling_id] = $sitMap[$pedido->situacao_id] ?? ('ID ' . ($pedido->situacao_id ?? '—'));
            }

            $chamadas++;
        }

        return $resultado;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'operador']) ?? false;
    }

    private function getNomeProduto(array $item): string
    {
        $sku = $item['codigo'] ?? '';
        if ($sku) {
            $produto = ProdutoEstoque::where('sku', $sku)->first();
            if ($produto && $produto->observacoes) {
                return $produto->observacoes;
            }
        }
        return $item['descricao'] ?? '';
    }
}
