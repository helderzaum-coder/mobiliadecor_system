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
     *
     * O ML é a fonte verdadeira dos dados — a integração nativa do Bling com ML
     * frequentemente perde número da casa, complemento e telefone.
     */
    public static function corrigir(PedidoBlingStaging $staging, string $mlAccount = 'primary'): bool
    {
        $orderId = $staging->numero_loja;
        if (!$orderId) {
            return false;
        }

        try {
            $mlClient = new MercadoLivreClient($mlAccount);

            // Buscar order para pegar buyer.phone e shipping_id
            $orderRes = $mlClient->get("/orders/{$orderId}");
            if (!$orderRes['success']) return false;

            $orderBody = $orderRes['body'];
            $shippingId = $staging->ml_shipping_id ?: ($orderBody['shipping']['id'] ?? null);

            // Telefone: tentar buyer.phone da order primeiro
            $telefone = null;
            $buyerPhone = $orderBody['buyer']['phone'] ?? [];
            if (!empty($buyerPhone['number'])) {
                $areaCode = $buyerPhone['area_code'] ?? '';
                $telefone = $areaCode . $buyerPhone['number'];
            }

            $receiverAddress = [];
            $complemento = null;

            if ($shippingId) {
                $shippingRes = $mlClient->get("/shipments/{$shippingId}");
                if ($shippingRes['success']) {
                    $receiverAddress = $shippingRes['body']['receiver_address'] ?? [];
                    $complemento = $receiverAddress['comment'] ?? null;

                    // Fallback: telefone do receiver_address se buyer.phone veio vazio
                    if (!$telefone && !empty($receiverAddress['receiver_phone'])) {
                        $telefone = $receiverAddress['receiver_phone'];
                    }
                }
            }

            // Sempre buscar billing_info — tem endereço completo e documento
            $billingRes = $mlClient->get("/orders/{$orderId}/billing_info");
            $billingData = [];
            $billingAddress = [];
            $cpfMl = null;

            if ($billingRes['success']) {
                $billingData = $billingRes['body']['billing_info'] ?? $billingRes['body'] ?? [];
                $cpfMl = $billingData['doc_number'] ?? null;

                // Extrair dados de endereço do additional_info
                $additionalInfo = $billingData['additional_info'] ?? [];
                foreach ($additionalInfo as $info) {
                    $type = $info['type'] ?? '';
                    $value = $info['value'] ?? '';
                    match ($type) {
                        'STREET_NAME' => $billingAddress['street_name'] = $value,
                        'STREET_NUMBER' => $billingAddress['street_number'] = $value,
                        'NEIGHBORHOOD' => $billingAddress['neighborhood'] = $value,
                        'CITY_NAME' => $billingAddress['city'] = $value,
                        'STATE_CODE' => $billingAddress['state'] = $value,
                        'ZIP_CODE' => $billingAddress['zip_code'] = $value,
                        default => null,
                    };
                }

                // Fallback telefone: phone da billing_info
                if (!$telefone && !empty($billingData['phone']['number'] ?? null)) {
                    $telefone = ($billingData['phone']['area_code'] ?? '') . $billingData['phone']['number'];
                }
            }

            if (!$telefone && !$complemento && empty($receiverAddress) && empty($billingAddress)) return false;

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

            // Montar payload preservando dados do Bling que não vêm do ML
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

            // Endereço: ML é a fonte verdadeira
            // Prioridade: receiver_address (shipping) > billing_info > Bling existente
            $endAtual = $contatoData['endereco']['geral'] ?? $contatoData['endereco'] ?? [];

            $mlUf = $receiverAddress['state']['id'] ?? $billingAddress['state'] ?? '';
            if (str_contains($mlUf, '-')) {
                $mlUf = explode('-', $mlUf)[1];
            }

            $mlEndereco = $receiverAddress['street_name'] ?? $billingAddress['street_name'] ?? null;
            $mlNumero = $receiverAddress['street_number'] ?? $billingAddress['street_number'] ?? null;
            $mlBairro = $receiverAddress['neighborhood']['name'] ?? $billingAddress['neighborhood'] ?? null;
            $mlCidade = $receiverAddress['city']['name'] ?? $billingAddress['city'] ?? null;
            $mlCep = $receiverAddress['zip_code'] ?? $billingAddress['zip_code'] ?? null;

            $payload['endereco'] = [
                'endereco'    => $mlEndereco ?: ($endAtual['endereco'] ?? ''),
                'numero'      => $mlNumero ?: ($endAtual['numero'] ?? ''),
                'bairro'      => $mlBairro ?: ($endAtual['bairro'] ?? ''),
                'municipio'   => $mlCidade ?: ($endAtual['municipio'] ?? ''),
                'uf'          => $mlUf ?: ($endAtual['uf'] ?? ''),
                'cep'         => $mlCep ?: ($endAtual['cep'] ?? ''),
                'complemento' => $complemento ?: ($endAtual['complemento'] ?? ''),
            ];

            $res = $blingClient->put("/contatos/{$contatoId}", [], $payload);

            if ($res['success']) {
                $staging->update(['bling_dados_corrigidos' => true]);
                Log::info("ML corrigir contato: pedido {$orderId} atualizado (contato {$contatoId})", [
                    'telefone_encontrado' => !empty($telefone),
                    'complemento_encontrado' => !empty($complemento),
                    'numero_casa' => $mlNumero,
                    'fonte_numero' => !empty($receiverAddress['street_number']) ? 'shipping' : (!empty($billingAddress['street_number']) ? 'billing_info' : 'nenhuma'),
                ]);
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
