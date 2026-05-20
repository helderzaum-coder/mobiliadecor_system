<?php

namespace App\Console\Commands;

use App\Models\ProdutoEstoque;
use App\Services\Bling\BlingClient;
use Illuminate\Console\Command;

class CompararEstoqueBling extends Command
{
    protected $signature = 'estoque:comparar-bling {--sku= : SKU específico para comparar}';
    protected $description = 'Compara estoque entre Primary e Secondary no Bling';

    public function handle(): int
    {
        $primary = new BlingClient('primary');
        $secondary = new BlingClient('secondary');

        $depositoPrimary = $this->getDeposito($primary);
        $depositoSecondary = $this->getDeposito($secondary);

        if (!$depositoPrimary || !$depositoSecondary) {
            $this->error('Não foi possível encontrar depósitos.');
            return 1;
        }

        $skuFiltro = $this->option('sku');

        $query = ProdutoEstoque::where('ativo', true);
        if ($skuFiltro) {
            $query->where('sku', $skuFiltro);
        }
        $produtos = $query->orderBy('sku')->get();

        $this->info("Comparando {$produtos->count()} produtos...");
        $this->newLine();

        $divergencias = [];
        $bar = $this->output->createProgressBar($produtos->count());

        foreach ($produtos as $produto) {
            $saldoPrimary = $this->getSaldo($primary, $produto->sku, $depositoPrimary);
            $saldoSecondary = $this->getSaldo($secondary, $produto->sku, $depositoSecondary);

            if ($saldoPrimary !== $saldoSecondary) {
                $divergencias[] = [
                    'sku' => $produto->sku,
                    'nome' => mb_substr($produto->nome, 0, 40),
                    'sistema' => $produto->saldo,
                    'primary' => $saldoPrimary ?? 'N/A',
                    'secondary' => $saldoSecondary ?? 'N/A',
                    'diff' => ($saldoPrimary !== null && $saldoSecondary !== null)
                        ? $saldoPrimary - $saldoSecondary
                        : '?',
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (empty($divergencias)) {
            $this->info('✅ Nenhuma divergência encontrada! Estoques iguais.');
        } else {
            $count = count($divergencias);
            $this->warn("⚠️  {$count} divergência(s) encontrada(s):");
            $this->newLine();
            $this->table(
                ['SKU', 'Nome', 'Sistema', 'Primary', 'Secondary', 'Diff (P-S)'],
                $divergencias
            );
        }

        return 0;
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
}
