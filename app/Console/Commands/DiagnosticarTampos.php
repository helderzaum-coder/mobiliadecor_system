<?php

namespace App\Console\Commands;

use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
use Illuminate\Console\Command;

class DiagnosticarTampos extends Command
{
    protected $signature = 'tampos:diagnosticar {--cor= : Filtrar por cor} {--grupo= : Filtrar por grupo}';
    protected $description = 'Diagnostica o cálculo de variação de tampos sem alterar nada';

    public function handle(): int
    {
        $query = TrocaTampoConfig::query();

        if ($this->option('cor')) {
            $query->where('cor', 'like', '%' . $this->option('cor') . '%');
        }
        if ($this->option('grupo')) {
            $query->where('grupo', 'like', '%' . $this->option('grupo') . '%');
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->error('Nenhuma config encontrada com esses filtros.');
            return 1;
        }

        $this->info("Configs encontradas: {$configs->count()}");
        $this->newLine();

        // Mostrar cada config e seu estado
        $rows = [];
        foreach ($configs as $config) {
            $produto = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
            $tampo = ProdutoEstoque::where('sku', $config->sku_tampo)->where('ativo', true)->first();

            $rows[] = [
                $config->sku_produto,
                $config->grupo,
                $config->cor,
                $config->tipo_tampo,
                $config->familia_tampo ?: '(vazio)',
                $config->equalizacao_ativa ? 'SIM' : 'NÃO',
                $produto ? $produto->saldo_fisico : 'N/E',
                $produto ? ($produto->saldo_carcaca === null ? 'NULL' : $produto->saldo_carcaca) : 'N/E',
                $config->sku_tampo,
                $tampo ? $tampo->saldo_fisico : 'N/E',
            ];
        }

        $this->table(
            ['SKU', 'Grupo', 'Cor', 'Tipo', 'Família', 'Equaliz.', 'Físico', 'Carc.', 'SKU Tampo', 'Tampo Físico'],
            $rows
        );

        // Simular o cálculo da variação por família+cor
        $this->newLine();
        $this->info('=== SIMULAÇÃO DO CÁLCULO ===');

        $ativos = $configs->where('equalizacao_ativa', true)
            ->filter(fn ($c) => !empty($c->familia_tampo));

        if ($ativos->isEmpty()) {
            $this->warn('Nenhuma config com equalizacao_ativa=true E familia_tampo preenchida. O job IGNORA estes SKUs!');
            return 0;
        }

        foreach ($ativos->groupBy('familia_tampo') as $familia => $membros) {
            $this->line("Família (tampo): <info>{$familia}</info>");

            // Carcaças por GRUPO+COR (carcaças não são compartilhadas entre grupos)
            $carcacasPorGrupoCor = [];
            foreach ($membros as $config) {
                $produto = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
                $carcaca = ($produto && $produto->saldo_carcaca !== null) ? (int) $produto->saldo_carcaca : 0;
                $chave = $config->grupo . '|' . $config->cor;
                $carcacasPorGrupoCor[$chave] = ($carcacasPorGrupoCor[$chave] ?? 0) + max(0, $carcaca);
            }

            foreach ($carcacasPorGrupoCor as $chave => $total) {
                [$g, $c] = explode('|', $chave);
                $this->line("  Grupo <comment>{$g}</comment> / Cor <comment>{$c}</comment>: total carcaças = <info>{$total}</info>");
            }

            foreach ($membros as $config) {
                $produto = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
                $tampo = ProdutoEstoque::where('sku', $config->sku_tampo)->where('ativo', true)->first();
                $chave = $config->grupo . '|' . $config->cor;
                $totalCarcacas = $carcacasPorGrupoCor[$chave] ?? 0;
                $tampoSaldo = $tampo ? $tampo->saldo_fisico : 0;
                $saldoFinal = min($totalCarcacas, $tampoSaldo);
                $atual = $produto ? $produto->saldo_fisico : 0;
                $vaiAlterar = ((int) $atual != (int) $saldoFinal) ? 'SIM' : 'não';

                $this->line("  {$config->sku_produto} ({$config->grupo}): min(carcaças={$totalCarcacas}, tampo={$tampoSaldo}) = <info>{$saldoFinal}</info> | atual={$atual} | altera? <comment>{$vaiAlterar}</comment>");
            }
            $this->newLine();
        }

        return 0;
    }
}
