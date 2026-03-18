<?php

namespace App\Services\MercadoLivre;

use Illuminate\Support\Facades\Log;

class MercadoLivreOrderService
{
    private MercadoLivreClient $client;

    public function __construct(string $accountKey = 'primary')
    {
        $this->client = new MercadoLivreClient($accountKey);
    }

    /**
     * Busca dados complementares de um pedido ML pelo ID do pedido (pack_id ou order_id)
     * Retorna: tipo_anuncio, tem_rebate, tipo_frete (ME1/ME2), valor_rebate
     */
    public function buscarDadosPedido(string $orderId): ?array
    {
        $order = $this->getOrder($orderId);

        if (!$order) {
            return null;
        }

        $resultado = [
            'order_id' => $orderId,
            'tipo_anuncio' => null,
            'tem_rebate' => false,
            'valor_rebate' => 0,
            'tipo_frete' => null,
            'shipping_id' => null,
            'sale_fee' => 0,
            'frete_ml_custo' => 0,
            'frete_ml_receita' => 0,
        ];

        // Tipo de anúncio (clássico/premium) e sale_fee - vem do item
        if (!empty($order['order_items'])) {
            $item = $order['order_items'][0];
            $listingTypeId = $item['listing_type_id'] ?? null;
            $resultado['tipo_anuncio'] = $this->traduzirTipoAnuncio($listingTypeId);

            // sale_fee = comissão real cobrada pelo ML (já com rebate descontado se houver)
            $saleFee = (float) ($item['sale_fee'] ?? 0);
            $resultado['sale_fee'] = $saleFee;
        }

        // Tipo de frete e custos - vem do shipping
        $shippingId = $order['shipping']['id'] ?? null;
        if ($shippingId) {
            $resultado['shipping_id'] = $shippingId;
            $dadosFrete = $this->buscarDadosFrete($shippingId);
            $resultado['tipo_frete'] = $dadosFrete['tipo_frete'];
            $resultado['frete_ml_custo'] = $dadosFrete['frete_ml_custo'];
            $resultado['frete_ml_receita'] = $dadosFrete['frete_ml_receita'];
        }

        return $resultado;
    }

    /**
     * Busca o pedido na API do ML
     */
    private function getOrder(string $orderId): ?array
    {
        $response = $this->client->get("/orders/{$orderId}");

        if (!$response['success']) {
            Log::warning("ML: Erro ao buscar pedido {$orderId}", $response);
            return null;
        }

        return $response['body'];
    }

    /**
     * Retorna o percentual teórico de comissão por tipo de anúncio
     */
    private function percentualPorTipoAnuncio(?string $listingTypeId): float
    {
        return match ($listingTypeId) {
            'gold_special' => 11.5, // Clássico
            'gold_pro' => 16.5,     // Premium
            default => 11.5,
        };
    }

    /**
     * Busca tipo de frete (ME1 = Coleta / ME2 = Flex/Drop-off)
     * Retorna tipo e custos de frete
     */
    private function buscarDadosFrete(string $shippingId): array
    {
        $response = $this->client->get("/shipments/{$shippingId}");

        if (!$response['success']) {
            Log::warning("ML: Erro ao buscar shipping {$shippingId}");
            return ['tipo_frete' => null, 'frete_ml_custo' => 0, 'frete_ml_receita' => 0];
        }

        $shipping = $response['body'];
        $logisticType = $shipping['logistic_type'] ?? null;

        $tipoFrete = match ($logisticType) {
            'cross_docking' => 'ME1',
            'xd_drop_off', 'drop_off' => 'ME2',
            'fulfillment' => 'FULL',
            'self_service' => 'ME2',
            'default' => 'ME1',
            default => $logisticType,
        };

        // Custos de frete do shipping
        $shippingOption = $shipping['shipping_option'] ?? [];
        $listCost = (float) ($shippingOption['list_cost'] ?? 0);  // custo cobrado pelo ML do vendedor
        $cost = (float) ($shippingOption['cost'] ?? 0);            // valor pago pelo comprador

        return [
            'tipo_frete' => $tipoFrete,
            'frete_ml_custo' => $listCost,    // o que o ML cobra do vendedor
            'frete_ml_receita' => $cost,       // o que o comprador pagou
        ];
    }

    /**
     * Traduz listing_type_id para nome legível
     */
    private function traduzirTipoAnuncio(?string $listingTypeId): ?string
    {
        return match ($listingTypeId) {
            'gold_special' => 'Clássico',
            'gold_pro' => 'Premium',
            'gold' => 'Ouro',
            'silver' => 'Prata',
            'bronze' => 'Bronze',
            'free' => 'Grátis',
            default => $listingTypeId,
        };
    }

    /**
     * Busca dados ML para múltiplos pedidos de uma vez
     */
    public function buscarDadosMultiplosPedidos(array $orderIds): array
    {
        $resultados = [];

        foreach ($orderIds as $orderId) {
            try {
                $dados = $this->buscarDadosPedido($orderId);
                if ($dados) {
                    $resultados[$orderId] = $dados;
                }
            } catch (\Exception $e) {
                Log::error("ML: Erro ao buscar pedido {$orderId}: " . $e->getMessage());
            }

            // Rate limiting - ML permite ~10 req/s
            usleep(150000); // 150ms entre requests
        }

        return $resultados;
    }
}
