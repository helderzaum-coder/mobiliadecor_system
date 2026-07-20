<?php

namespace App\Console\Commands;

use App\Models\ProdutoEstoque;
use Illuminate\Console\Command;

class ExportarProdutosSemMovimentacao extends Command
{
    protected $signature = 'estoque:exportar-sem-movimentacao {--horas=1 : Janela de horas para verificar movimentações}';
    protected $description = 'Exporta CSV de produtos simples sem movimentação no período';

    public function handle(): void
    {
        $horas = (int) $this->option('horas');
        $desde = now()->subHours($horas);

        $produtos = ProdutoEstoque::where('ativo', true)
            ->where('formato', 'S')
            ->whereDoesntHave('movimentacoes', fn ($q) => $q->where('created_at', '>=', $desde))
            ->orderBy('sku')
            ->get(['sku', 'nome', 'observacoes', 'saldo_fisico', 'saldo_virtual', 'saldo']);

        if ($produtos->isEmpty()) {
            $this->warn('Nenhum produto encontrado.');
            return;
        }

        $path = storage_path('app/produtos_sem_movimentacao_' . now()->format('Ymd_His') . '.csv');
        $fp = fopen($path, 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

        fputcsv($fp, ['SKU', 'Nome', 'Observações', 'Saldo Físico', 'Saldo Virtual', 'Saldo Total'], ';');

        foreach ($produtos as $p) {
            fputcsv($fp, [
                $p->sku,
                $p->observacoes ?: $p->nome,
                $p->nome,
                $p->saldo_fisico,
                $p->saldo_virtual,
                $p->saldo,
            ], ';');
        }

        fclose($fp);

        $this->info("✅ {$produtos->count()} produtos exportados para:");
        $this->line($path);
    }
}
