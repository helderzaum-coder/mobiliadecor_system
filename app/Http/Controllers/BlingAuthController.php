<?php

namespace App\Http\Controllers;

use App\Services\Bling\BlingOAuthService;
use Illuminate\Http\Request;

class BlingAuthController extends Controller
{
    /**
     * Redireciona para autorização OAuth do Bling
     */
    public function authorize(Request $request, string $account)
    {
        $validAccounts = array_keys(config('bling.accounts'));

        if (!in_array($account, $validAccounts)) {
            abort(404, 'Conta não encontrada');
        }

        $oauth = new BlingOAuthService($account);

        return redirect($oauth->getAuthorizationUrl());
    }

    /**
     * Callback do OAuth - recebe o code e troca por token
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        $accountKey = $request->get('state');

        if (!$code || !$accountKey) {
            return redirect()->route('filament.helder.pages.dashboard')
                ->with('error', 'Autorização cancelada ou inválida.');
        }

        $oauth = new BlingOAuthService($accountKey);
        $token = $oauth->exchangeCodeForToken($code);

        if ($token) {
            return redirect()->route('filament.helder.pages.dashboard')
                ->with('success', "Conta Bling '{$oauth->getAccountName()}' autorizada com sucesso!");
        }

        return redirect()->route('filament.helder.pages.dashboard')
            ->with('error', 'Erro ao autorizar conta Bling. Verifique os logs.');
    }

    /**
     * Status das contas Bling (API JSON)
     */
    public function status()
    {
        $accounts = [];

        foreach (config('bling.accounts') as $key => $account) {
            $oauth = new BlingOAuthService($key);
            $accounts[$key] = [
                'name' => $account['name'],
                'authorized' => $oauth->isAuthorized(),
                'authorize_url' => route('bling.authorize', $key),
            ];
        }

        return response()->json($accounts);
    }
}
