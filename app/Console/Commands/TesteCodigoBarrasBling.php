<?php

namespace App\Console\Commands;

use App\Models\ProdutoEstoque;
use App\Services\Bling\BlingClient;
use Illuminate\Console\Command;

class TesteCodigoBarrasBling extends Command
{
    protected $signature = 'bling:teste-codigos-barras {--limit=10}';
    protected $description = 'Busca código de barras de N produtos no Bling para teste';

    public function handle(): void
    {
        $client = new BlingClient('primary');
        $limit = (int) $this->option('limit');

        $produtos = ProdutoEstoque::where('ativo', true)
            ->whereNull('codigo_barras')
            ->where('formato', 'S')
            ->limit($limit)
            ->get();

        $this->info("Buscando código de barras de {$produtos->count()} produtos...");

        foreach ($produtos as $produto) {
            $blingProduto = $client->getProductBySku($produto->sku);

            if (!$blingProduto) {
                $this->warn("  {$produto->sku}: não encontrado no Bling");
                continue;
            }

            $detalhe = $client->getProductById((int) $blingProduto['id']);

            if (!$detalhe) {
                $this->warn("  {$produto->sku}: detalhe não retornado");
                continue;
            }

            // Logar todos os campos que podem conter código de barras
            $candidatos = [
                'gtin' => $detalhe['gtin'] ?? null,
                'gtinEmbalagem' => $detalhe['gtinEmbalagem'] ?? null,
                'codigoBarras' => $detalhe['codigoBarras'] ?? null,
                'codigo_barras' => $detalhe['codigo_barras'] ?? null,
                'ean' => $detalhe['ean'] ?? null,
            ];

            $codigoEncontrado = $candidatos['gtin']
                ?? $candidatos['gtinEmbalagem']
                ?? $candidatos['codigoBarras']
                ?? $candidatos['codigo_barras']
                ?? $candidatos['ean']
                ?? null;

            $this->info("  {$produto->sku}: " . json_encode($candidatos));

            if ($codigoEncontrado) {
                $produto->update(['codigo_barras' => $codigoEncontrado]);
                $this->info("    → Salvo: {$codigoEncontrado}");
            } else {
                // Mostrar todas as chaves do detalhe para debug
                $this->warn("    → Nenhum campo encontrado. Chaves: " . implode(', ', array_keys($detalhe)));
            }
        }

        $this->info('Concluído.');
    }
}
