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

                // Já existe no staging ou já foi importado como venda?
                if (PedidoBlingStaging::where('bling_id', $blingId)->exists()
                    || Venda::where('bling_id', $blingId)->exists()) {
                    $resultado['ignorados']++;
                    continue;
                }

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
        $nf = '';
        if (!empty($pedido['notaFiscal']['id']) && $pedido['notaFiscal']['id'] > 0) {
            $nf = (string) $pedido['notaFiscal']['id'];
        }

        // Extrair itens simplificados
        $itens = [];
        foreach ($pedido['itens'] ?? [] as $item) {
            $itens[] = [
                'codigo' => $item['codigo'] ?? '',
                'descricao' => $item['descricao'] ?? '',
                'quantidade' => $item['quantidade'] ?? 1,
                'valor' => $item['valor'] ?? 0,
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

        PedidoBlingStaging::create([
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
            'nota_fiscal' => $nf,
            'situacao_id' => $pedido['situacao']['id'] ?? null,
            'observacoes' => $pedido['observacoes'] ?? '',
            'itens' => $itens,
            'parcelas' => $parcelas,
            'dados_originais' => $pedido,
            'status' => 'pendente',
        ]);
    }

    private function identificarCanal(array $pedido): string
    {
        $intermediador = $pedido['intermediador']['nomeUsuario'] ?? '';
        if (!empty($intermediador)) {
            return ucfirst($intermediador);
        }

        $obs = $pedido['observacoes'] ?? '';
        if (preg_match('/Via Hub Commerceplus:\s*(\w+)/i', $obs, $matches)) {
            return $matches[1];
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
        $cnpjMap = [
            'primary' => 'Mobilia Decor',
            'secondary' => 'HES Móveis',
        ];
        $razaoSocial = $cnpjMap[$this->accountKey] ?? '';
        $cnpj = \App\Models\Cnpj::where('razao_social', $razaoSocial)->first();

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
