<?php

namespace App\Services;

use App\Models\TrocaTampoConfig;
use App\Services\Bling\BlingClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrocaTampoService
{
    private BlingClient $client;
    private string $account;

    public function __construct(string $account = 'primary')
    {
        $this->account = $account;
        $this->client = new BlingClient($account);
    }

    /**
     * Retorna os grupos disponíveis (Alana, Evelyn, Fran, etc.)
     */
    public static function getGrupos(): array
    {
        return TrocaTampoConfig::distinct()->orderBy('grupo')->pluck('grupo', 'grupo')->toArray();
    }

    /**
     * Retorna as cores disponíveis para um grupo.
     */
    public static function getCores(string $grupo): array
    {
        return TrocaTampoConfig::where('grupo', $grupo)
            ->distinct()
            ->orderBy('cor')
            ->pluck('cor', 'cor')
            ->toArray();
    }

    /**
     * Retorna os produtos (tipo_tampo) disponíveis para um grupo+cor.
     */
    public static function getProdutos(string $grupo, string $cor): array
    {
        return TrocaTampoConfig::where('grupo', $grupo)
            ->where('cor', $cor)
            ->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->nome_produto . ' (' . $c->tipo_tampo . ')'])
            ->toArray();
    }

    /**
     * Retorna os destinos possíveis para o tampo que sobra.
     * Pode ir para estoque avulso ou para outra caixa compatível.
     */
    public static function getDestinosTampo(int $configOrigemId): array
    {
        $origem = TrocaTampoConfig::find($configOrigemId);
        if (!$origem) return [];

        $destinos = [];

        // Opção 1: Tampo volta pro estoque avulso
        $destinos["estoque_{$configOrigemId}"] = "Estoque avulso: {$origem->nome_tampo} ({$origem->sku_tampo})";

        // Opção 2: Tampo vai pra outra caixa (mesma familia_tampo, mesma cor_tampo, mesmo tipo_tampo)
        $compativeis = TrocaTampoConfig::where('cor_tampo', $origem->cor_tampo)
            ->where('tipo_tampo', $origem->tipo_tampo)
            ->where('familia_tampo', $origem->familia_tampo)
            ->where('id', '!=', $configOrigemId)
            ->get();

        foreach ($compativeis as $comp) {
            $destinos["caixa_{$comp->id}"] = "Montar: {$comp->nome_produto} ({$comp->sku_produto})";
        }

        return $destinos;
    }

    /**
     * Executa a troca de tampo.
     *
     * @param int $caixaAbertaId  Config ID do produto cuja caixa será aberta
     * @param int $tampoUsadoId   Config ID do produto que será montado (tampo que entra)
     * @param string $destinoTampo "estoque_{id}" ou "caixa_{id}"
     * @return array Resultado com movimentações
     */
    public function executarTroca(int $caixaAbertaId, int $tampoUsadoId, string $destinoTampo): array
    {
        $caixaAberta = TrocaTampoConfig::find($caixaAbertaId);
        $tampoUsado = TrocaTampoConfig::find($tampoUsadoId);

        if (!$caixaAberta || !$tampoUsado) {
            return ['success' => false, 'erro' => 'Configuração não encontrada.'];
        }

        // Validar que são do mesmo grupo e cor (mesma caixa física)
        if ($caixaAberta->grupo !== $tampoUsado->grupo || $caixaAberta->cor !== $tampoUsado->cor) {
            return ['success' => false, 'erro' => 'Caixa aberta e produto montado devem ser do mesmo grupo e cor.'];
        }

        $movimentacoes = [];
        $erros = [];

        // 1. Dar baixa na caixa aberta (produto original): -1
        $res = $this->movimentarEstoque($caixaAberta->sku_produto, -1, "Troca tampo: caixa aberta para montar {$tampoUsado->nome_produto}");
        $movimentacoes[] = [
            'acao' => 'SAÍDA',
            'sku' => $caixaAberta->sku_produto,
            'nome' => $caixaAberta->nome_produto,
            'qtd' => -1,
            'ok' => $res['success'],
        ];
        if (!$res['success']) $erros[] = "Falha ao dar baixa em {$caixaAberta->sku_produto}: " . ($res['erro'] ?? 'erro desconhecido');

        sleep(1); // Rate limit Bling: 3 req/s

        // 2. Dar baixa no tampo usado (que vai pra montagem): -1
        $res = $this->movimentarEstoque($tampoUsado->sku_tampo, -1, "Troca tampo: usado para montar {$tampoUsado->nome_produto}");
        $movimentacoes[] = [
            'acao' => 'SAÍDA',
            'sku' => $tampoUsado->sku_tampo,
            'nome' => $tampoUsado->nome_tampo,
            'qtd' => -1,
            'ok' => $res['success'],
        ];
        if (!$res['success']) $erros[] = "Falha ao dar baixa em {$tampoUsado->sku_tampo}: " . ($res['erro'] ?? 'erro desconhecido');

        sleep(1);

        // 3. Dar entrada no produto montado: +1
        $res = $this->movimentarEstoque($tampoUsado->sku_produto, 1, "Troca tampo: montado a partir de {$caixaAberta->nome_produto}");
        $movimentacoes[] = [
            'acao' => 'ENTRADA',
            'sku' => $tampoUsado->sku_produto,
            'nome' => $tampoUsado->nome_produto,
            'qtd' => 1,
            'ok' => $res['success'],
        ];
        if (!$res['success']) $erros[] = "Falha ao dar entrada em {$tampoUsado->sku_produto}: " . ($res['erro'] ?? 'erro desconhecido');

        sleep(1);

        // 4. Destino do tampo que sobrou da caixa aberta
        if (str_starts_with($destinoTampo, 'estoque_')) {
            // Tampo volta pro estoque avulso: +1
            $res = $this->movimentarEstoque($caixaAberta->sku_tampo, 1, "Troca tampo: tampo devolvido ao estoque");
            $movimentacoes[] = [
                'acao' => 'ENTRADA',
                'sku' => $caixaAberta->sku_tampo,
                'nome' => $caixaAberta->nome_tampo,
                'qtd' => 1,
                'ok' => $res['success'],
            ];
            if (!$res['success']) $erros[] = "Falha ao dar entrada em {$caixaAberta->sku_tampo}: " . ($res['erro'] ?? 'erro desconhecido');
        } elseif (str_starts_with($destinoTampo, 'caixa_')) {
            // Tampo vai montar outra caixa
            $destinoConfigId = (int) str_replace('caixa_', '', $destinoTampo);
            $destinoConfig = TrocaTampoConfig::find($destinoConfigId);

            if ($destinoConfig) {
                // Precisa de outra caixa aberta para receber este tampo
                // Dar baixa na caixa destino (será aberta): -1
                // Na verdade, se o tampo vai pra outra caixa, significa que já existe
                // uma caixa aberta esperando esse tampo. Então:
                // +1 no produto destino montado
                $res = $this->movimentarEstoque($destinoConfig->sku_produto, 1, "Troca tampo: montado com tampo de {$caixaAberta->nome_produto}");
                $movimentacoes[] = [
                    'acao' => 'ENTRADA',
                    'sku' => $destinoConfig->sku_produto,
                    'nome' => $destinoConfig->nome_produto,
                    'qtd' => 1,
                    'ok' => $res['success'],
                ];
                if (!$res['success']) $erros[] = "Falha ao dar entrada em {$destinoConfig->sku_produto}: " . ($res['erro'] ?? 'erro desconhecido');
            }
        }

        $success = empty($erros);

        Log::info("TrocaTampo [{$this->account}]: " . ($success ? 'OK' : 'COM ERROS'), [
            'caixa_aberta' => $caixaAberta->sku_produto,
            'produto_montado' => $tampoUsado->sku_produto,
            'destino_tampo' => $destinoTampo,
            'movimentacoes' => $movimentacoes,
            'erros' => $erros,
        ]);

        return [
            'success' => $success,
            'movimentacoes' => $movimentacoes,
            'erros' => $erros,
        ];
    }

    /**
     * Movimenta estoque no Bling.
     * $qtd positivo = entrada (E), negativo = saída (B)
     */
    private function movimentarEstoque(string $sku, int $qtd, string $obs): array
    {
        Log::info("TrocaTampo: Buscando SKU '{$sku}' na conta {$this->account}...");

        $busca = $this->buscarProduto($sku);
        $produto = $busca['produto'] ?? null;

        // Retry se falhou (pode ser rate limit)
        if (!$produto && empty($busca['erro'])) {
            Log::warning("TrocaTampo: SKU '{$sku}' não encontrado na 1ª tentativa, aguardando 2s...");
            sleep(2);
            $busca = $this->buscarProduto($sku);
            $produto = $busca['produto'] ?? null;
        }

        if (!$produto) {
            if (!empty($busca['erro'])) {
                Log::error("TrocaTampo: Falha ao buscar SKU '{$sku}' no Bling ({$this->account})", [
                    'erro' => $busca['erro'],
                    'http_code' => $busca['http_code'] ?? null,
                ]);

                return ['success' => false, 'erro' => $busca['erro']];
            }

            Log::error("TrocaTampo: SKU '{$sku}' não encontrado no Bling ({$this->account}) após 2 tentativas");
            return ['success' => false, 'erro' => "Produto SKU {$sku} não encontrado no Bling"];
        }

        Log::info("TrocaTampo: SKU '{$sku}' encontrado — ID: {$produto['id']}, codigo: " . ($produto['codigo'] ?? 'N/A'));

        $produtoId = $produto['id'];
        $operacao = $qtd > 0 ? 'E' : 'B';
        $quantidade = abs($qtd);

        // Buscar depósito padrão (o primeiro retornado, que geralmente é o principal)
        $depositos = $this->client->get('/depositos', ['limite' => 100]);
        $depositoId = null;
        $depositoNome = '';

        // Tentar encontrar o depósito padrão/principal
        foreach ($depositos['body']['data'] ?? [] as $dep) {
            if (($dep['padrao'] ?? false) === true || ($dep['situacao'] ?? '') === 'A') {
                $depositoId = $dep['id'];
                $depositoNome = $dep['descricao'] ?? 'N/A';
                break;
            }
        }

        // Fallback: primeiro depósito
        if (!$depositoId && !empty($depositos['body']['data'])) {
            $depositoId = $depositos['body']['data'][0]['id'];
            $depositoNome = $depositos['body']['data'][0]['descricao'] ?? 'N/A';
        }

        if (!$depositoId) {
            return ['success' => false, 'erro' => 'Depósito não encontrado'];
        }

        Log::info("TrocaTampo: Usando depósito ID {$depositoId} ({$depositoNome}) para SKU {$sku}");

        // Anti-loop: marcar para o webhook de estoque ignorar
        $cacheKey = "bling_sync_loop_{$this->account}_{$produtoId}";
        Cache::put($cacheKey, true, 60);

        $res = $this->client->post('/estoques', [], [
            'produto'     => ['id' => $produtoId],
            'deposito'    => ['id' => (int) $depositoId],
            'operacao'    => $operacao,
            'preco'       => 0,
            'custo'       => 0,
            'quantidade'  => $quantidade,
            'observacoes' => $obs,
        ]);

        Log::info("TrocaTampo: POST /estoques SKU {$sku} — operacao={$operacao} qtd={$quantidade} deposito={$depositoId}", [
            'success' => $res['success'],
            'http_code' => $res['http_code'] ?? null,
            'body' => $res['body'] ?? null,
        ]);

        if ($res['success']) {
            return ['success' => true];
        }

        // Rate limit retry
        if ($res['http_code'] === 429) {
            sleep(2);
            $res = $this->client->post('/estoques', [], [
                'produto'     => ['id' => $produtoId],
                'deposito'    => ['id' => (int) $depositoId],
                'operacao'    => $operacao,
                'preco'       => 0,
                'custo'       => 0,
                'quantidade'  => $quantidade,
                'observacoes' => $obs,
            ]);
            if ($res['success']) return ['success' => true];
        }

        return ['success' => false, 'erro' => "HTTP {$res['http_code']}: " . json_encode($res['body'] ?? [])];
    }

    private function buscarProduto(string $sku): array
    {
        $res = $this->client->get('/produtos', ['codigo' => $sku, 'limite' => 100]);

        if (($res['http_code'] ?? null) === 401) {
            return [
                'produto' => null,
                'erro' => "Conta Bling '{$this->account}' não autorizada. Refaça a autorização OAuth.",
                'http_code' => 401,
            ];
        }

        if (!$res['success']) {
            return [
                'produto' => null,
                'erro' => "Falha ao consultar produtos no Bling (HTTP {$res['http_code']}).",
                'http_code' => $res['http_code'] ?? null,
            ];
        }

        foreach ($res['body']['data'] ?? [] as $produto) {
            if ((string) ($produto['codigo'] ?? '') === (string) $sku) {
                return ['produto' => $produto, 'erro' => null, 'http_code' => $res['http_code'] ?? 200];
            }
        }

        if (!empty($res['body']['data'][0])) {
            return ['produto' => $res['body']['data'][0], 'erro' => null, 'http_code' => $res['http_code'] ?? 200];
        }

        if (ctype_digit($sku)) {
            $resById = $this->client->get("/produtos/{$sku}");

            if (($resById['http_code'] ?? null) === 401) {
                return [
                    'produto' => null,
                    'erro' => "Conta Bling '{$this->account}' não autorizada. Refaça a autorização OAuth.",
                    'http_code' => 401,
                ];
            }

            if ($resById['success'] && !empty($resById['body']['data'])) {
                return [
                    'produto' => $resById['body']['data'],
                    'erro' => null,
                    'http_code' => $resById['http_code'] ?? 200,
                ];
            }
        }

        return ['produto' => null, 'erro' => null, 'http_code' => $res['http_code'] ?? 200];
    }
}
