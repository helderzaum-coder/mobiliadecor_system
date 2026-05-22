<?php

namespace App\Filament\Pages;

use App\Models\ProdutoEstoque;
use App\Services\Bling\BlingClient;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompararEstoqueBling extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Comparar Bling';
    protected static ?string $title = 'Comparar Estoque Bling';
    protected static string $view = 'filament.pages.comparar-estoque-bling';

    public string $filtro = 'divergencias';
    public string $buscaSku = '';
    public array $resultados = [];
    public bool $consultaRealizada = false;
    public int $totalProdutos = 0;
    public int $totalDivergencias = 0;
    public bool $jobRodando = false;

    public function mount(): void
    {
        // Carregar resultado anterior do cache se existir
        $cached = Cache::get('comparar_bling_resultado');
        if ($cached) {
            $this->resultados = $cached['resultados'];
            $this->totalProdutos = $cached['totalProdutos'];
            $this->totalDivergencias = $cached['totalDivergencias'];
            $this->consultaRealizada = true;
        }
        $this->jobRodando = Cache::has('comparar_bling_running');
    }

    public function consultar(): void
    {
        if (empty($this->buscaSku)) {
            // Consulta completa: rodar via Job
            \App\Jobs\CompararEstoqueBlingJob::dispatch($this->filtro, auth()->id());
            $this->jobRodando = true;
            Notification::make()->title('Comparação iniciada em background. Recarregue a página em alguns minutos.')->info()->send();
            return;
        }

        // Busca específica: executar inline (rápido)
        $this->resultados = [];
        $this->consultaRealizada = false;

        $primary = new BlingClient('primary');
        $secondary = new BlingClient('secondary');

        $depositoPrimary = $this->getDeposito($primary);
        $depositoSecondary = $this->getDeposito($secondary);

        if (!$depositoPrimary || !$depositoSecondary) {
            Notification::make()->title('Não foi possível encontrar depósitos no Bling.')->danger()->send();
            return;
        }

        $query = ProdutoEstoque::where('ativo', true)
            ->where(function ($q) {
                $q->where('sku', 'like', "%{$this->buscaSku}%")
                  ->orWhere('nome', 'like', "%{$this->buscaSku}%");
            });
        $produtos = $query->orderBy('sku')->limit(20)->get();

        $resultados = [];

        foreach ($produtos as $produto) {
            $saldoPrimary = $this->getSaldo($primary, $produto->sku, $depositoPrimary);
            $saldoSecondary = $this->getSaldo($secondary, $produto->sku, $depositoSecondary);
            $divergente = $saldoPrimary !== $saldoSecondary;

            if ($this->filtro === 'divergencias' && !$divergente) continue;

            $resultados[] = [
                'sku' => $produto->sku,
                'nome' => $produto->nome,
                'sistema' => $produto->saldo,
                'primary' => $saldoPrimary,
                'secondary' => $saldoSecondary,
                'divergente' => $divergente,
            ];
        }

        $this->resultados = $resultados;
        $this->totalProdutos = $produtos->count();
        $this->totalDivergencias = collect($resultados)->where('divergente', true)->count();
        $this->consultaRealizada = true;
    }

    public function recarregar(): void
    {
        $cached = Cache::get('comparar_bling_resultado');
        if ($cached) {
            $this->resultados = $cached['resultados'];
            $this->totalProdutos = $cached['totalProdutos'];
            $this->totalDivergencias = $cached['totalDivergencias'];
            $this->consultaRealizada = true;
            $this->filtro = $cached['filtro'] ?? 'divergencias';
        }
        $this->jobRodando = Cache::has('comparar_bling_running');
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
