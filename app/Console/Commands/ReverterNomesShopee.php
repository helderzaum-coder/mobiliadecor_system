<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingClient;
use Illuminate\Console\Command;

class ReverterNomesShopee extends Command
{
    protected $signature = 'shopee:reverter-nomes {--dry-run : Apenas listar sem alterar}';
    protected $description = 'Reverte nomes de contatos no Bling que foram corrompidos (mascarados com ***) usando o nome da NF-e';

    public function handle(): int
    {
        $afetados = PedidoBlingStaging::where('bling_dados_corrigidos', true)
            ->where('cliente_nome', 'like', '%*%')
            ->whereNotNull('bling_id')
            ->get();

        $this->info("Encontrados: {$afetados->count()} pedidos com nome mascarado.");

        if ($afetados->isEmpty()) {
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $corrigidos = 0;
        $erros = 0;

        foreach ($afetados as $staging) {
            $client = new BlingClient($staging->bling_account);

            // Buscar pedido para pegar contato ID
            $pedidoRes = $client->getPedido((int) $staging->bling_id);
            if (!$pedidoRes['success']) {
                $this->warn("  [{$staging->numero_loja}] Erro ao buscar pedido no Bling");
                $erros++;
                continue;
            }

            $contatoId = $pedidoRes['body']['data']['contato']['id'] ?? null;
            if (!$contatoId) {
                $this->warn("  [{$staging->numero_loja}] Contato não encontrado no pedido");
                $erros++;
                continue;
            }

            // Buscar NF-e vinculada para pegar nome correto
            $nomeCorreto = null;

            if ($staging->nfe_chave_acesso) {
                // Buscar NF-e por numeroPedidoLoja
                $nfe = $client->getNfePorPedidoLoja($staging->numero_loja);
                if ($nfe) {
                    $nomeCorreto = $nfe['contato']['nome'] ?? null;
                }
            }

            if (!$nomeCorreto) {
                // Fallback: buscar direto pelo pedido
                $nfe = $client->getNfePorPedidoLoja($staging->numero_loja);
                if ($nfe) {
                    $nomeCorreto = $nfe['contato']['nome'] ?? null;
                }
            }

            if (!$nomeCorreto) {
                $this->warn("  [{$staging->numero_loja}] NF-e não encontrada, não é possível reverter. Nome atual: {$staging->cliente_nome}");
                $erros++;
                continue;
            }

            $this->line("  [{$staging->numero_loja}] {$staging->cliente_nome} → {$nomeCorreto}");

            if ($dryRun) {
                $corrigidos++;
                continue;
            }

            // Buscar contato atual para preservar campos (mesmo padrão do ShopeeCorrigirDadosService)
            $contatoRes = $client->get("/contatos/{$contatoId}");
            $contatoData = $contatoRes['success'] ? ($contatoRes['body']['data'] ?? []) : [];

            $cpf = $contatoData['numeroDocumento'] ?? '';
            $tipoPessoa = (strlen(preg_replace('/\D/', '', $cpf)) > 11) ? 'J' : 'F';

            $payload = [
                'nome' => $nomeCorreto,
                'tipo' => $tipoPessoa,
                'situacao' => 'A',
            ];

            if (!empty($cpf)) {
                $payload['numeroDocumento'] = preg_replace('/\D/', '', $cpf);
            }

            $res = $client->put("/contatos/{$contatoId}", [], $payload);

            if (!$res['success']) {
                $this->error("  [{$staging->numero_loja}] Erro ao atualizar contato: HTTP " . ($res['http_code'] ?? '?'));
                $erros++;
                continue;
            }

            // Atualizar banco local
            $staging->update(['cliente_nome' => $nomeCorreto]);
            \App\Models\Venda::where('bling_id', $staging->bling_id)->update(['cliente_nome' => $nomeCorreto]);

            $corrigidos++;
        }

        $this->info("Concluído. Corrigidos: {$corrigidos} | Erros: {$erros}" . ($dryRun ? ' (DRY RUN)' : ''));

        return 0;
    }
}
