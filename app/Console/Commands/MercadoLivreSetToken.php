<?php

namespace App\Console\Commands;

use App\Models\MercadoLivreToken;
use App\Services\MercadoLivre\MercadoLivreOAuthService;
use Illuminate\Console\Command;

class MercadoLivreSetToken extends Command
{
    protected $signature = 'ml:set-token {account=primary} {--refresh-token=}';
    protected $description = 'Configura o refresh token do Mercado Livre e obtém um access token válido';

    public function handle(): int
    {
        $account = $this->argument('account');
        $refreshToken = $this->option('refresh-token');

        if (!$refreshToken) {
            $refreshToken = $this->ask('Informe o refresh token do Mercado Livre');
        }

        if (!$refreshToken) {
            $this->error('Refresh token é obrigatório.');
            return 1;
        }

        $config = config("mercadolivre.accounts.{$account}");
        if (!$config) {
            $this->error("Conta '{$account}' não configurada.");
            return 1;
        }

        // Salvar token temporário para poder usar o refresh
        MercadoLivreToken::updateOrCreate(
            ['account_key' => $account],
            [
                'access_token' => 'pending',
                'refresh_token' => $refreshToken,
                'user_id' => $config['user_id'] ?? '',
                'expires_at' => now()->subMinute(), // forçar refresh
            ]
        );

        // Tentar renovar para obter access token válido
        $oauth = new MercadoLivreOAuthService($account);
        $accessToken = $oauth->getAccessToken();

        if ($accessToken) {
            $this->info("Token ML configurado com sucesso para: {$config['name']}");
            $this->info("Access token obtido e salvo.");
            return 0;
        }

        $this->error('Erro ao renovar token. Verifique client_id, client_secret e refresh_token no .env');
        return 1;
    }
}
