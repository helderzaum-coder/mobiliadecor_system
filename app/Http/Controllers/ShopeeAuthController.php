<?php

namespace App\Http\Controllers;

use App\Services\Shopee\ShopeeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopeeAuthController extends Controller
{
    public function authorize()
    {
        $client = new ShopeeClient();
        $url = $client->getAuthUrl();

        Log::info('Shopee: redirecionando para autorização', ['url' => $url]);

        return redirect()->away($url);
    }

    public function callback(Request $request)
    {
        $code   = $request->query('code');
        $shopId = (int) $request->query('shop_id');

        if (!$code || !$shopId) {
            Log::error('Shopee callback: code ou shop_id ausente', $request->all());
            return redirect('/shopee-integration')
                ->with('error', 'Autorização falhou: parâmetros ausentes.');
        }

        Log::info('Shopee callback recebido', ['code' => $code, 'shop_id' => $shopId]);

        $client = new ShopeeClient();
        $result = $client->getAccessToken($code, $shopId);

        if ($result) {
            return redirect('/shopee-integration')
                ->with('success', "Shopee conectada! Shop ID: {$shopId}");
        }

        return redirect('/shopee-integration')
            ->with('error', 'Erro ao obter token da Shopee.');
    }

    public function status()
    {
        $client = new ShopeeClient();
        $shopId = $client->getShopId();

        return response()->json([
            'authorized' => $client->isAuthorized(),
            'shop_id'    => $shopId,
            'sandbox'    => config('shopee.sandbox'),
        ]);
    }
}
