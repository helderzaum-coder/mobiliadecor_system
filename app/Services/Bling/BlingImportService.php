<?php

namespace App\Services\Bling;

use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use App\Services\MercadoLivre\MercadoLivreOrderService;
use App\Services\MercadoLivrePlanilhaService;
use App\Services\Shopee\ShopeeService;
use Illuminate\Support\Facades\Log;

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  ATENÇÃO: CÓDIGO ESTÁVEL E FUNCIONAL — NÃO SOBRESCREVER           ║
 * ║                                                                    ║
 * ║  Este serviço é responsável por:                                   ║
 * ║  1. Importação de pedidos do Bling para o staging                  ║
 * ║  2. Busca de NF-e vinculada ao pedido                              ║
 * ║  3. Busca de dados de envio (CEP, dimensões)                       ║
 * ║  4. Busca de custo dos produtos via API Bling                      ║
 * ║  5. Pré-cálculo de comissão e imposto                              ║
 * ║  6. Integração com ML (dados pré-cálculo: rebate, frete, sale_fee)║
 * ║  7. Reprocessamento automático de planilhas (ML e Shopee)          ║
 * ║                                                                    ║
 * ║  Qualquer alteração deve ser testada com pedidos reais de:         ║
 * ║  - Shopee (com e sem planilha)                                     ║
 * ║  - Mercado Livre (ME1 e ME2)                                       ║
 * ║  - Vendas diretas                                                  ║
 * ║                                                                    ║
 * ║  Referência funcional: commit de 23/03/2026                        ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */
