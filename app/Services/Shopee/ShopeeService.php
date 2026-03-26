<?php

namespace App\Services\Shopee;

use App\Models\PedidoBlingStaging;
use Illuminate\Support\Facades\Log;
use Laraditz\Shopee\Facades\Shopee;

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ShopeeService — Integração com laraditz/shopee                   ║
 * ║                                                                    ║
 * ║  Responsável por:                                                  ║
 * ║  - Buscar detalhes de pedidos Shopee via API                      ║
 * ║  - Atualizar staging com dados financeiros reais                  ║
 * ║  - Reprocessar pedidos após planilha (se aplicável)               ║
 * ║                                                                    ║
 * ║  Usa laraditz/shopee para chamadas oficiais da API Shopee         ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */
class ShopeeService
{
    /**
     * Busca detalhes de um pedido Shopee via API.
     *
     * @param string $orderSn Número do pedido Shopee
     * @return array|null Dados do pedido ou null se erro
     */
    public static function getOrderDetail(string $orderSn): ?array
    {
        try {
            $response = Shopee::order()->getOrderDetail(order_sn_list: $orderSn);

            if (isset($response['order_list'][0])) {
                return $response['order_list'][0];
            }

            Log::warning('ShopeeService: pedido não encontrado', ['order_sn' => $orderSn]);
            return null;

        } catch (\Throwable $e) {
            Log::error('ShopeeService: erro ao buscar pedido', [
                'order_sn' => $orderSn,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Busca dados de escrow/pagamento para um pedido.
     *
     * @param string $orderSn Número do pedido Shopee
     * @return array|null Dados financeiros ou null se erro
     */
    public static function getEscrowDetail(string $orderSn): ?array
    {
        try {
            $response = Shopee::payment()->getEscrowDetail(order_sn: $orderSn);
            return $response;

        } catch (\Throwable $e) {
            Log::error('ShopeeService: erro ao buscar escrow', [
                'order_sn' => $orderSn,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Reprocessa um pedido no staging com dados Shopee (sem planilha).
     *
     * Atualiza apenas campos financeiros que vêm da API Shopee:
     * - Comissão real
     * - Subsídios (PIX, cupom marketplace)
     * - Frete (se aplicável)
     *
     * @param PedidoBlingStaging $staging
     */
    public static function reprocessarPedido(PedidoBlingStaging $staging): void
    {
        if (!$staging->numero_loja) {
            Log::warning('ShopeeService: pedido sem numero_loja', ['id' => $staging->id]);
            return;
        }

        $orderDetail = self::getOrderDetail($staging->numero_loja);
        if (!$orderDetail) {
            return;
        }

        $escrowDetail = self::getEscrowDetail($staging->numero_loja);

        // Extrair dados financeiros
        $dadosFinanceiros = self::extrairDadosFinanceiros($orderDetail, $escrowDetail);

        // Atualizar staging
        $staging->update([
            'comissao' => $dadosFinanceiros['comissao'] ?? $staging->comissao,
            'subsidio_pix' => $dadosFinanceiros['subsidio_pix'] ?? $staging->subsidio_pix,
            'cupom_shopee' => $dadosFinanceiros['cupom_shopee'] ?? $staging->cupom_shopee,
            'frete' => $dadosFinanceiros['frete'] ?? $staging->frete,
        ]);

        Log::info('ShopeeService: pedido reprocessado via API', [
            'numero_loja' => $staging->numero_loja,
            'comissao' => $dadosFinanceiros['comissao'],
            'subsidio_total' => ($dadosFinanceiros['subsidio_pix'] ?? 0) + ($dadosFinanceiros['cupom_shopee'] ?? 0),
        ]);
    }

    /**
     * Extrai dados financeiros do response da API Shopee.
     *
     * @param array $orderDetail
     * @param array|null $escrowDetail
     * @return array
     */
    private static function extrairDadosFinanceiros(array $orderDetail, ?array $escrowDetail): array
    {
        $dados = [];

        // Comissão do pedido
        if (isset($orderDetail['actual_shipping_fee'])) {
            $dados['frete'] = (float) $orderDetail['actual_shipping_fee'];
        }

        // Dados de escrow para comissão e subsídios
        if ($escrowDetail) {
            // Comissão líquida (sale_fee - rebate)
            if (isset($escrowDetail['order_income']['seller_transaction_fee'])) {
                $dados['comissao'] = (float) $escrowDetail['order_income']['seller_transaction_fee'];
            }

            // Subsídios
            if (isset($escrowDetail['order_income']['shopee_offset'])) {
                $dados['subsidio_pix'] = (float) $escrowDetail['order_income']['shopee_offset'];
            }

            // Cupom marketplace (se disponível)
            if (isset($escrowDetail['order_income']['voucher_from_shopee'])) {
                $dados['cupom_shopee'] = (float) $escrowDetail['order_income']['voucher_from_shopee'];
            }
        }

        return $dados;
    }

    /**
     * Verifica se a integração Shopee está autorizada.
     *
     * @return bool
     */
    public static function getShopId(): ?int
    {
        return config('shopee.shop_id') ?: null;
    }

    public static function isAuthorized(): bool
    {
        try {
            $shopId = self::getShopId();
            if (!$shopId) {
                return false;
            }

            $info = Shopee::make(shop_id: $shopId)->shop()->getShopInfo();
            return !empty($info['shop_id']);
        } catch (\Throwable $e) {
            Log::error('ShopeeService::isAuthorized falhou', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Busca lista de pedidos Shopee em um período.
     *
     * @param int $timeFrom Timestamp inicial
     * @param int $timeTo Timestamp final
     * @return array
     */
    public static function getOrderList(int $timeFrom, int $timeTo): array
    {
        try {
            return Shopee::order()->getOrderList(
                time_range_field: 'create_time',
                time_from: $timeFrom,
                time_to: $timeTo,
                page_size: 100
            );
        } catch (\Throwable $e) {
            Log::error('ShopeeService: erro ao buscar lista de pedidos', [
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
                'exception' => $e->getMessage()
            ]);
            return [];
        }
    }
}