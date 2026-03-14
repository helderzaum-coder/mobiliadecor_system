<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CteService
{
    private static string $pastaPendentes = 'ctes/pendentes';
    private static string $pastaProcessados = 'ctes/processados';

    /**
     * Busca CT-e vinculado a um pedido pela chave da NF-e.
     * Retorna os dados do CT-e ou null se não encontrado.
     */
    public static function buscarCtePorPedido(PedidoBlingStaging $staging): ?array
    {
        $chaveNfe = $staging->nfe_chave_acesso;

        if (empty($chaveNfe)) {
            return null;
        }

        $pasta = storage_path('app/' . self::$pastaPendentes);

        if (!File::isDirectory($pasta)) {
            return null;
        }

        $arquivos = File::glob($pasta . '/*.xml');

        foreach ($arquivos as $arquivo) {
            try {
                $xml = simplexml_load_file($arquivo);

                if (!$xml) {
                    continue;
                }

                // Registrar namespaces para busca
                $namespaces = $xml->getNamespaces(true);
                $ns = reset($namespaces) ?: '';

                // Buscar a chave da NF-e dentro do XML
                $chaveEncontrada = self::buscarChaveNfe($xml, $ns);

                if ($chaveEncontrada && $chaveEncontrada === $chaveNfe) {
                    $dados = self::extrairDadosCte($xml, $ns);
                    $dados['arquivo'] = basename($arquivo);
                    $dados['arquivo_path'] = $arquivo;
                    return $dados;
                }
            } catch (\Exception $e) {
                Log::warning("CTE: Erro ao ler XML {$arquivo}", ['error' => $e->getMessage()]);
                continue;
            }
        }

        return null;
    }

    /**
     * Processa CT-e: atualiza custo_frete no staging e move o XML.
     */
    public static function processarCte(PedidoBlingStaging $staging): array
    {
        $cte = self::buscarCtePorPedido($staging);

        if (!$cte) {
            return ['success' => false, 'msg' => 'CT-e não encontrado para esta NF-e.'];
        }

        // Atualizar custo do frete no staging
        $staging->update([
            'custo_frete' => $cte['valor_frete'],
        ]);

        // Mover XML para processados
        $destino = storage_path('app/' . self::$pastaProcessados . '/' . $cte['arquivo']);
        File::move($cte['arquivo_path'], $destino);

        return [
            'success' => true,
            'msg' => "CT-e {$cte['numero']} encontrado. Frete: R$ {$cte['valor_frete']}",
            'dados' => $cte,
        ];
    }

    /**
     * Busca a chave da NF-e dentro do XML do CT-e.
     */
    private static function buscarChaveNfe(\SimpleXMLElement $xml, string $ns): ?string
    {
        // Tentar com namespace
        if ($ns) {
            $xml->registerXPathNamespace('cte', $ns);
            $chaves = $xml->xpath('//cte:infNFe/cte:chave');
            if (!empty($chaves)) {
                return (string) $chaves[0];
            }
        }

        // Tentar sem namespace (fallback)
        $chaves = $xml->xpath('//infNFe/chave');
        if (!empty($chaves)) {
            return (string) $chaves[0];
        }

        // Busca por regex no conteúdo bruto como último recurso
        return null;
    }

    /**
     * Extrai dados relevantes do CT-e.
     */
    private static function extrairDadosCte(\SimpleXMLElement $xml, string $ns): array
    {
        $valor = 0;
        $numero = '';

        if ($ns) {
            $xml->registerXPathNamespace('cte', $ns);

            $vTPrest = $xml->xpath('//cte:vTPrest');
            if (!empty($vTPrest)) {
                $valor = (float) $vTPrest[0];
            }

            $nCT = $xml->xpath('//cte:nCT');
            if (!empty($nCT)) {
                $numero = (string) $nCT[0];
            }
        } else {
            $vTPrest = $xml->xpath('//vTPrest');
            if (!empty($vTPrest)) {
                $valor = (float) $vTPrest[0];
            }

            $nCT = $xml->xpath('//nCT');
            if (!empty($nCT)) {
                $numero = (string) $nCT[0];
            }
        }

        return [
            'numero' => $numero,
            'valor_frete' => round($valor, 2),
        ];
    }
}
