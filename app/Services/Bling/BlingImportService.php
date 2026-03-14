<?php

namespace App\Services\Bling;

use App\Models\PedidoBlingStaging;
use App\Models\Venda;
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

    /**
     * Busca pedidos do Bling e joga no staging
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

        do {
            $response = $this->client->getPedidos([
                'dataInicial' => $dataInicio,
                'dataFinal' => $dataFim,
                'pagina' => $pagina,
                'limite' => $limite,
            ]);

            if (!$response['success']) {
                $resultado['erros']++;
                $resultado['mensagens'][] = "Erro na página {$pagina}: HTTP {$response['http_code']}";
                break;
            }

            $pedidos = $response['body']['data'] ?? [];

            if (empty($pedidos)) {
                break;
            }

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
                    continue;
                }

                $pedido = $detalhe['body']['data'] ?? null;
                if (!$pedido) {
                    $resultado['erros']++;
                    continue;
                }

                try {
                    $this->salvarNoStaging($pedido);
                    $resultado['importados']++;
                } catch (\Exception $e) {
                    $resultado['erros']++;
                    $resultado['mensagens'][] = "Erro pedido {$blingId}: {$e->getMessage()}";
                    Log::error("Bling staging error", ['bling_id' => $blingId, 'error' => $e->getMessage()]);
                }
            }

            $pagina++;
        } while (count($pedidos) >= $limite);

        return $resultado;
    }

    private function salvarNoStaging(array $pedido): void
    {
        $canal = $this->identificarCanal($pedido);
        $nfId = $pedido['notaFiscal']['id'] ?? 0; // ID da NF-e no Bling (usado para busca direta /nfe/{id})

        // Extrair itens simplificados
        $itens = [];
        foreach ($pedido['itens'] ?? [] as $item) {
            $sku = $item['codigo'] ?? '';
            $custo = 0;

            // Buscar custo do produto na API
            if ($sku) {
                $produto = $this->client->getProductBySku($sku);
                $custo = (float) ($produto['precoCusto'] ?? 0);
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

        // Pré-calcular comissão e imposto
        $comissaoData = $this->preCalcularComissao($canal, $itens);
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
            'nota_fiscal' => $nfId ?: '',
            'situacao_id' => $pedido['situacao']['id'] ?? null,
            'observacoes' => $pedido['observacoes'] ?? '',
            'itens' => $itens,
            'parcelas' => $parcelas,
            'dados_originais' => $pedido,
            'status' => 'pendente',
        ]);

    }

    /**
     * Busca NF-e vinculada a um pedido do staging (chamado pelo botão na UI)
     * Usa o ID da NF-e salvo em nota_fiscal para busca direta /nfe/{id}
     */
    public static function buscarNfePorPedido(PedidoBlingStaging $staging): bool
    {
        // Tentar pegar o ID da NF-e do campo nota_fiscal ou dos dados_originais
        $nfeId = $staging->nota_fiscal;

        if (!$nfeId || $nfeId == '0') {
            $nfeId = $staging->dados_originais['notaFiscal']['id'] ?? 0;
        }

        if (!$nfeId || $nfeId == 0) {
            return false;
        }

        $client = new BlingClient($staging->bling_account);

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
     * Busca custo dos produtos nos itens de um pedido do staging via API Bling.
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

    private function preCalcularComissao(string $canalNome, array $itens): array
    {
        $canal = \App\Models\CanalVenda::where('nome_canal', $canalNome)->first();

        if (!$canal) {
            return ['comissao_total' => 0, 'subsidio_pix_total' => 0];
        }

        return \App\Services\CalculoComissaoService::calcular($canal->id_canal, $itens);
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
