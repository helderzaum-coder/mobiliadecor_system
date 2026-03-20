<?php

namespace App\Http\Controllers;

use App\Services\MercadoLivre\MercadoLivreOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoLivreAuthController extends Controller
{
    public function authorize(Request $request, string $account)
    {
        $validAccounts = array_keys(config('mercadolivre.accounts'));

        if (!in_array($account, $validAccounts)) {
            abort(404, 'Conta ML não encontrada');
        }

        $oauth = new MercadoLivreOAuthService($account);

        return redirect($oauth->getAuthorizationUrl());
    }

    public function callback(Request $request)
    {
        $code = $request->get('code');
        $accountKey = $request->get('state');
        $oauthError = $request->get('error');
        $oauthErrorDescription = $request->get('error_description');

        if ($oauthError) {
            Log::warning('ML OAuth: callback retornou erro', [
                'error' => $oauthError,
                'error_description' => $oauthErrorDescription,
                'state' => $accountKey,
                'full_query' => $request->query(),
            ]);

            $message = 'Mercado Livre retornou erro na autorização.';
            if ($oauthErrorDescription) {
                $message .= ' ' . $oauthErrorDescription;
            }

            return redirect()->route('filament.helder.pages.mercado-livre-integration')
                ->with('error', $message);
        }

        if (!$code || !$accountKey) {
            return redirect()->route('filament.helder.pages.mercado-livre-integration')
                ->with('error', 'Autorização ML cancelada ou inválida.');
        }

        $oauth = new MercadoLivreOAuthService($accountKey);
        $token = $oauth->exchangeCodeForToken($code);

        if ($token) {
            return redirect()->route('filament.helder.pages.mercado-livre-integration')
                ->with('success', "Conta ML '{$oauth->getAccountName()}' autorizada com sucesso!");
        }

        return redirect()->route('filament.helder.pages.mercado-livre-integration')
            ->with('error', 'Erro ao autorizar conta ML. Verifique os logs.');
    }

    public function status()
    {
        $accounts = [];

        foreach (config('mercadolivre.accounts') as $key => $account) {
            $oauth = new MercadoLivreOAuthService($key);
            $accounts[$key] = [
                'name' => $account['name'],
                'authorized' => $oauth->isAuthorized(),
                'authorize_url' => route('ml.authorize', $key),
            ];
        }

        return response()->json($accounts);
    }
}
