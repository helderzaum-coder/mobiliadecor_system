<?php

namespace App\Services\Shopee;

use App\Models\PedidoBlingStaging;
use Illuminate\Support\Facades\Log;
use Laraditz\Shopee\Facades\Shopee;

class ShopeeService
{
    public static function getShopId(): ?int
    {
        return config('shopee.shop_id') ?: null;
    }

    public static function getOrderDetail(string $orderSn): ?array
    {
        try {
            // CORREÇÃO: Passando o parâmetro correto esperado pelo wrapper/API (String separada por vírgula)
            $response = Shopee::order()->getOrderDetail([
                'order_sn_list' => $orderSn
            ]);

            // CORREÇÃO: O retorno da Shopee v2 encapsula os dados em ['response']['order_list']
            if (isset($response['response']['order_list'][0])) {
                return $response['response']['order_list'][0];
            }

            Log::warning('ShopeeService: pedido não encontrado', ['order_sn' => $orderSn, 'response' => $response]);
            return null;

        } catch (\Throwable $e) {
            Log::error('ShopeeService: erro ao buscar pedido', [
                'order_sn' => $orderSn,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

    public static function getEscrowDetail(string $orderSn): ?array
    {
        try {
            $response = Shopee::payment()->getEscrowDetail([
                'order_sn' => $orderSn
            ]);
            return $response;

        } catch (\Throwable $e) {
            Log::error('ShopeeService: erro ao buscar escrow', [
                'order_sn' => $orderSn,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }

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

        $dadosFinanceiros = self::extrairDadosFinanceiros($orderDetail, $escrowDetail);

        $staging->update([
            'comissao' => $dadosFinanceiros['comissao'] ?? $staging->comissao,
            'subsidio_pix' => $dadosFinanceiros['subsidio_pix'] ?? $staging->subsidio_pix,
            'cupom_shopee' => $dadosFinanceiros['cupom_shopee'] ?? $staging->cupom_shopee,
            'frete' => $dadosFinanceiros['frete'] ?? $staging->frete,
        ]);

        Log::info('ShopeeService: pedido reprocessado via API', [
            'numero_loja' => $staging->numero_loja,
            'comissao' => $dadosFinanceiros['comissao'] ?? 0,
            'subsidio_total' => ($dadosFinanceiros['subsidio_pix'] ?? 0) + ($dadosFinanceiros['cupom_shopee'] ?? 0),
        ]);
    }

    private static function extrairDadosFinanceiros(array $orderDetail, ?array $escrowDetail): array
    {
        $dados = [];

        // Frete real cobrado
        if (isset($orderDetail['actual_shipping_fee'])) {
            $dados['frete'] = (float) $orderDetail['actual_shipping_fee'];
        }

        // CORREÇÃO: Inclusão do nó ['response'] que a API v2 retorna no Escrow
        if ($escrowDetail && isset($escrowDetail['response']['order_income'])) {
            $income = $escrowDetail['response']['order_income'];

            // CORREÇÃO: Soma da Comissão de Marketplace + Taxa de Transação para a 'comissao' total
            $commissionFee = $income['commission_fee'] ?? 0;
            $transactionFee = $income['seller_transaction_fee'] ?? 0;
            $dados['comissao'] = (float) ($commissionFee + $transactionFee);

            // Subsídios Shopee (Rebates / Descontos que a Shopee cobre)
            if (isset($income['shopee_offset'])) {
                $dados['subsidio_pix'] = (float) $income['shopee_offset'];
            }

            // Cupom do marketplace aplicado
            if (isset($income['voucher_from_shopee'])) {
                $dados['cupom_shopee'] = (float) $income['voucher_from_shopee'];
            }
        }

        return $dados;
    }

    public static function isAuthorized(): bool
    {
        try {
            $shopId = self::getShopId();
            if (!$shopId) {
                return false;
            }

            $info = Shopee::make(['shop_id' => $shopId])->shop()->getShopInfo();
            
            // CORREÇÃO: Validação do nó 'response' retornado pela API
            return !empty($info['response']['shop_id']);
        } catch (\Throwable $e) {
            Log::error('ShopeeService::isAuthorized falhou', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    public static function getOrderList(int $timeFrom, int $timeTo): array
    {
        try {
            // CORREÇÃO: Passagem de parâmetros em formato de array associativo, padrão do pacote laraditz
            $response = Shopee::order()->getOrderList([
                'time_range_field' => 'create_time',
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
                'page_size' => 100
            ]);

            return $response['response']['order_list'] ?? [];
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
