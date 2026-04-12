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
     * Se o pedido faz parte de um pack, busca todas as orders do pack.
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

        // Se faz parte de um pack, buscar todas as orders do pack
        $packId = $order['pack_id'] ?? null;
        $orders = [$order];

        if ($packId) {
            $packOrders = $this->getOrdersByPack($packId);
            if (!empty($packOrders)) {
                $orders = $packOrders;
                Log::info("ML pack {$packId}: encontradas " . count($orders) . " orders");
            }
        }

        $listingTypeId = null;
        $totalProduto = 0;
        $totalSaleFee = 0;
        $totalNetReceived = 0;

        foreach ($orders as $ord) {
            if (!empty($ord['order_items'])) {
                if (!$listingTypeId) {
                    $listingTypeId = $ord['order_items'][0]['listing_type_id'] ?? null;
                }

                foreach ($ord['order_items'] as $item) {
                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                    $qty = (int) ($item['quantity'] ?? 1);
                    $fee = (float) ($item['sale_fee'] ?? 0);

                    $totalProduto += $unitPrice * $qty;
                    $totalSaleFee += $fee * $qty;
                }
            }

            // Somar net_received de cada order do pack
            $paymentId = $ord['payments'][0]['id'] ?? null;
            if ($paymentId) {
                $financeiro = $this->buscarDadosFinanceiros($paymentId);
                if ($financeiro) {
                    $totalNetReceived += $financeiro['net_received_amount'];
                }
            }
        }

        $resultado['tipo_anuncio'] = $this->traduzirTipoAnuncio($listingTypeId);
        $resultado['sale_fee'] = round($totalSaleFee, 2);
        $resultado['net_received_amount'] = round($totalNetReceived, 2);

        // Tipo de frete e custos - vem do shipping (compartilhado no pack)
        $shippingId = $order['shipping']['id'] ?? null;
        if ($shippingId) {
            $resultado['shipping_id'] = $shippingId;
            $dadosFrete = $this->buscarDadosFrete($shippingId);
            $resultado['tipo_frete'] = $dadosFrete['tipo_frete'];
            $resultado['frete_ml_custo'] = $dadosFrete['frete_ml_custo'];
            $resultado['frete_ml_receita'] = $dadosFrete['frete_ml_receita'];
        }

        // Rebate = tarifa bruta - sale_fee
        $tarifaBruta = round($totalProduto * $this->percentualPorTipoAnuncio($listingTypeId) / 100, 2);
        $saleFee = $resultado['sale_fee'];

        $rebate = round($tarifaBruta - $saleFee, 2);
        if ($rebate > 0.01) {
            $resultado['tem_rebate'] = true;
            $resultado['valor_rebate'] = $rebate;
        }

        Log::info("ML financeiro pedido {$orderId}" . ($packId ? " (pack {$packId})" : ''), [
            'sale_fee' => $saleFee,
            'tarifa_bruta' => $tarifaBruta,
            'frete_ml_custo' => $resultado['frete_ml_custo'],
            'rebate' => $rebate,
            'net_received' => $resultado['net_received_amount'],
            'orders_no_pack' => count($orders),
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
     * Busca todas as orders de um pack
     */
    private function getOrdersByPack(int $packId): array
    {
        $response = $this->client->get("/packs/{$packId}");

        if (!$response['success']) {
            Log::warning("ML: Erro ao buscar pack {$packId}", $response);
            return [];
        }

        $orderIds = collect($response['body']['orders'] ?? [])->pluck('id')->filter()->toArray();

        if (empty($orderIds)) {
            return [];
        }

        $orders = [];
        foreach ($orderIds as $oid) {
            $order = $this->getOrder((string) $oid);
            if ($order) {
                $orders[] = $order;
            }
            usleep(100000); // rate limiting
        }

        return $orders;
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
            'frete_ml_custo' => $listCost,   // tarifa de envio do ML (list_cost)
            'frete_ml_receita' => $cost,      // valor pago pelo comprador (cost)
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
