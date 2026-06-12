<?php

namespace App\Filament\Pages;

use App\Models\PedidoBlingStaging;
use Filament\Pages\Page;

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
    public string $filtro_status = '';
    public array $resultados = [];
    public bool $consultaRealizada = false;

    // Mapa de situações Bling v3
    private const SITUACOES_BLING = [
        6   => 'Em aberto',
        9   => 'Atendido',
        12  => 'Cancelado',
        15  => 'Em andamento',
        18  => 'Venda agenciada',
        21  => 'Em digitação',
        24  => 'Verificado',
        27  => 'Aguardando',
        28  => 'Pronto p/ envio',
        94  => 'Em produção',
        95  => 'Disponível',
        173 => 'Enviado',
    ];

    public function consultar(): void
    {
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
        if ($this->filtro_status) {
            $query->where('status', $this->filtro_status);
        }

        $pedidos = $query->orderBy('data_pedido', 'desc')->get();

        $this->resultados = $pedidos->flatMap(function ($pedido) {
            $itens = $pedido->itens ?? [];
            $situacao = match ($pedido->status) {
                'pendente' => 'Pendente',
                'aprovado' => 'Aprovado',
                'cancelado' => 'Cancelado',
                'assistencia' => 'Assistência',
                default => $pedido->status ?? '—',
            };
            $cnpj = $pedido->bling_account === 'primary' ? 'Mobilia Decor' : 'HES Móveis';

            // Data liberação etiqueta ML (salva em dados_originais._data_despacho)
            $liberacaoEtiqueta = '—';
            $isML = str_contains(strtolower($pedido->canal ?? ''), 'mercado');
            if ($isML) {
                $dataDespacho = $pedido->dados_originais['_data_despacho'] ?? null;
                if ($dataDespacho) {
                    try {
                        $liberacaoEtiqueta = \Carbon\Carbon::parse($dataDespacho)->format('d/m/Y H:i');
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
                'liberacao_etiqueta' => $liberacaoEtiqueta,
                'is_ml' => $isML,
            ];

            if (empty($itens)) {
                return [array_merge($base, ['produto' => '—', 'quantidade' => '—'])];
            }

            return collect($itens)->map(fn ($item) => array_merge($base, [
                'produto' => ($item['codigo'] ?? '') . ' - ' . ($item['descricao'] ?? ''),
                'quantidade' => $item['quantidade'] ?? 1,
            ]))->toArray();
        })->toArray();

        $this->consultaRealizada = true;
    }

    public function exportarCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Data', 'Status Bling', 'CNPJ', 'Canal', 'Produto', 'Qtd', 'Pedido Bling', 'Pedido Canal', 'Cliente', 'Liberação Etiqueta'], ';');
            foreach ($this->resultados as $r) {
                fputcsv($handle, [
                    $r['data'], $r['situacao_bling'], $r['cnpj'], $r['canal'],
                    $r['produto'], $r['quantidade'], $r['pedido_bling'],
                    $r['pedido_canal'], $r['cliente'], $r['liberacao_etiqueta'],
                ], ';');
            }
            fclose($handle);
        }, 'listagem_pedidos_' . now()->format('Y-m-d') . '.csv');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'operador']) ?? false;
    }
}
