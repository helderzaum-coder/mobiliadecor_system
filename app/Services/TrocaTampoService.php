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
     * Retorna os grupos disponíveis (Alana, Elisa, Jade, etc.)
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
     * Retorna os tipos de tampo disponíveis para um grupo+cor.
     */
    public static function getTiposTampo(string $grupo, string $cor): array
    {
        return TrocaTampoConfig::where('grupo', $grupo)
            ->where('cor', $cor)
            ->pluck('tipo_tampo', 'tipo_tampo')
            ->mapWithKeys(fn ($v, $k) => [$k => match($k) {
                '4bocas' => '4 Bocas (Cooktop)',
                '5bocas' => '5 Bocas (Cooktop)',
                'liso' => 'Liso (sem recorte)',
                default => $k,
            }])
            ->toArray();
    }

    /**
     * Retorna as fontes possíveis para o tampo necessário.
     * Pode ser estoque avulso ou outra caixa (mesma familia_tampo, mesmo tipo_tampo).
     * Para 4bocas/5bocas: qualquer cor (universal). Para liso: mesma cor_tampo.
     */
    public static function getFontesTampo(string $grupo, string $cor, string $tipoTampo): array
    {
        $produtoVendido = TrocaTampoConfig::where('grupo', $grupo)
            ->where('cor', $cor)
            ->where('tipo_tampo', $tipoTampo)
            ->first();

        if (!$produtoVendido) return [];

        $fontes = [];

        // Opção 1: Tampo vem do estoque avulso
        $fontes["estoque"] = "📦 Estoque avulso: {$produtoVendido->nome_tampo} ({$produtoVendido->sku_tampo})";

        // Opção 2: Tampo vem de outra caixa (mesma familia_tampo, mesmo tipo_tampo)
        $query = TrocaTampoConfig::where('familia_tampo', $produtoVendido->familia_tampo)
            ->where('tipo_tampo', $tipoTampo)
            ->where('id', '!=', $produtoVendido->id);

        // 4bocas e 5bocas são universais (qualquer cor_tampo), liso depende da cor
        if ($tipoTampo === 'liso') {
            $query->where('cor_tampo', $produtoVendido->cor_tampo);
        }

        $compativeis = $query->get();

        foreach ($compativeis as $comp) {
            $fontes["caixa_{$comp->id}"] = "📦 Abrir: {$comp->nome_produto} ({$comp->sku_produto})";
        }

        return $fontes;
    }

    /**
     * Retorna a config do produto vendido.
     */
    public static function getProdutoVendido(string $grupo, string $cor, string $tipoTampo): ?TrocaTampoConfig
    {
        return TrocaTampoConfig::where('grupo', $grupo)
            ->where('cor', $cor)
            ->where('tipo_tampo', $tipoTampo)
            ->first();
    }

    /**
     * Quando a fonte é outra caixa, precisamos saber:
     * - Qual caixa abrir para fornecer a carcaça (mesmo grupo+cor do produto vendido, tipo_tampo diferente)
     * O tampo da caixa aberta vai pra carcaça da fonte, montando um novo produto.
     *
     * Retorna as opções de caixa a abrir para fornecer a carcaça.
     */
    public static function getCaixasParaCarcaca(string $grupo, string $cor, string $tipoTampoVendido): array
    {
        // Caixas do mesmo grupo+cor, mas com tipo_tampo diferente do vendido
        $caixas = TrocaTampoConfig::where('grupo', $grupo)
            ->where('cor', $cor)
            ->where('tipo_tampo', '!=', $tipoTampoVendido)
            ->get();

        return $caixas->mapWithKeys(fn ($c) => [
            $c->id => "{$c->nome_produto} ({$c->sku_produto}) — tampo {$c->tipo_tampo}"
        ])->toArray();
    }

    /**
     * Executa a troca de tampo.
     *
     * Cenário A (fonte = estoque avulso):
     *   - SAÍDA: caixa aberta (grupo+cor, tipo_tampo diferente) -1
     *   - SAÍDA: tampo avulso do estoque -1
     *   - ENTRADA: produto vendido (grupo+cor+tipoTampo) +1
     *   - ENTRADA: tampo que sobrou da caixa aberta vai pro estoque +1
     *
     * Cenário B (fonte = outra caixa):
     *   - SAÍDA: caixa aberta para carcaça (mesmo grupo+cor, tipo diferente) -1
     *   - SAÍDA: caixa fonte do tampo (outra familia/cor, mesmo tipo_tampo) -1
     *   - ENTRADA: produto vendido (grupo+cor+tipoTampo) +1
     *     → tampo veio da fonte, carcaça veio da caixa aberta
     *   - ENTRADA: produto montado com carcaça da fonte + tampo da caixa aberta +1
     *     → carcaça da fonte recebe o tampo que sobrou da caixa aberta
     */
    public function executarTroca(
        int $produtoVendidoId,
        int $caixaAbertaId,
        string $fonteTampo
    ): array {
        $produtoVendido = TrocaTampoConfig::find($produtoVendidoId);
        $caixaAberta = TrocaTampoConfig::find($caixaAbertaId);

        if (!$produtoVendido || !$caixaAberta) {
            return ['success' => false, 'erro' => 'Configuração não encontrada.', 'movimentacoes' => [], 'erros' => ['Configuração não encontrada.']];
        }

        $movimentacoes = [];
        $erros = [];

        if ($fonteTampo === 'estoque') {
            // CENÁRIO A: tampo vem do estoque avulso
            return $this->executarTrocaEstoque($produtoVendido, $caixaAberta);
        }

        // CENÁRIO B: tampo vem de outra caixa
        if (str_starts_with($fonteTampo, 'caixa_')) {
            $fonteId = (int) str_replace('caixa_', '', $fonteTampo);
            $fonteCaixa = TrocaTampoConfig::find($fonteId);

            if (!$fonteCaixa) {
                return ['success' => false, 'movimentacoes' => [], 'erros' => ['Caixa fonte não encontrada.']];
            }

            return $this->executarTrocaCaixa($produtoVendido, $caixaAberta, $fonteCaixa);
        }

        return ['success' => false, 'movimentacoes' => [], 'erros' => ['Fonte de tampo inválida.']];
    }

    /**
     * Cenário A: tampo vem do estoque avulso.
     * - SAÍDA: caixa aberta (ex: Elisa 5 Bocas Branco) -1
     * - SAÍDA: tampo avulso (ex: Tampo 4 Bocas) -1
     * - ENTRADA: produto vendido (ex: Elisa 4 Bocas Branco) +1
     * - ENTRADA: tampo que sobrou da caixa aberta (ex: Tampo 5 Bocas) +1
     */
    private function executarTrocaEstoque(TrocaTampoConfig $produtoVendido, TrocaTampoConfig $caixaAberta): array
    {
        $movimentacoes = [];
        $erros = [];

        // 1. SAÍDA: caixa aberta
        $res = $this->movimentarEstoque($caixaAberta->sku_produto, -1, "Troca tampo: caixa aberta para montar {$produtoVendido->nome_produto}");
        $movimentacoes[] = ['acao' => 'SAÍDA', 'sku' => $caixaAberta->sku_produto, 'nome' => $caixaAberta->nome_produto, 'qtd' => -1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha saída {$caixaAberta->sku_produto}: " . ($res['erro'] ?? '?');

        sleep(1);

        // 2. SAÍDA: tampo avulso do estoque
        $res = $this->movimentarEstoque($produtoVendido->sku_tampo, -1, "Troca tampo: tampo retirado do estoque para montar {$produtoVendido->nome_produto}");
        $movimentacoes[] = ['acao' => 'SAÍDA', 'sku' => $produtoVendido->sku_tampo, 'nome' => $produtoVendido->nome_tampo, 'qtd' => -1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha saída {$produtoVendido->sku_tampo}: " . ($res['erro'] ?? '?');

        sleep(1);

        // 3. ENTRADA: produto vendido montado
        $res = $this->movimentarEstoque($produtoVendido->sku_produto, 1, "Troca tampo: montado com carcaça de {$caixaAberta->nome_produto} + tampo do estoque");
        $movimentacoes[] = ['acao' => 'ENTRADA', 'sku' => $produtoVendido->sku_produto, 'nome' => $produtoVendido->nome_produto, 'qtd' => 1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha entrada {$produtoVendido->sku_produto}: " . ($res['erro'] ?? '?');

        sleep(1);

        // 4. ENTRADA: tampo que sobrou da caixa aberta volta pro estoque
        $res = $this->movimentarEstoque($caixaAberta->sku_tampo, 1, "Troca tampo: tampo devolvido ao estoque de {$caixaAberta->nome_produto}");
        $movimentacoes[] = ['acao' => 'ENTRADA', 'sku' => $caixaAberta->sku_tampo, 'nome' => $caixaAberta->nome_tampo, 'qtd' => 1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha entrada {$caixaAberta->sku_tampo}: " . ($res['erro'] ?? '?');

        $success = empty($erros);
        Log::info("TrocaTampo [{$this->account}]: Estoque " . ($success ? 'OK' : 'COM ERROS'), compact('movimentacoes', 'erros'));

        return ['success' => $success, 'movimentacoes' => $movimentacoes, 'erros' => $erros];
    }

    /**
     * Cenário B: tampo vem de outra caixa.
     * Ex: Vendi Elisa 4 Bocas Branco. Abro Elisa 5 Bocas Branco (carcaça) e Jade 4 Bocas Sav/Preto (fonte tampo).
     * - SAÍDA: caixa aberta (Elisa 5 Bocas Branco) -1
     * - SAÍDA: caixa fonte (Jade 4 Bocas Sav/Preto) -1
     * - ENTRADA: produto vendido (Elisa 4 Bocas Branco) +1
     *   → carcaça do Elisa 5B + tampo 4B do Jade
     * - ENTRADA: produto montado na carcaça da fonte (Jade 5 Bocas Sav/Preto) +1
     *   → carcaça do Jade 4B + tampo 5B do Elisa
     */
    private function executarTrocaCaixa(TrocaTampoConfig $produtoVendido, TrocaTampoConfig $caixaAberta, TrocaTampoConfig $fonteCaixa): array
    {
        $movimentacoes = [];
        $erros = [];

        // Determinar o produto que será montado com a carcaça da fonte + tampo da caixa aberta
        // A carcaça da fonte (ex: Jade) recebe o tampo da caixa aberta (ex: 5 bocas)
        // Então procuramos: mesmo grupo+cor da fonte, tipo_tampo da caixa aberta
        $produtoDestino = TrocaTampoConfig::where('grupo', $fonteCaixa->grupo)
            ->where('cor', $fonteCaixa->cor)
            ->where('tipo_tampo', $caixaAberta->tipo_tampo)
            ->first();

        if (!$produtoDestino) {
            return [
                'success' => false,
                'movimentacoes' => [],
                'erros' => ["Não existe configuração para {$fonteCaixa->grupo} {$fonteCaixa->cor} {$caixaAberta->tipo_tampo}"],
            ];
        }

        // 1. SAÍDA: caixa aberta (fornece carcaça pro produto vendido)
        $res = $this->movimentarEstoque($caixaAberta->sku_produto, -1, "Troca tampo: caixa aberta para fornecer carcaça para {$produtoVendido->nome_produto}");
        $movimentacoes[] = ['acao' => 'SAÍDA', 'sku' => $caixaAberta->sku_produto, 'nome' => $caixaAberta->nome_produto, 'qtd' => -1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha saída {$caixaAberta->sku_produto}: " . ($res['erro'] ?? '?');

        sleep(1);

        // 2. SAÍDA: caixa fonte (fornece tampo pro produto vendido)
        $res = $this->movimentarEstoque($fonteCaixa->sku_produto, -1, "Troca tampo: caixa aberta para fornecer tampo para {$produtoVendido->nome_produto}");
        $movimentacoes[] = ['acao' => 'SAÍDA', 'sku' => $fonteCaixa->sku_produto, 'nome' => $fonteCaixa->nome_produto, 'qtd' => -1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha saída {$fonteCaixa->sku_produto}: " . ($res['erro'] ?? '?');

        sleep(1);

        // 3. ENTRADA: produto vendido (carcaça da caixa aberta + tampo da fonte)
        $res = $this->movimentarEstoque($produtoVendido->sku_produto, 1, "Troca tampo: montado com carcaça de {$caixaAberta->nome_produto} + tampo de {$fonteCaixa->nome_produto}");
        $movimentacoes[] = ['acao' => 'ENTRADA', 'sku' => $produtoVendido->sku_produto, 'nome' => $produtoVendido->nome_produto, 'qtd' => 1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha entrada {$produtoVendido->sku_produto}: " . ($res['erro'] ?? '?');

        sleep(1);

        // 4. ENTRADA: produto destino (carcaça da fonte + tampo da caixa aberta)
        $res = $this->movimentarEstoque($produtoDestino->sku_produto, 1, "Troca tampo: montado com carcaça de {$fonteCaixa->nome_produto} + tampo de {$caixaAberta->nome_produto}");
        $movimentacoes[] = ['acao' => 'ENTRADA', 'sku' => $produtoDestino->sku_produto, 'nome' => $produtoDestino->nome_produto, 'qtd' => 1, 'ok' => $res['success']];
        if (!$res['success']) $erros[] = "Falha entrada {$produtoDestino->sku_produto}: " . ($res['erro'] ?? '?');

        $success = empty($erros);
        Log::info("TrocaTampo [{$this->account}]: Caixa " . ($success ? 'OK' : 'COM ERROS'), compact('movimentacoes', 'erros'));

        return ['success' => $success, 'movimentacoes' => $movimentacoes, 'erros' => $erros];
    }

    /**
     * Movimenta estoque no Bling.
     * $qtd positivo = entrada (E), negativo = saída (S)
     */
    private function movimentarEstoque(string $sku, int $qtd, string $obs): array
    {
        Log::info("TrocaTampo: Buscando SKU '{$sku}' na conta {$this->account}...");

        $produto = $this->client->getProductBySku($sku);

        if (!$produto) {
            sleep(2);
            $produto = $this->client->getProductBySku($sku);
        }

        if (!$produto && ctype_digit($sku) && (float) $sku >= 10000000000) {
            $produto = $this->client->getProductById((int) $sku);
        }

        if (!$produto) {
            Log::error("TrocaTampo: SKU '{$sku}' não encontrado no Bling ({$this->account})");
            return ['success' => false, 'erro' => "Produto SKU {$sku} não encontrado no Bling"];
        }

        Log::info("TrocaTampo: SKU '{$sku}' encontrado — ID: {$produto['id']}, codigo: " . ($produto['codigo'] ?? 'N/A'));

        $produtoId = $produto['id'];
        $operacao = $qtd > 0 ? 'E' : 'S';
        $quantidade = abs($qtd);

        $depositos = $this->client->get('/depositos', ['limite' => 100]);
        $depositoId = null;

        foreach ($depositos['body']['data'] ?? [] as $dep) {
            if (($dep['padrao'] ?? false) === true || ($dep['situacao'] ?? '') === 'A') {
                $depositoId = $dep['id'];
                break;
            }
        }
        if (!$depositoId && !empty($depositos['body']['data'])) {
            $depositoId = $depositos['body']['data'][0]['id'];
        }

        if (!$depositoId) {
            return ['success' => false, 'erro' => 'Depósito não encontrado'];
        }

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

        Log::info("TrocaTampo: POST /estoques SKU {$sku} — op={$operacao} qtd={$quantidade} dep={$depositoId}", [
            'success' => $res['success'],
            'http_code' => $res['http_code'] ?? null,
        ]);

        if ($res['success']) {
            return ['success' => true];
        }

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
}
