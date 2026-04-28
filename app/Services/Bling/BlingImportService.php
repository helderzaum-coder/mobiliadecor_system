<?php

namespace App\Services\Bling;

use App\Jobs\SyncEstoquePedidoJob;
use App\Models\PedidoBlingStaging;
use App\Models\Venda;
use App\Services\MercadoLivre\MercadoLivreOrderService;
use App\Services\MercadoLivrePlanilhaService;
use App\Services\AprovacaoVendaService;
use App\Services\Shopee\ShopeeService;
use Illuminate\Support\Facades\Log;

class BlingImportService
{
    private BlingClient $client;
    private string $accountKey;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->client = new BlingClient($accountKey);
    }

    public function importarParaStaging(string $dataInicio, string $dataFim, ?string $canalFiltro = null): array
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
            Log::warning("Bling Import: Buscando página {$pagina}...", [
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
                Log::error("Bling Import: Erro ao buscar página", [
                    'pagina' => $pagina,
                    'http_code' => $response['http_code'],
                    'account' => $this->accountKey,
                ]);
                break;
            }

            $pedidos = $response['body']['data'] ?? [];

            if (empty($pedidos)) {
                Log::warning("Bling Import: Nenhum pedido na página {$pagina}, finalizando.", [
                    'account' => $this->accountKey,
                ]);
                break;
            }

            Log::warning("Bling Import: Encontrados " . count($pedidos) . " pedidos na página {$pagina}", [
                'account' => $this->accountKey,
            ]);

            foreach ($pedidos as $pedidoResumo) {
                $blingId = $pedidoResumo['id'] ?? null;
                if (!$blingId) {
                    $resultado['erros']++;
                    continue;
                }

                $existente = PedidoBlingStaging::where('bling_id', $blingId)
                    ->whereIn('status', ['pendente', 'aprovado'])
                    ->exists();

                if ($existente || Venda::where('bling_id', $blingId)->exists()) {
                    $resultado['ignorados']++;
                    $totalProcessados++;
                    continue;
                }

                PedidoBlingStaging::where('bling_id', $blingId)
                    ->where('status', 'rejeitado')
                    ->delete();

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
                    $canalIdentificado = $this->identificarCanal($pedido);

                    // Filtro de canal: pular se não bate
                    if ($canalFiltro && str_replace(' ', '', strtolower($canalIdentificado)) !== str_replace(' ', '', strtolower($canalFiltro))) {
                        $resultado['ignorados']++;
                        $totalProcessados++;
                        continue;
                    }

                    $this->salvarNoStaging($pedido);
                    $resultado['importados']++;
                    $totalProcessados++;

                    if ($resultado['importados'] % 10 === 0) {
                        Log::warning("Bling Import: Progresso - {$resultado['importados']} importados, {$resultado['ignorados']} ignorados", [
                            'account' => $this->accountKey,
                        ]);
                    }
                } catch (\Exception $e) {
                    $resultado['erros']++;
                    $resultado['mensagens'][] = "Erro pedido {$blingId}: {$e->getMessage()}";
                    Log::error("Bling staging error", ['bling_id' => $blingId, 'error' => $e->getMessage()]);
                    $totalProcessados++;
                }
            }

            $pagina++;
        } while (count($pedidos) >= $limite);

        Log::warning("Bling Import: Importação finalizada", [
            'account' => $this->accountKey,
            'importados' => $resultado['importados'],
            'ignorados' => $resultado['ignorados'],
            'erros' => $resultado['erros'],
            'total_processados' => $totalProcessados,
        ]);

        return $resultado;
    }

    public function importarPedidoPorId(int $blingId): array
    {
        $existente = PedidoBlingStaging::where('bling_id', $blingId)
            ->whereIn('status', ['pendente', 'aprovado'])
            ->exists();

        if ($existente || Venda::where('bling_id', $blingId)->exists()) {
            return ['status' => 'ignorado', 'motivo' => 'ja_existe'];
        }

        PedidoBlingStaging::where('bling_id', $blingId)
            ->where('status', 'rejeitado')
            ->delete();

        $detalhe = $this->client->getPedido($blingId);
        if (!$detalhe['success']) {
            return ['status' => 'erro', 'motivo' => 'api_erro_' . ($detalhe['http_code'] ?? 'unknown')];
        }

        $pedido = $detalhe['body']['data'] ?? null;
        if (!$pedido) {
            return ['status' => 'erro', 'motivo' => 'dados_vazios'];
        }

        $staging = $this->salvarNoStaging($pedido);

        // Disparar sincronização de estoque em background
        if ($staging) {
        // Sync automático desabilitado — usar botão "Espelhar Estoque" na página Bling
        // SyncEstoquePedidoJob::dispatch($staging->id);
        }

        return ['status' => 'importado', 'numero' => $pedido['numero'] ?? $blingId];
    }

    private function salvarNoStaging(array $pedido): ?PedidoBlingStaging
    {
        $canal = $this->identificarCanal($pedido);
        $nfId = $pedido['notaFiscal']['id'] ?? 0;

        $etiqueta = $pedido['transporte']['etiqueta'] ?? [];
        $destCep = $etiqueta['cep'] ?? null;
        $destCidade = $etiqueta['municipio'] ?? null;
        $destUf = $etiqueta['uf'] ?? null;
        $pesoBruto = (float) ($pedido['transporte']['pesoBruto'] ?? 0);

        if (empty($destCep) && !empty($pedido['contato']['id'])) {
            $contatoRes = $this->client->get("/contatos/{$pedido['contato']['id']}");
            if ($contatoRes['success']) {
                $endGeral = $contatoRes['body']['data']['endereco']['geral'] ?? [];
                $destCep    = $endGeral['cep'] ?? null;
                $destCidade = $endGeral['municipio'] ?? null;
                $destUf     = $endGeral['uf'] ?? null;
            }
        }

        $itens = [];
        $maiorLargura = 0;
        $maiorAltura = 0;
        $maiorComprimento = 0;

        foreach ($pedido['itens'] ?? [] as $item) {
            $sku = $item['codigo'] ?? '';
            $custo = 0;

            if ($sku) {
                $produto = $this->client->getProductBySku($sku);
                $produtoId = $produto['id'] ?? null;

                if ($produtoId) {
                    $produtoDetalhe = $this->client->getProductById((int) $produtoId);
                    if ($produtoDetalhe) {
                        $custo = (float) ($produtoDetalhe['precoCusto'] ?? 0);
                        $dimensoes = $produtoDetalhe['dimensoes'] ?? [];
                        $largura = (float) ($dimensoes['largura'] ?? 0);
                        $altura = (float) ($dimensoes['altura'] ?? 0);
                        $comprimento = (float) ($dimensoes['profundidade'] ?? 0);

                        $maiorLargura = max($maiorLargura, $largura);
                        $maiorAltura = max($maiorAltura, $altura);
                        $maiorComprimento = max($maiorComprimento, $comprimento);
                    }
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

        $parcelas = [];
        foreach ($pedido['parcelas'] ?? [] as $parcela) {
            $parcelas[] = [
                'data_vencimento' => $parcela['dataVencimento'] ?? '',
                'valor' => $parcela['valor'] ?? 0,
                'observacoes' => $parcela['observacoes'] ?? '',
            ];
        }

        $mlDados = $this->buscarDadosMLPreCalculo($canal, $pedido['numeroLoja'] ?? null, $pedido['numero'] ?? null);

        $isMlMe2Full = in_array($mlDados['ml_tipo_frete'] ?? null, ['ME2', 'FULL']);

        $comissaoData = $this->preCalcularComissao($canal, $itens, $mlDados['ml_tipo_anuncio'] ?? null, $mlDados['ml_tipo_frete'] ?? null, (float) ($pedido['transporte']['frete'] ?? 0));

        // Para ML com dados da API, usar sale_fee real em vez do pré-cálculo
        $mlSaleFee = (float) ($mlDados['ml_sale_fee'] ?? 0);
        $mlFreteCusto = (float) ($mlDados['ml_frete_custo'] ?? 0);
        $mlFreteReceita = (float) ($mlDados['ml_frete_receita'] ?? 0);
        // ME2/FULL: custo líquido do vendedor = list_cost - cost
        $mlFreteCustoLiquido = $isMlMe2Full ? round($mlFreteCusto - $mlFreteReceita, 2) : $mlFreteCusto;
        if ($mlSaleFee > 0) {
            if ($isMlMe2Full) {
                // ME2/FULL: comissão = sale_fee + frete líquido
                $comissaoData['comissao_total'] = round($mlSaleFee + $mlFreteCustoLiquido, 2);
            } else {
                $comissaoData['comissao_total'] = $mlSaleFee;
            }
        }
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
            'custo_frete' => $isMlMe2Full ? 0 : ($pedido['taxas']['custoFrete'] ?? 0),
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

        $isMl = str_contains(strtolower($canal), 'mercado')
            || str_contains(strtolower($canal), 'meli')
            || str_starts_with((string) ($pedido['numeroLoja'] ?? ''), '2000')
            || str_contains(strtolower($pedido['intermediador']['nomeUsuario'] ?? ''), 'meli')
            || str_contains(strtolower($pedido['intermediador']['descricao'] ?? ''), 'mercado');

        if ($isMl) {
            // Se a API do ML já trouxe dados financeiros (net_received_amount > 0),
            // não precisa mais da planilha
            if (empty($mlDados['ml_sale_fee']) || $mlDados['ml_sale_fee'] <= 0) {
                MercadoLivrePlanilhaService::reprocessarPedido($staging);
            }

            // ME2/FULL: auto-aprovar (não precisa cotar frete)
            $tipoFrete = $mlDados['ml_tipo_frete'] ?? null;
            if (in_array($tipoFrete, ['ME2', 'FULL'])) {
                try {
                    AprovacaoVendaService::aprovar($staging);
                    Log::info("ML auto-aprovado: pedido {$staging->numero_pedido} (tipo frete: {$tipoFrete})");
                } catch (\Exception $e) {
                    Log::warning("ML auto-aprovação falhou para pedido {$staging->numero_pedido}: " . $e->getMessage());
                }
            }
        } elseif (str_contains(strtolower($canal), 'shopee')) {
            ShopeeService::reprocessarPedido($staging);
        }

        return $staging;
    }

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

    public static function buscarNfePorPedido(PedidoBlingStaging $staging): bool
    {
        $client = new BlingClient($staging->bling_account);

        $nfeId = $staging->nota_fiscal;

        if (!$nfeId || $nfeId == '0' || $nfeId == '') {
            $nfeId = $staging->dados_originais['notaFiscal']['id'] ?? 0;
        }

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

    public static function buscarDadosEnvio(PedidoBlingStaging $staging): bool
    {
        $client = new BlingClient($staging->bling_account);
        $updates = [];

        $pedido = $staging->dados_originais ?? [];
        $etiqueta = $pedido['transporte']['etiqueta'] ?? [];
        $destCep = $etiqueta['cep'] ?? null;
        $destCidade = $etiqueta['municipio'] ?? null;
        $destUf = $etiqueta['uf'] ?? null;

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

        $pesoBruto = (float) ($pedido['transporte']['pesoBruto'] ?? 0);
        if ($pesoBruto > 0 && !$staging->peso_bruto) {
            $updates['peso_bruto'] = $pesoBruto;
        }

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

        $imposto = \App\Models\ImpostoMensal::where('id_cnpj', $cnpj->id_cnpj)
            ->where('mes_referencia', $mes)
            ->where('ano_referencia', $ano)
            ->first();

        if ($imposto) {
            return (float) $imposto->percentual_imposto;
        }

        $ultimo = \App\Models\ImpostoMensal::where('id_cnpj', $cnpj->id_cnpj)
            ->orderByRaw('ano_referencia DESC, mes_referencia DESC')
            ->first();

        return (float) ($ultimo->percentual_imposto ?? 0);
    }

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
        $obs = $pedido['observacoes'] ?? '';
        if (preg_match('/Via Hub Commerceplus:\s*(.+?)\s*$/im', $obs, $matches)) {
            $canalHub = trim($matches[1]);
            if (str_contains(strtolower($canalHub), 'madeira')) {
                return 'Madeira Madeira';
            }
            return ucfirst(strtolower($canalHub));
        }

        $intermediador = $pedido['intermediador'] ?? [];
        $cnpj = $intermediador['cnpj'] ?? '';
        $nomeUsuario = $intermediador['nomeUsuario'] ?? '';

        // Identificar pelo CNPJ do intermediador
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        if ($cnpjLimpo === '03007331000141') {
            return 'Mercadolivre';
        }
        if ($cnpjLimpo === '02489951000102') {
            return 'Shopee';
        }
        if ($cnpjLimpo === '47960950000121') {
            return 'Magalu';
        }
        if ($cnpjLimpo === '04032433000189') {
            return 'Webcontinental';
        }
        // Madeira Madeira
        if ($cnpjLimpo === '02814497000198') {
            return 'Madeira Madeira';
        }

        // Identificar pelo nome do intermediador
        if (!empty($nomeUsuario)) {
            $nomeLower = strtolower($nomeUsuario);
            if (str_contains($nomeLower, 'mercado') || str_contains($nomeLower, 'meli')) {
                return 'Mercadolivre';
            }
            if (str_contains($nomeLower, 'shopee')) {
                return 'Shopee';
            }
            if (str_contains($nomeLower, 'webcontinental') || str_contains($nomeLower, 'continental')) {
                return 'Webcontinental';
            }
            if (str_contains($nomeLower, 'madeira')) {
                return 'Madeira Madeira';
            }
            return ucfirst($nomeUsuario);
        }

        return 'Direto';
    }

    private function preCalcularComissao(string $canalNome, array $itens, ?string $mlTipoAnuncio = null, ?string $mlTipoFrete = null, float $valorFrete = 0): array
    {
        $canal = \App\Models\CanalVenda::where('nome_canal', $canalNome)->first();

        // Fallback: busca flexível removendo espaços
        if (!$canal) {
            $canal = \App\Models\CanalVenda::get()->first(
                fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($canalNome))
            );
        }

        if (!$canal) {
            return ['comissao_total' => 0, 'subsidio_pix_total' => 0];
        }

        return \App\Services\CalculoComissaoService::calcular($canal->id_canal, $itens, $mlTipoAnuncio, $mlTipoFrete, $valorFrete);
    }

    private function preCalcularImposto(string $canalNome, float $total, float $frete, string $data): array
    {
        $canal = \App\Models\CanalVenda::where('nome_canal', $canalNome)->first();
        if (!$canal) {
            $canal = \App\Models\CanalVenda::get()->first(
                fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($canalNome))
            );
        }
        $tipoNota = $canal->tipo_nota ?? 'cheia';

        $mes = (int) date('m', strtotime($data));
        $ano = (int) date('Y', strtotime($data));

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
