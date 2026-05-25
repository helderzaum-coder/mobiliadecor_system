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
    protected $description = 'Busca telefone e complemento do ML e atualiza no cadastro do contato no Bling';

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

        // 4) Buscar contato completo do Bling para preservar dados existentes
        $contatoRes = $blingClient->get("/contatos/{$contatoId}");
        if (!$contatoRes['success']) {
            $this->error("Erro ao buscar contato Bling #{$contatoId}");
            return 1;
        }

        $contatoData = $contatoRes['body']['data'] ?? [];
        $this->line("Contato atual: " . json_encode($contatoData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Montar payload preservando TODOS os campos existentes
        $payload = [
            'nome' => $contatoData['nome'] ?? $staging->cliente_nome,
            'tipo' => $contatoData['tipo'] ?? 'F',
            'situacao' => $contatoData['situacao'] ?? 'A',
            'numeroDocumento' => $contatoData['numeroDocumento'] ?? '',
            'ie' => $contatoData['ie'] ?? '',
            'fantasia' => $contatoData['fantasia'] ?? '',
            'contribuinte' => $contatoData['contribuinte'] ?? 9,
        ];

        // Preservar telefone existente ou atualizar com o do ML
        if ($telefone) {
            $tel = preg_replace('/\D/', '', $telefone);
            if (strlen($tel) >= 12 && str_starts_with($tel, '55')) {
                $tel = substr($tel, 2);
            }
            $payload['telefone'] = $tel;
        } elseif (!empty($contatoData['telefone'])) {
            $payload['telefone'] = $contatoData['telefone'];
        }

        if (!empty($contatoData['celular'])) {
            $payload['celular'] = $contatoData['celular'];
        }
        if (!empty($contatoData['email'])) {
            $payload['email'] = $contatoData['email'];
        }

        // Preservar endereço existente e adicionar complemento
        $enderecoAtual = $contatoData['endereco']['geral'] ?? $contatoData['endereco'] ?? [];
        $enderecoPayload = [
            'endereco' => $enderecoAtual['endereco'] ?? '',
            'numero' => $enderecoAtual['numero'] ?? '',
            'bairro' => $enderecoAtual['bairro'] ?? '',
            'municipio' => $enderecoAtual['municipio'] ?? '',
            'uf' => $enderecoAtual['uf'] ?? '',
            'cep' => $enderecoAtual['cep'] ?? '',
            'complemento' => $enderecoAtual['complemento'] ?? '',
        ];

        if ($complemento) {
            $enderecoPayload['complemento'] = $complemento;
        }

        $payload['endereco'] = $enderecoPayload;

        $this->info("Atualizando contato com telefone e complemento...");
        $this->line("Payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $res = $blingClient->put("/contatos/{$contatoId}", [], $payload);

        if ($res['success']) {
            $this->info("✓ Contato atualizado com sucesso (telefone + complemento).");
        } else {
            $this->warn("✗ Falha ao atualizar contato: HTTP " . ($res['http_code'] ?? '?'));
            $this->line(json_encode($res['body'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // 5) Marcar como corrigido no staging
        $staging->update(['bling_dados_corrigidos' => true]);
        $this->info("✓ Pedido marcado como corrigido no staging.");

        return 0;
    }
}
