<?php

namespace App\Services\MercadoLivre;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingClient;
use Illuminate\Support\Facades\Log;

class MercadoLivreCorrigirContatoBlingService
{
    /**
     * Busca telefone, complemento e endereço do ML e atualiza o contato no Bling.
     * Chamado automaticamente após importar pedido ML no staging.
     */
    public static function corrigir(PedidoBlingStaging $staging, string $mlAccount = 'primary'): bool
    {
        $orderId = $staging->numero_loja;
        if (!$orderId) {
            return false;
        }

        try {
            $mlClient = new MercadoLivreClient($mlAccount);

            // Buscar shipping para pegar receiver_address (telefone, complemento, endereço)
            $shippingId = $staging->ml_shipping_id;

            if (!$shippingId) {
                $orderRes = $mlClient->get("/orders/{$orderId}");
                if (!$orderRes['success']) return false;
                $shippingId = $orderRes['body']['shipping']['id'] ?? null;
            }

            if (!$shippingId) return false;

            $shippingRes = $mlClient->get("/shipments/{$shippingId}");
            if (!$shippingRes['success']) return false;

            $receiverAddress = $shippingRes['body']['receiver_address'] ?? [];
            $telefone = $receiverAddress['receiver_phone'] ?? null;
            $complemento = $receiverAddress['comment'] ?? null;

            if (!$telefone && !$complemento) return false;

            // Buscar contato ID no pedido Bling
            $blingClient = new BlingClient($staging->bling_account);
            $pedidoBling = $blingClient->getPedido((int) $staging->bling_id);
            if (!$pedidoBling['success']) return false;

            $contatoId = $pedidoBling['body']['data']['contato']['id'] ?? null;
            if (!$contatoId) return false;

            // Buscar contato atual para preservar dados existentes
            $contatoRes = $blingClient->get("/contatos/{$contatoId}");
            if (!$contatoRes['success']) return false;

            $contatoData = $contatoRes['body']['data'] ?? [];

            // Buscar CPF do billing_info se contato estiver sem documento
            $cpfMl = null;
            if (empty($contatoData['numeroDocumento'])) {
                $billingRes = $mlClient->get("/orders/{$orderId}/billing_info");
                if ($billingRes['success']) {
                    $cpfMl = $billingRes['body']['billing_info']['doc_number'] ?? null;
                }
            }

            // Montar payload preservando tudo que já existe
            $payload = [
                'nome'            => $contatoData['nome'] ?? $staging->cliente_nome,
                'tipo'            => $contatoData['tipo'] ?? 'F',
                'situacao'        => $contatoData['situacao'] ?? 'A',
                'numeroDocumento' => $contatoData['numeroDocumento'] ?? $cpfMl ?? '',
                'ie'              => $contatoData['ie'] ?? '',
                'fantasia'        => $contatoData['fantasia'] ?? '',
                'contribuinte'    => $contatoData['contribuinte'] ?? 9,
            ];

            if (!empty($contatoData['email'])) {
                $payload['email'] = $contatoData['email'];
            }

            // Telefone: preenche ambos os campos se estiverem vazios
            if ($telefone) {
                $tel = preg_replace('/\D/', '', $telefone);
                if (strlen($tel) >= 12 && str_starts_with($tel, '55')) {
                    $tel = substr($tel, 2);
                }
                $payload['telefone'] = $contatoData['telefone'] ?? '' ?: $tel;
                $payload['celular']  = $contatoData['celular']  ?? '' ?: $tel;
            } else {
                if (!empty($contatoData['telefone'])) $payload['telefone'] = $contatoData['telefone'];
                if (!empty($contatoData['celular']))  $payload['celular']  = $contatoData['celular'];
            }

            // Endereço: preserva existente, preenche com ML se vazio
            $endAtual = $contatoData['endereco']['geral'] ?? $contatoData['endereco'] ?? [];

            $mlUf = $receiverAddress['state']['id'] ?? '';
            if (str_contains($mlUf, '-')) {
                $mlUf = explode('-', $mlUf)[1];
            }

            $payload['endereco'] = [
                'endereco'    => $endAtual['endereco']  ?? '' ?: ($receiverAddress['street_name']          ?? ''),
                'numero'      => $endAtual['numero']    ?? '' ?: ($receiverAddress['street_number']        ?? ''),
                'bairro'      => $endAtual['bairro']    ?? '' ?: ($receiverAddress['neighborhood']['name'] ?? ''),
                'municipio'   => $endAtual['municipio'] ?? '' ?: ($receiverAddress['city']['name']         ?? ''),
                'uf'          => $endAtual['uf']        ?? '' ?: $mlUf,
                'cep'         => $endAtual['cep']       ?? '' ?: ($receiverAddress['zip_code']             ?? ''),
                'complemento' => $complemento ?: ($endAtual['complemento'] ?? ''),
            ];

            $res = $blingClient->put("/contatos/{$contatoId}", [], $payload);

            if ($res['success']) {
                $staging->update(['bling_dados_corrigidos' => true]);
                Log::info("ML corrigir contato: pedido {$orderId} atualizado (contato {$contatoId})");
                return true;
            }

            Log::warning("ML corrigir contato: falha no PUT contato {$contatoId}", [
                'pedido'    => $orderId,
                'http_code' => $res['http_code'] ?? null,
                'response'  => $res['body'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error("ML corrigir contato: erro pedido {$orderId}: " . $e->getMessage());
        }

        return false;
    }
}