class BlingImportService
{
    private BlingClient $client;
    private string $accountKey;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->client = new BlingClient($accountKey);
    }

    /**
     * Busca pedidos do Bling e joga no staging.
     *
     * ⚠️ NÃO ALTERAR: Fluxo de paginação e rate limit (sleep 1s) estável.
     * Cada pedido é buscado individualmente (getPedido) para obter detalhes completos.
     * Pedidos já existentes (pendente/aprovado) ou já em vendas são ignorados.
     * Pedidos rejeitados são apagados e reimportados limpos.
     */
    public function importarParaStaging(string $dataInicio, string $dataFim): array
    {
        $resultado = [
            'importados' => 0,
            'ignorados' => 0,
            'erros' => 0,
            'mensagens' => [],
        ];

        $pagina = 1;
        $limite = 100;
        $totalProcessados = 0;

        do {
            \Illuminate\Support\Facades\Log::warning("Bling Import: Buscando página {$pagina}...", [
                'account' => $this->accountKey,
            ]);

            $response = $this->client->getPedidos([
                'dataInicial' => $dataInicio,
                'dataFinal' => $dataFim,
                'pagina' => $pagina,
                'limite' => $limite,
            ]);

            if (!$response['success']) {
                $resultado['erros']++;
                $resultado['mensagens'][] = "Erro na página {$pagina}: HTTP {$response['http_code']}";
                \Illuminate\Support\Facades\Log::error("Bling Import: Erro ao buscar página", [
                    'pagina' => $pagina,
                    'http_code' => $response['http_code'],
                    'account' => $this->accountKey,
                ]);
                break;
            }

            $pedidos = $response['body']['data'] ?? [];

            if (empty($pedidos)) {
                \Illuminate\Support\Facades\Log::warning("Bling Import: Nenhum pedido na página {$pagina}, finalizando.", [
                    'account' => $this->accountKey,
                ]);
                break;
            }

            \Illuminate\Support\Facades\Log::warning("Bling Import: Encontrados {" . count($pedidos) . "} pedidos na página {$pagina}", [
                'account' => $this->accountKey,
            ]);

            foreach ($pedidos as $pedidoResumo) {
                $blingId = $pedidoResumo['id'] ?? null;
                if (!$blingId) {
                    $resultado['erros']++;
                    continue;
                }

                // Já existe no staging (pendente/aprovado) ou já foi importado como venda?
                $existente = PedidoBlingStaging::where('bling_id', $blingId)
                    ->whereIn('status', ['pendente', 'aprovado'])
                    ->exists();

                if ($existente || Venda::where('bling_id', $blingId)->exists()) {
                    $resultado['ignorados']++;
                    $totalProcessados++;
                    continue;
                }

                // Se existia como rejeitado, apagar para reimportar limpo
                PedidoBlingStaging::where('bling_id', $blingId)
                    ->where('status', 'rejeitado')
                    ->delete();

                // Buscar detalhes completos
                $detalhe = $this->client->getPedido($blingId);
                if (!$detalhe['success']) {
                    $resultado['erros']++;
                    $resultado['mensagens'][] = "Erro ao buscar pedido {$blingId}";
                    $totalProcessados++;
                    continue;
                }

                $pedido = $detalhe['body']['data'] ?? null;
                if (!$pedido) {
                    $resultado['erros']++;
                    $totalProcessados++;
                    continue;
                }

                try {
                    $this->salvarNoStaging($pedido);
                    $resultado['importados']++;
                    $totalProcessados++;
                    
                    // Log a cada 10 pedidos
                    if ($resultado['importados'] % 10 === 0) {
                        \Illuminate\Support\Facades\Log::warning("Bling Import: Progresso - {" . $resultado['importados'] . "} importados, {" . $resultado['ignorados'] . "} ignorados", [
                            'account' => $this->accountKey,
                        ]);
                    }
                } catch (\Exception $e) {
                    $resultado['erros']++;
                    $resultado['mensagens'][] = "Erro pedido {$blingId}: {$e->getMessage()}";
                    \Illuminate\Support\Facades\Log::error("Bling staging error", ['bling_id' => $blingId, 'error' => $e->getMessage()]);
                    $totalProcessados++;
                }
            }

            $pagina++;
        } while (count($pedidos) >= $limite);

        \Illuminate\Support\Facades\Log::warning("Bling Import: Importação finalizada", [
            'account' => $this->accountKey,
            'importados' => $resultado['importados'],
            'ignorados' => $resultado['ignorados'],
            'erros' => $resultado['erros'],
            'total_processados' => $totalProcessados,
        ]);

        return $resultado;
    }

    /**
     * Importa um único pedido pelo ID do Bling para o staging.
     * Usado pelo webhook e por importações avulsas.
     * Retorna true se importou, false se ignorou (já existe).
     *
     * ⚠️ NÃO ALTERAR: Usado pelo BlingWebhookController para importação automática.
     */
    public function importarPedidoPorId(int $blingId): array
    {
        // Já existe no staging (pendente/aprovado) ou já foi importado como venda?
        $existente = PedidoBlingStaging::where('bling_id', $blingId)
            ->whereIn('status', ['pendente', 'aprovado'])
            ->exists();

        if ($existente || Venda::where('bling_id', $blingId)->exists()) {
            return ['status' => 'ignorado', 'motivo' => 'ja_existe'];
        }

        // Se existia como rejeitado, apagar para reimportar limpo
        PedidoBlingStaging::where('bling_id', $blingId)
            ->where('status', 'rejeitado')
            ->delete();

        // Buscar detalhes completos
        $detalhe = $this->client->getPedido($blingId);
        if (!$detalhe['success']) {
            return ['status' => 'erro', 'motivo' => 'api_erro_' . ($detalhe['http_code'] ?? 'unknown')];
        }

        $pedido = $detalhe['body']['data'] ?? null;
        if (!$pedido) {
            return ['status' => 'erro', 'motivo' => 'dados_vazios'];
        }

        $this->salvarNoStaging($pedido);
        return ['status' => 'importado', 'numero' => $pedido['numero'] ?? $blingId];
    }

    /**
     * Salva pedido no staging com todos os dados necessários.
     *
     * ⚠️ NÃO ALTERAR ESTA FUNÇÃO SEM TESTAR COM PEDIDOS REAIS.
     * Fluxo crítico:
     *  1. Identifica canal (Hub Commerceplus → intermediador → Direto)
     *  2. Extrai endereço de envio (etiqueta → contato fallback)
     *  3. Busca custo e dimensões de cada item via API Bling (com rate limit)
     *  4. Busca dados ML pré-cálculo (tipo anúncio, frete, rebate, sale_fee)
     *  5. Pré-calcula comissão e imposto
     *  6. Cria registro no staging
     *  7. Reprocessa planilhas armazenadas (ML rebate / Shopee dados financeiros)
     *
     * IMPORTANTE: A planilha Shopee NÃO sobrescreve itens do Bling — só dados financeiros.
     */
    private function salvarNoStaging(array $pedido): void
    {
        $canal = $this->identificarCanal($pedido);
        $nfId = $pedido['notaFiscal']['id'] ?? 0; // ID da NF-e no Bling (usado para busca direta /nfe/{id})

        // Extrair dados de envio do transporte.etiqueta
        $etiqueta = $pedido['transporte']['etiqueta'] ?? [];
        $destCep = $etiqueta['cep'] ?? null;
        $destCidade = $etiqueta['municipio'] ?? null;
        $destUf = $etiqueta['uf'] ?? null;
        $pesoBruto = (float) ($pedido['transporte']['pesoBruto'] ?? 0);

        // Fallback: buscar endereço do contato se etiqueta estiver vazia
        if (empty($destCep) && !empty($pedido['contato']['id'])) {
            $contatoRes = $this->client->get("/contatos/{$pedido['contato']['id']}");
            if ($contatoRes['success']) {
                $endGeral = $contatoRes['body']['data']['endereco']['geral'] ?? [];
                $destCep    = $endGeral['cep'] ?? null;
                $destCidade = $endGeral['municipio'] ?? null;
                $destUf     = $endGeral['uf'] ?? null;
            }
        }

        // Extrair itens simplificados + buscar dimensões do produto
        $itens = [];
        $maiorLargura = 0;
        $maiorAltura = 0;
        $maiorComprimento = 0;

        foreach ($pedido['itens'] ?? [] as $item) {
            $sku = $item['codigo'] ?? '';
            $custo = 0;

            // Buscar custo do produto na API (lista)
            if ($sku) {
                $produto = $this->client->getProductBySku($sku);
                $custo = (float) ($produto['precoCusto'] ?? 0);

                // Buscar dimensões via /produtos/{id} (detalhe completo)
                $produtoId = $produto['id'] ?? null;
                if ($produtoId) {
                    $produtoDetalhe = $this->client->getProductById((int) $produtoId);
                    $dimensoes = $produtoDetalhe['dimensoes'] ?? [];
                    $largura = (float) ($dimensoes['largura'] ?? 0);
                    $altura = (float) ($dimensoes['altura'] ?? 0);
                    $comprimento = (float) ($dimensoes['profundidade'] ?? 0);

                    // Usar a maior dimensão entre todos os itens
                    $maiorLargura = max($maiorLargura, $largura);
                    $maiorAltura = max($maiorAltura, $altura);
                    $maiorComprimento = max($maiorComprimento, $comprimento);
                }
            }

            $itens[] = [
                'codigo' => $sku,
                'descricao' => $item['descricao'] ?? '',
                'quantidade' => $item['quantidade'] ?? 1,
                'valor' => $item['valor'] ?? 0,
                'custo' => $custo,
            ];
        }

        // Extrair parcelas
        $parcelas = [];
        foreach ($pedido['parcelas'] ?? [] as $parcela) {
            $parcelas[] = [
                'data_vencimento' => $parcela['dataVencimento'] ?? '',
                'valor' => $parcela['valor'] ?? 0,
                'observacoes' => $parcela['observacoes'] ?? '',
            ];
        }

        // Pré-buscar dados do Mercado Livre (tipo anúncio, frete, rebate)
        $mlDados = $this->buscarDadosMLPreCalculo($canal, $pedido['numeroLoja'] ?? null, $pedido['numero'] ?? null);

        // Pré-calcular comissão e imposto (com dados ML se disponíveis)
        $comissaoData = $this->preCalcularComissao($canal, $itens, $mlDados['ml_tipo_anuncio'] ?? null, $mlDados['ml_tipo_frete'] ?? null);
        $impostoData = $this->preCalcularImposto(
            $canal,
            (float) ($pedido['total'] ?? 0),
            (float) ($pedido['transporte']['frete'] ?? 0),
            $pedido['data'] ?? now()->toDateString()
        );

        $staging = PedidoBlingStaging::create([
            'bling_id' => $pedido['id'],
            'bling_account' => $this->accountKey,
            'numero_pedido' => $pedido['numero'] ?? 0,
            'numero_loja' => $pedido['numeroLoja'] ?? null,
            'data_pedido' => $pedido['data'] ?? now()->toDateString(),
            'cliente_nome' => $pedido['contato']['nome'] ?? '',
            'cliente_documento' => $pedido['contato']['numeroDocumento'] ?? '',
            'total_produtos' => $pedido['totalProdutos'] ?? 0,
            'total_pedido' => $pedido['total'] ?? 0,
            'frete' => $pedido['transporte']['frete'] ?? 0,
            'custo_frete' => $pedido['taxas']['custoFrete'] ?? 0,
            'comissao_calculada' => $comissaoData['comissao_total'],
            'subsidio_pix' => $comissaoData['subsidio_pix_total'],
            'base_imposto' => $impostoData['base_calculo'],
            'percentual_imposto' => $impostoData['percentual'],
            'valor_imposto' => $impostoData['valor_imposto'],
            'canal' => $canal,
            'ml_tipo_anuncio' => $mlDados['ml_tipo_anuncio'] ?? null,
            'ml_tipo_frete' => $mlDados['ml_tipo_frete'] ?? null,
            'ml_tem_rebate' => $mlDados['ml_tem_rebate'] ?? false,
            'ml_valor_rebate' => $mlDados['ml_valor_rebate'] ?? 0,
            'ml_sale_fee' => $mlDados['ml_sale_fee'] ?? 0,
            'ml_frete_custo' => $mlDados['ml_frete_custo'] ?? 0,
            'ml_frete_receita' => $mlDados['ml_frete_receita'] ?? 0,
            'ml_order_id' => $mlDados['ml_order_id'] ?? null,
            'ml_shipping_id' => $mlDados['ml_shipping_id'] ?? null,
            'dest_cep' => $destCep,
            'dest_cidade' => $destCidade,
            'dest_uf' => $destUf,
            'peso_bruto' => $pesoBruto > 0 ? $pesoBruto : null,
            'embalagem_largura' => $maiorLargura > 0 ? $maiorLargura : null,
            'embalagem_altura' => $maiorAltura > 0 ? $maiorAltura : null,
            'embalagem_comprimento' => $maiorComprimento > 0 ? $maiorComprimento : null,
            'nota_fiscal' => $nfId ?: '',
            'situacao_id' => $pedido['situacao']['id'] ?? null,
            'observacoes' => $pedido['observacoes'] ?? '',
            'itens' => $itens,
            'parcelas' => $parcelas,
            'dados_originais' => $pedido,
            'status' => 'pendente',
        ]);

        // Reprocessar planilhas armazenadas (ML rebate / Shopee dados)
        $isMl = str_contains(strtolower($canal), 'mercado')
            || str_contains(strtolower($canal), 'meli')
            || str_starts_with((string) ($pedido['numeroLoja'] ?? ''), '2000')
            || str_contains(strtolower($pedido['intermediador']['nomeUsuario'] ?? ''), 'meli')
            || str_contains(strtolower($pedido['intermediador']['descricao'] ?? ''), 'mercado');

        if ($isMl) {
            MercadoLivrePlanilhaService::reprocessarPedido($staging);
        } elseif (str_contains(strtolower($canal), 'shopee')) {
            ShopeeService::reprocessarPedido($staging);
        }
    }

    /**
     * Busca dados do ML antes do cálculo de comissão.
     *
     * ⚠️ NÃO ALTERAR: Detecção de canal ML usa múltiplos critérios:
     *  - nome do canal contém 'mercado' ou 'meli'
     *  - numeroLoja começa com '2000'
     *  - conta secondary (HES Móveis = sempre ML)
     * Retorna dados reais da API ML: tipo_anuncio, tipo_frete, sale_fee, rebate, frete.
     */
    private function buscarDadosMLPreCalculo(string $canal, ?string $numeroLoja, ?string $numeroPedido): array
    {
        $vazio = [
            'ml_tipo_anuncio' => null,
            'ml_tipo_frete' => null,
            'ml_tem_rebate' => false,
            'ml_valor_rebate' => 0,
            'ml_sale_fee' => 0,
            'ml_frete_custo' => 0,
            'ml_frete_receita' => 0,
            'ml_order_id' => null,
            'ml_shipping_id' => null,
        ];

        if (!str_contains(strtolower($canal), 'mercado')
            && !str_contains(strtolower($canal), 'meli')
            && !str_starts_with((string) ($numeroLoja ?? ''), '2000')
            && $this->accountKey !== 'secondary') {
            return $vazio;
        }

        $orderId = $numeroLoja ?? $numeroPedido;
        if (!$orderId) {
            return $vazio;
        }

        try {
            $mlAccount = match ($this->accountKey) {
                'primary' => 'primary',
                'secondary' => 'secondary',
                default => 'primary',
            };

            $mlService = new MercadoLivreOrderService($mlAccount);
            $dados = $mlService->buscarDadosPedido((string) $orderId);

            if ($dados) {
                Log::info("ML: Dados pré-cálculo para pedido {$orderId}", $dados);
                return [
                    'ml_tipo_anuncio' => $dados['tipo_anuncio'],
                    'ml_tipo_frete' => $dados['tipo_frete'],
                    'ml_tem_rebate' => $dados['tem_rebate'],
                    'ml_valor_rebate' => $dados['valor_rebate'],
                    'ml_sale_fee' => $dados['sale_fee'],
                    'ml_frete_custo' => $dados['frete_ml_custo'],
                    'ml_frete_receita' => $dados['frete_ml_receita'],
                    'ml_order_id' => $dados['order_id'],
                    'ml_shipping_id' => $dados['shipping_id'],
                ];
            }
        } catch (\Exception $e) {
            Log::warning("ML: Erro ao buscar dados pré-cálculo para pedido {$orderId}: " . $e->getMessage());
        }

        return $vazio;
    }

    /**
     * Busca NF-e vinculada a um pedido do staging (chamado pelo botão na UI).
     * Usa o ID da NF-e salvo em nota_fiscal para busca direta /nfe/{id}.
     *
     * ⚠️ NÃO ALTERAR: Se o ID da NF-e não existe no staging, re-consulta o pedido
     * na API do Bling para obter o notaFiscal.id atualizado (NF emitida após importação).
     * Recalcula imposto com base no valor da NF-e.
     */
    public static function buscarNfePorPedido(PedidoBlingStaging $staging): bool
    {
        $client = new BlingClient($staging->bling_account);

        // Tentar pegar o ID da NF-e do campo nota_fiscal ou dos dados_originais
        $nfeId = $staging->nota_fiscal;

        if (!$nfeId || $nfeId == '0' || $nfeId == '') {
            $nfeId = $staging->dados_originais['notaFiscal']['id'] ?? 0;
        }

        // Se ainda não tem NF ID, re-consultar o pedido na API do Bling
        if (!$nfeId || $nfeId == 0) {
            try {
                $response = $client->getPedido((int) $staging->bling_id);
                if ($response['success']) {
                    $pedidoAtualizado = $response['body']['data'] ?? null;
                    if ($pedidoAtualizado) {
                        $nfeId = $pedidoAtualizado['notaFiscal']['id'] ?? 0;
                        Log::info("Bling: Re-fetch pedido {$staging->bling_id} -> notaFiscal.id = {$nfeId}");
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Bling: Erro ao re-fetch pedido {$staging->bling_id}: " . $e->getMessage());
            }
        }

        if (!$nfeId || $nfeId == 0) {
            return false;
        }

        try {
            $response = $client->getNfe((int) $nfeId);

            if ($response['success']) {
                $nfe = $response['body']['data'] ?? null;

                if ($nfe) {
                    $valorNota = (float) ($nfe['valorNota'] ?? 0);

                    // Recalcular imposto: base = valor da NF-e, percentual do mês
                    $percentual = self::buscarPercentualImposto($staging);
                    $valorImposto = round($valorNota * ($percentual / 100), 2);

                    $staging->update([
                        'nota_fiscal' => $nfe['numero'] ?? $staging->nota_fiscal,
                        'nfe_numero' => $nfe['numero'] ?? '',
                        'nfe_chave_acesso' => $nfe['chaveAcesso'] ?? '',
                        'nfe_valor' => $valorNota,
                        'nfe_xml_url' => $nfe['xml'] ?? '',
                        'nfe_pdf_url' => $nfe['linkPDF'] ?? '',
                        'base_imposto' => $valorNota,
                        'percentual_imposto' => $percentual,
                        'valor_imposto' => $valorImposto,
                    ]);
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Bling: Erro ao buscar NF-e {$nfeId} para pedido {$staging->bling_id}", [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Busca dados de envio (CEP, cidade, UF, dimensões) para um pedido no staging.
     * Tenta: 1) dados_originais salvos, 2) re-fetch do pedido via API, 3) contato via API.
     *
     * ⚠️ NÃO ALTERAR: Fallback em 3 níveis garante que o endereço seja encontrado.
     * Dimensões são buscadas do produto via API (maior dimensão entre todos os itens).
     */
    public static function buscarDadosEnvio(PedidoBlingStaging $staging): bool
    {
        $client = new BlingClient($staging->bling_account);
        $updates = [];

        // 1) Tentar dos dados_originais salvos
        $pedido = $staging->dados_originais ?? [];
        $etiqueta = $pedido['transporte']['etiqueta'] ?? [];
        $destCep = $etiqueta['cep'] ?? null;
        $destCidade = $etiqueta['municipio'] ?? null;
        $destUf = $etiqueta['uf'] ?? null;

        // 2) Se não tem na etiqueta, re-fetch do pedido via API
        if (empty($destCep) && $staging->bling_id) {
            $res = $client->getPedido((int) $staging->bling_id);
            if ($res['success']) {
                $pedido = $res['body']['data'] ?? [];
                $etiqueta = $pedido['transporte']['etiqueta'] ?? [];
                $destCep = $etiqueta['cep'] ?? null;
                $destCidade = $etiqueta['municipio'] ?? null;
                $destUf = $etiqueta['uf'] ?? null;
            }
        }

        // 3) Fallback: buscar endereço do contato
        if (empty($destCep)) {
            $contatoId = $pedido['contato']['id'] ?? null;
            if ($contatoId) {
                $contatoRes = $client->get("/contatos/{$contatoId}");
                if ($contatoRes['success']) {
                    $endGeral = $contatoRes['body']['data']['endereco']['geral'] ?? [];
                    $destCep    = $endGeral['cep'] ?? null;
                    $destCidade = $endGeral['municipio'] ?? null;
                    $destUf     = $endGeral['uf'] ?? null;
                }
            }
        }

        if ($destCep) {
            $updates['dest_cep'] = $destCep;
            $updates['dest_cidade'] = $destCidade;
            $updates['dest_uf'] = $destUf;
        }

        // Peso bruto
        $pesoBruto = (float) ($pedido['transporte']['pesoBruto'] ?? 0);
        if ($pesoBruto > 0 && !$staging->peso_bruto) {
            $updates['peso_bruto'] = $pesoBruto;
        }

        // Dimensões: buscar do produto se não tem
        if (!$staging->embalagem_largura) {
            $maiorLargura = 0;
            $maiorAltura = 0;
            $maiorComprimento = 0;

            foreach ($pedido['itens'] ?? $staging->itens ?? [] as $item) {
                $sku = $item['codigo'] ?? '';
                if (!$sku) continue;

                $produto = $client->getProductBySku($sku);
                $produtoId = $produto['id'] ?? null;
                if ($produtoId) {
                    $detalhe = $client->getProductById((int) $produtoId);
                    $dim = $detalhe['dimensoes'] ?? [];
                    $maiorLargura = max($maiorLargura, (float) ($dim['largura'] ?? 0));
                    $maiorAltura = max($maiorAltura, (float) ($dim['altura'] ?? 0));
                    $maiorComprimento = max($maiorComprimento, (float) ($dim['profundidade'] ?? 0));
                }
            }

            if ($maiorLargura > 0) $updates['embalagem_largura'] = $maiorLargura;
            if ($maiorAltura > 0) $updates['embalagem_altura'] = $maiorAltura;
            if ($maiorComprimento > 0) $updates['embalagem_comprimento'] = $maiorComprimento;
        }

        if (!empty($updates)) {
            $staging->update($updates);
            Log::info("BlingImport: Dados de envio atualizados para pedido {$staging->numero_pedido}", $updates);
            return true;
        }

        return false;
    }


    /**
     * Busca custo dos produtos nos itens de um pedido do staging via API Bling.
     *
     * ⚠️ NÃO ALTERAR: Busca precoCusto pelo SKU e atualiza o array de itens no staging.
     */
    public static function buscarCustosProdutos(PedidoBlingStaging $staging): int
    {
        $client = new BlingClient($staging->bling_account);
        $itens = $staging->itens ?? [];
        $atualizados = 0;

        foreach ($itens as &$item) {
            $sku = $item['codigo'] ?? '';
            if (empty($sku)) {
                continue;
            }

            $produto = $client->getProductBySku($sku);
            if ($produto && isset($produto['precoCusto'])) {
                $item['custo'] = (float) $produto['precoCusto'];
                $atualizados++;
            }
        }

        $staging->update(['itens' => $itens]);

        return $atualizados;
    }

    /**
     * Busca o percentual de imposto do mês para a conta do pedido.
     * Se não existir para o mês do pedido, usa o último mês cadastrado (estimativa).
     */
    private static function buscarPercentualImposto(PedidoBlingStaging $staging): float
    {
        $cnpjId = config("bling.accounts.{$staging->bling_account}.cnpj_id");
        $cnpj = \App\Models\Cnpj::find($cnpjId);

        if (!$cnpj) {
            return 0;
        }

        $data = $staging->data_pedido;
        $mes = (int) $data->format('m');
        $ano = (int) $data->format('Y');

        // Buscar percentual do mês exato
        $imposto = \App\Models\ImpostoMensal::where('id_cnpj', $cnpj->id_cnpj)
            ->where('mes_referencia', $mes)
            ->where('ano_referencia', $ano)
            ->first();

        if ($imposto) {
            return (float) $imposto->percentual_imposto;
        }

        // Fallback: último mês cadastrado (estimativa até o escritório fechar)
        $ultimo = \App\Models\ImpostoMensal::where('id_cnpj', $cnpj->id_cnpj)
            ->orderByRaw('ano_referencia DESC, mes_referencia DESC')
            ->first();

        return (float) ($ultimo->percentual_imposto ?? 0);
    }

    /**
     * Reprocessa impostos de todos os pedidos pendentes de um mês/ano/conta
     * Chamado quando o escritório cadastra o percentual real do mês
     */
    public static function reprocessarImpostos(string $blingAccount, int $mes, int $ano): array
    {
        $cnpjId = config("bling.accounts.{$blingAccount}.cnpj_id");
        $cnpj = \App\Models\Cnpj::find($cnpjId);

        if (!$cnpj) {
            return ['atualizados' => 0, 'erro' => 'CNPJ não encontrado'];
        }

        $imposto = \App\Models\ImpostoMensal::where('id_cnpj', $cnpj->id_cnpj)
            ->where('mes_referencia', $mes)
            ->where('ano_referencia', $ano)
            ->first();

        if (!$imposto) {
            return ['atualizados' => 0, 'erro' => 'Percentual não cadastrado para este mês'];
        }

        $percentual = (float) $imposto->percentual_imposto;

        // Buscar pedidos do mês/conta que tenham NF-e vinculada
        $pedidos = PedidoBlingStaging::where('bling_account', $blingAccount)
            ->whereMonth('data_pedido', $mes)
            ->whereYear('data_pedido', $ano)
            ->where('status', 'pendente')
            ->get();

        $atualizados = 0;
        foreach ($pedidos as $pedido) {
            $base = (float) $pedido->nfe_valor ?: (float) $pedido->total_pedido;
            $valorImposto = round($base * ($percentual / 100), 2);

            $pedido->update([
                'base_imposto' => $base,
                'percentual_imposto' => $percentual,
                'valor_imposto' => $valorImposto,
            ]);
            $atualizados++;
        }

        return ['atualizados' => $atualizados, 'percentual' => $percentual];
    }


    private function identificarCanal(array $pedido): string
    {
        // Prioridade 1: Extrair canal real das observações (Hub Commerceplus)
        $obs = $pedido['observacoes'] ?? '';
        if (preg_match('/Via Hub Commerceplus:\s*(\w+)/i', $obs, $matches)) {
            return ucfirst(strtolower($matches[1]));
        }

        // Prioridade 2: Intermediador (fallback)
        $intermediador = $pedido['intermediador']['nomeUsuario'] ?? '';
        if (!empty($intermediador)) {
            return ucfirst($intermediador);
        }

        return 'Direto';
    }

    private function preCalcularComissao(string $canalNome, array $itens, ?string $mlTipoAnuncio = null, ?string $mlTipoFrete = null): array
    {
        $canal = \App\Models\CanalVenda::where('nome_canal', $canalNome)->first();

        if (!$canal) {
            return ['comissao_total' => 0, 'subsidio_pix_total' => 0];
        }

        return \App\Services\CalculoComissaoService::calcular($canal->id_canal, $itens, $mlTipoAnuncio, $mlTipoFrete);
    }

    private function preCalcularImposto(string $canalNome, float $total, float $frete, string $data): array
    {
        $canal = \App\Models\CanalVenda::where('nome_canal', $canalNome)->first();
        $tipoNota = $canal->tipo_nota ?? 'cheia';

        // Buscar percentual de imposto do mês/CNPJ
        $mes = (int) date('m', strtotime($data));
        $ano = (int) date('Y', strtotime($data));

        // Determinar CNPJ pela conta
        $cnpjId = config("bling.accounts.{$this->accountKey}.cnpj_id");
        $cnpj = \App\Models\Cnpj::find($cnpjId);

        $percentual = 0;
        if ($cnpj) {
            $imposto = \App\Models\ImpostoMensal::where('id_cnpj', $cnpj->id_cnpj)
                ->where('mes_referencia', $mes)
                ->where('ano_referencia', $ano)
                ->first();
            $percentual = (float) ($imposto->percentual_imposto ?? 0);
        }

        return \App\Services\CalculoComissaoService::calcularBaseImposto(
            $tipoNota, $total, $frete, $percentual
        );
    }
}
