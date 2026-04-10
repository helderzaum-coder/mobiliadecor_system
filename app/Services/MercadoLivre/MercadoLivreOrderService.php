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
     * Usa /orders, /shipments e /collections para obter todos os dados financeiros.
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
            'net_received_amount' => 0,
        ];

        $listingTypeId = null;
        $unitPrice = 0;

        if (!empty($order['order_items'])) {
            $item = $order['order_items'][0];
            $listingTypeId = $item['listing_type_id'] ?? null;
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $resultado['tipo_anuncio'] = $this->traduzirTipoAnuncio($listingTypeId);
            $resultado['sale_fee'] = (float) ($item['sale_fee'] ?? 0);
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

        // Dados financeiros: sale_fee do order é a comissão real cobrada pelo ML
        // net_received_amount do /collections serve apenas como confirmação
        $paymentId = $order['payments'][0]['id'] ?? null;
        if ($paymentId) {
            $financeiro = $this->buscarDadosFinanceiros($paymentId);
            if ($financeiro) {
                $resultado['net_received_amount'] = $financeiro['net_received_amount'];
            }
        }

        // Comissão = sale_fee do order (valor real cobrado pelo ML)
        // Tarifa bruta = preço × percentual do tipo de anúncio
        $tarifaBruta = round($unitPrice * $this->percentualPorTipoAnuncio($listingTypeId) / 100, 2);
        $saleFee = $resultado['sale_fee']; // já veio do order_items[0].sale_fee

        // Rebate = tarifa bruta - sale_fee (se ML deu desconto na comissão)
        $rebate = round($tarifaBruta - $saleFee, 2);
        if ($rebate > 0.01) {
            $resultado['tem_rebate'] = true;
            $resultado['valor_rebate'] = $rebate;
        }

        Log::info("ML financeiro pedido {$orderId}", [
            'sale_fee' => $saleFee,
            'tarifa_bruta' => $tarifaBruta,
            'frete_ml_custo' => $resultado['frete_ml_custo'],
            'rebate' => $rebate,
            'net_received' => $resultado['net_received_amount'],
        ]);

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
     * Busca dados financeiros reais via /collections/{payment_id}
     * Retorna net_received_amount (valor real repassado ao vendedor)
     */
    private function buscarDadosFinanceiros(int $paymentId): ?array
    {
        $response = $this->client->get("/collections/{$paymentId}");

        if (!$response['success']) {
            Log::warning("ML: Erro ao buscar collection {$paymentId}", $response);
            return null;
        }

        $data = $response['body'];

        return [
            'net_received_amount' => (float) ($data['net_received_amount'] ?? 0),
            'transaction_amount' => (float) ($data['transaction_amount'] ?? 0),
            'shipping_cost' => (float) ($data['shipping_cost'] ?? 0),
            'total_paid_amount' => (float) ($data['total_paid_amount'] ?? 0),
            'finance_fee' => (float) ($data['finance_fee'] ?? 0),
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
