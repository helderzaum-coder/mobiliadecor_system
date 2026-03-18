<?php

namespace App\Http\Controllers;

use App\Models\BlingToken;
use App\Services\Bling\BlingOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class BlingAuthController extends Controller
{
    /**
     * Redireciona para autorização OAuth do Bling
     * Apaga token existente para forçar emissão de novo token com scopes atualizados
     */
    public function authorize(Request $request, string $account)
    {
        $validAccounts = array_keys(config('bling.accounts'));

        if (!in_array($account, $validAccounts)) {
            abort(404, 'Conta não encontrada');
        }

        // Apagar token antigo para forçar reautorização com novos scopes
        BlingToken::where('account_key', $account)->delete();

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
        $dashboardRoute = Route::has('filament.helder.pages.dashboard')
            ? route('filament.helder.pages.dashboard')
            : url('/');

        if (!$code || !$accountKey) {
            return redirect($dashboardRoute)
                ->with('error', 'Autorização cancelada ou inválida.');
        }

        $oauth = new BlingOAuthService($accountKey);
        $token = $oauth->exchangeCodeForToken($code);

        if ($token) {
            return redirect($dashboardRoute)
                ->with('success', "Conta Bling '{$oauth->getAccountName()}' autorizada com sucesso!");
        }

        return redirect($dashboardRoute)
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
