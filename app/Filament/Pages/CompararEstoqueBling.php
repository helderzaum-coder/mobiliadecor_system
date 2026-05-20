<?php

namespace App\Filament\Pages;

use App\Models\ProdutoEstoque;
use App\Services\Bling\BlingClient;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CompararEstoqueBling extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Comparar Bling';
    protected static ?string $title = 'Comparar Estoque Bling';
    protected static string $view = 'filament.pages.comparar-estoque-bling';

    public string $filtro = 'divergencias'; // divergencias, todos
    public string $buscaSku = '';
    public array $resultados = [];
    public bool $carregando = false;
    public bool $consultaRealizada = false;
    public int $totalProdutos = 0;
    public int $totalDivergencias = 0;

    public function consultar(): void
    {
        $this->resultados = [];
        $this->consultaRealizada = false;
        $this->totalProdutos = 0;
        $this->totalDivergencias = 0;

        $primary = new BlingClient('primary');
        $secondary = new BlingClient('secondary');

        $depositoPrimary = $this->getDeposito($primary);
        $depositoSecondary = $this->getDeposito($secondary);

        if (!$depositoPrimary || !$depositoSecondary) {
            Notification::make()->title('Não foi possível encontrar depósitos no Bling.')->danger()->send();
            return;
        }

        $query = ProdutoEstoque::where('ativo', true);
        if (!empty($this->buscaSku)) {
            $query->where(function ($q) {
                $q->where('sku', 'like', "%{$this->buscaSku}%")
                  ->orWhere('nome', 'like', "%{$this->buscaSku}%");
            });
        }
        $produtos = $query->orderBy('sku')->get();

        $resultados = [];

        foreach ($produtos as $produto) {
            $saldoPrimary = $this->getSaldo($primary, $produto->sku, $depositoPrimary);
            $saldoSecondary = $this->getSaldo($secondary, $produto->sku, $depositoSecondary);

            $divergente = $saldoPrimary !== $saldoSecondary;

            if ($this->filtro === 'divergencias' && !$divergente) {
                $this->totalProdutos++;
                continue;
            }

            $resultados[] = [
                'sku' => $produto->sku,
                'nome' => $produto->nome,
                'sistema' => $produto->saldo,
                'primary' => $saldoPrimary,
                'secondary' => $saldoSecondary,
                'divergente' => $divergente,
            ];

            $this->totalProdutos++;
            if ($divergente) $this->totalDivergencias++;
        }

        // Contar os que não entraram no loop por serem iguais
        if ($this->filtro === 'todos') {
            $this->totalProdutos = count($resultados);
            $this->totalDivergencias = collect($resultados)->where('divergente', true)->count();
        } else {
            $this->totalProdutos = $produtos->count();
            $this->totalDivergencias = count($resultados);
        }

        $this->resultados = $resultados;
        $this->consultaRealizada = true;

        Log::info("CompararEstoqueBling: consultado por " . auth()->user()->name, [
            'filtro' => $this->filtro,
            'busca' => $this->buscaSku,
            'total' => $this->totalProdutos,
            'divergencias' => $this->totalDivergencias,
        ]);
    }

    public function exportarCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU', 'Nome', 'Sistema', 'Primary', 'Secondary', 'Divergente']);

            foreach ($this->resultados as $r) {
                fputcsv($handle, [
                    $r['sku'],
                    $r['nome'],
                    $r['sistema'],
                    $r['primary'] ?? 'N/A',
                    $r['secondary'] ?? 'N/A',
                    $r['divergente'] ? 'SIM' : 'NAO',
                ]);
            }

            fclose($handle);
        }, 'comparacao_estoque_bling_' . now()->format('Y-m-d_His') . '.csv');
    }

    private function getSaldo(BlingClient $client, string $sku, int $depositoId): ?int
    {
        $produto = $client->getProductBySku($sku);
        if (!$produto) return null;

        $res = $client->get('/estoques/saldos', ['idsProdutos[]' => $produto['id']]);
        if (!$res['success'] || empty($res['body']['data'])) return null;

        $dados = $res['body']['data'][0] ?? null;
        if (!$dados) return null;

        foreach ($dados['depositos'] ?? [] as $dep) {
            $depId = (int) ($dep['deposito']['id'] ?? $dep['id'] ?? 0);
            if ($depId === $depositoId) {
                return (int) ($dep['saldoFisico'] ?? 0);
            }
        }

        return 0;
    }

    private function getDeposito(BlingClient $client): ?int
    {
        $res = $client->get('/depositos', ['limite' => 100]);
        if (!$res['success']) return null;

        foreach ($res['body']['data'] ?? [] as $d) {
            if (str_contains(strtolower(trim($d['descricao'] ?? '')), 'geral')) {
                return (int) $d['id'];
            }
        }

        return (int) (($res['body']['data'][0] ?? [])['id'] ?? 0) ?: null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
