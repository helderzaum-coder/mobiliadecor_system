<?php

namespace App\Http\Controllers;

use App\Services\MercadoLivre\MercadoLivreOAuthService;
use Illuminate\Http\Request;

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

        if (!$code || !$accountKey) {
            return redirect()->route('filament.helder.pages.dashboard')
                ->with('error', 'Autorização ML cancelada ou inválida.');
        }

        $oauth = new MercadoLivreOAuthService($accountKey);
        $token = $oauth->exchangeCodeForToken($code);

        if ($token) {
            return redirect()->route('filament.helder.pages.dashboard')
                ->with('success', "Conta ML '{$oauth->getAccountName()}' autorizada com sucesso!");
        }

        return redirect()->route('filament.helder.pages.dashboard')
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
