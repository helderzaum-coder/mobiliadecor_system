<?php

namespace App\Services\Bling;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ATENÇÃO: CÓDIGO ESTÁVEL E FUNCIONAL — NÃO SOBRESCREVER           ║
 * ║                                                                    ║
 * ║  Cliente HTTP para API Bling v3. Gerencia:                         ║
 * ║  - Autenticação OAuth (token + refresh automático em 401)          ║
 * ║  - Busca de pedidos, produtos, NF-e                                ║
 * ║  - Rate limit: respeitar sleep(1) entre chamadas                   ║
 * ║                                                                    ║
 * ║  Referência funcional: commit de 23/03/2026                        ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */
class BlingClient
{
    private string $apiBase;
    private string $accountKey;
    private BlingOAuthService $oauth;

    /**
     * Timestamp da última requisição (compartilhado entre instâncias).
     * Garante rate limit de ~3 req/s (intervalo mínimo de 340ms).
     */
    private static float $lastRequestTime = 0;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->apiBase = rtrim(config('bling.api_base'), '/');
        $this->oauth = new BlingOAuthService($accountKey);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $query = [], array $body = []): array
    {
        return $this->request('POST', $path, $query, $body);
    }

    public function put(string $path, array $query = [], array $body = []): array
    {
        return $this->request('PUT', $path, $query, $body);
    }

    public function patch(string $path, array $query = [], array $body = []): array
    {
        return $this->request('PATCH', $path, $query, $body);
    }

    public function patch(string $path, array $query = [], array $body = []): array
    {
        return $this->request('PATCH', $path, $query, $body);
    }

    private function request(string $method, string $path, array $query = [], ?array $body = null, bool $isRetry = false): array
    {
        // Rate limit centralizado: mínimo 340ms entre requisições (≈3 req/s)
        $now = microtime(true);
        $elapsed = $now - self::$lastRequestTime;
        if ($elapsed < 0.34) {
            usleep((int) ((0.34 - $elapsed) * 1_000_000));
        }
        self::$lastRequestTime = microtime(true);

        $token = $this->oauth->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'http_code' => 401,
                'body' => ['error' => "Conta '{$this->accountKey}' não autorizada. Execute a autorização OAuth primeiro."],
            ];
        }

        $url = $this->apiBase . $path;

        $request = Http::withToken($token)
            ->withOptions(['verify' => false])
            ->timeout(60); // Aumentado de 30s para 60s para requisições mais longas

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $query),
            'POST' => $request->post($url, $body),
            'PUT' => $request->put($url, $body),
            'PATCH' => $request->patch($url, $body),
            default => $request->get($url, $query),
        };

        // Se 401 e não é retry, força renovação do token e tenta novamente
        if ($response->status() === 401 && !$isRetry) {
            Log::warning("Bling [{$this->accountKey}]: HTTP 401 ao chamar {$path}, forçando refresh do token...");
            $newToken = $this->oauth->forceRefreshAccessToken();

            if ($newToken) {
                return $this->request($method, $path, $query, $body, true);
            }
        }

        // Se 429 (rate limit), esperar e tentar novamente
        if ($response->status() === 429 && !$isRetry) {
            // Silenciando o log de 429 de Warning para Info para não sujar o log de produção
            // O sistema já lida com isso automaticamente via retry
            Log::info("Bling [{$this->accountKey}]: HTTP 429 (rate limit) ao chamar {$path}, aguardando 2s...");
            sleep(2);
            return $this->request($method, $path, $query, $body, true);
        }

        return [
            'success' => $response->successful(),
            'http_code' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    /**
     * Busca pedidos de venda com paginação
     */
    public function getPedidos(array $params = []): array
    {
        return $this->get('/pedidos/vendas', $params);
    }

    /**
     * Busca um pedido específico
     */
    public function getPedido(int $id): array
    {
        return $this->get("/pedidos/vendas/{$id}");
    }

    /**
     * Busca produto pelo SKU
     */
    public function getProductBySku(string $sku): ?array
    {
        $res = $this->get('/produtos', ['codigo' => $sku, 'limite' => 100]);

        if ($res['success'] && !empty($res['body']['data'])) {
            foreach ($res['body']['data'] as $produto) {
                if ((string) ($produto['codigo'] ?? '') === (string) $sku) {
                    return $produto;
                }
            }
            // API retornou resultados mas nenhum match exato — usar primeiro
            return $res['body']['data'][0];
        }

        // Se falhou ou veio vazio, logar para debug
        Log::warning("Bling [{$this->accountKey}]: getProductBySku '{$sku}' — nenhum resultado", [
            'http_code' => $res['http_code'] ?? null,
            'data_count' => count($res['body']['data'] ?? []),
        ]);

        return null;
    }

    /**
     * Busca produto pelo ID (retorna dimensões completas)
     */
    public function getProductById(int $id): ?array
    {
        $res = $this->get("/produtos/{$id}");

        if ($res['success'] && !empty($res['body']['data'])) {
            return $res['body']['data'];
        }

        if (($res['http_code'] ?? 0) !== 404) {
            Log::warning("Bling [{$this->accountKey}]: Falha ao buscar produto por ID {$id}", [
                'http_code' => $res['http_code'] ?? null,
                'body' => $res['body'] ?? null,
            ]);
        }

        return null;
    }

    public function isAuthorized(): bool
    {
        return $this->oauth->isAuthorized();
    }

    /**
     * Busca NF-e pelo ID
     */
    public function getNfe(int $id): array
    {
        return $this->get("/nfe/{$id}");
    }

    /**
     * Busca NF-es com paginação
     */
    public function getNfes(array $params = []): array
    {
        return $this->get('/nfe', $params);
    }

    /**
     * Busca NF-e vinculada a um pedido pelo numeroPedidoLoja
     */
    public function getNfePorPedidoLoja(string $numeroPedidoLoja): ?array
    {
        $pagina = 1;
        $limite = 100;

        do {
            $response = $this->getNfes(['pagina' => $pagina, 'limite' => $limite]);

            if (!$response['success']) {
                break;
            }

            $nfes = $response['body']['data'] ?? [];

            foreach ($nfes as $nfeResumo) {
                // Buscar detalhe da NF-e para ver o numeroPedidoLoja
                $detalhe = $this->getNfe($nfeResumo['id']);

                if ($detalhe['success']) {
                    $nfe = $detalhe['body']['data'] ?? null;
                    if ($nfe && ($nfe['numeroPedidoLoja'] ?? '') === $numeroPedidoLoja) {
                        return $nfe;
                    }
                }
            }

            $pagina++;
        } while (count($nfes) >= $limite);

        return null;
    }
}
