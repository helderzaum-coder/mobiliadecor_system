<?php

namespace App\Console\Commands;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingClient;
use App\Services\MercadoLivre\MercadoLivreClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MercadoLivreCorrigirDadosBling extends Command
{
    protected $signature = 'ml:corrigir-dados-bling {order_id} {--account=primary} {--force}';
    protected $description = 'Busca telefone e complemento do ML e atualiza no Bling (contato + observações do pedido)';

    public function handle(): int
    {
        $orderId = $this->argument('order_id');
        $account = $this->option('account');
        $force = $this->option('force');

        // 1) Buscar pedido no staging pelo numero_loja (order_id do ML)
        $staging = PedidoBlingStaging::where('numero_loja', $orderId)->first();

        if (!$staging) {
            $this->error("Pedido {$orderId} não encontrado no staging (numero_loja).");
            return 1;
        }

        if ($staging->bling_dados_corrigidos && !$force) {
            $this->warn("Pedido já foi corrigido. Use --force para forçar.");
            return 0;
        }

        $this->info("Staging encontrado: Bling ID {$staging->bling_id}, conta: {$staging->bling_account}");

        // 2) Buscar dados do ML (shipping -> receiver_address)
        $mlClient = new MercadoLivreClient($account);
        $orderRes = $mlClient->get("/orders/{$orderId}");

        if (!$orderRes['success']) {
            $this->error("Erro ao buscar pedido ML: HTTP " . ($orderRes['http_code'] ?? '?'));
            return 1;
        }

        $shippingId = $orderRes['body']['shipping']['id'] ?? null;
        if (!$shippingId) {
            $this->error("Pedido ML não tem shipping_id.");
            return 1;
        }

        $shippingRes = $mlClient->get("/shipments/{$shippingId}");
        if (!$shippingRes['success']) {
            $this->error("Erro ao buscar shipping ML: HTTP " . ($shippingRes['http_code'] ?? '?'));
            return 1;
        }

        $receiverAddress = $shippingRes['body']['receiver_address'] ?? [];
        $telefone = $receiverAddress['receiver_phone'] ?? null;
        $complemento = $receiverAddress['comment'] ?? null;

        $this->info("Dados ML encontrados:");
        $this->line("  Telefone: " . ($telefone ?: '(vazio)'));
        $this->line("  Complemento: " . ($complemento ?: '(vazio)'));

        if (!$telefone && !$complemento) {
            $this->warn("Nenhum dado novo para corrigir.");
            return 0;
        }

        // 3) Buscar pedido no Bling para pegar contato ID
        $blingClient = new BlingClient($staging->bling_account);
        $pedidoBling = $blingClient->getPedido((int) $staging->bling_id);

        if (!$pedidoBling['success']) {
            $this->error("Erro ao buscar pedido Bling #{$staging->bling_id}");
            return 1;
        }

        $pedidoData = $pedidoBling['body']['data'] ?? [];
        $contatoId = $pedidoData['contato']['id'] ?? null;

        if (!$contatoId) {
            $this->error("Contato não encontrado no pedido Bling.");
            return 1;
        }

        $this->info("Contato Bling ID: {$contatoId}");

        // 4) Atualizar contato no Bling (telefone)
        if ($telefone) {
            $tel = preg_replace('/\D/', '', $telefone);
            if (strlen($tel) >= 12 && str_starts_with($tel, '55')) {
                $tel = substr($tel, 2);
            }

            $contatoRes = $blingClient->get("/contatos/{$contatoId}");
            $contatoData = $contatoRes['success'] ? ($contatoRes['body']['data'] ?? []) : [];

            $payload = [
                'nome' => $contatoData['nome'] ?? $staging->cliente_nome,
                'tipo' => $contatoData['tipo'] ?? 'F',
                'situacao' => 'A',
                'telefone' => $tel,
            ];

            $this->info("Atualizando telefone no contato...");
            $res = $blingClient->put("/contatos/{$contatoId}", [], $payload);

            if ($res['success']) {
                $this->info("✓ Telefone atualizado com sucesso.");
            } else {
                $this->warn("✗ Falha ao atualizar telefone: HTTP " . ($res['http_code'] ?? '?'));
                $this->line(json_encode($res['body'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        // 5) Atualizar observações do pedido no Bling (complemento/referência)
        if ($complemento) {
            $obsAtual = trim((string) ($pedidoData['observacoes'] ?? ''));
            $obsInternasAtual = trim((string) ($pedidoData['observacoesInternas'] ?? ''));

            // Adicionar complemento nas observações (visíveis na etiqueta/NF)
            $novaObs = $obsAtual;
            if (!str_contains($obsAtual, $complemento)) {
                $novaObs = $obsAtual
                    ? $obsAtual . "\n" . $complemento
                    : $complemento;
            }

            // Montar payload PUT completo
            $payload = $pedidoData;
            $payload['observacoes'] = $novaObs;

            if ($contatoId) {
                $payload['contato'] = ['id' => $contatoId];
            }

            foreach (['id', 'numero', 'situacao', 'dataOperacao', 'dataCriacao'] as $campo) {
                unset($payload[$campo]);
            }

            $this->info("Atualizando observações do pedido...");
            $res = $blingClient->put("/pedidos/vendas/{$staging->bling_id}", [], $payload);

            if ($res['success']) {
                $this->info("✓ Observações atualizadas com sucesso.");
            } else {
                $this->warn("✗ Falha ao atualizar observações: HTTP " . ($res['http_code'] ?? '?'));
                $this->line(json_encode($res['body'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        // 6) Marcar como corrigido no staging
        $staging->update(['bling_dados_corrigidos' => true]);
        $this->info("✓ Pedido marcado como corrigido no staging.");

        return 0;
    }
}
