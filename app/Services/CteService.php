<?php

namespace App\Services;

use App\Models\Cte;
use App\Models\Venda;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CteService
{
    private static string $pastaPendentes = 'ctes/pendentes';
    private static string $pastaProcessados = 'ctes/processados';

    /**
     * Processa todos os XMLs pendentes e salva no banco.
     * Retorna resumo do processamento.
     */
    public static function processarXmlsPendentes(): array
    {
        $pasta = storage_path('app/' . self::$pastaPendentes);
        $resultado = ['importados' => 0, 'erros' => 0, 'duplicados' => 0, 'detalhes' => []];

        if (!File::isDirectory($pasta)) {
            File::makeDirectory($pasta, 0755, true);
            return $resultado;
        }

        $arquivos = File::glob($pasta . '/*.xml');

        foreach ($arquivos as $arquivo) {
            try {
                $dados = self::extrairDadosXml($arquivo);

                if (!$dados || empty($dados['chave_nfe'])) {
                    $resultado['erros']++;
                    $resultado['detalhes'][] = basename($arquivo) . ': chave NF-e não encontrada';
                    continue;
                }

                // Verificar duplicata pelo arquivo
                $jaExiste = Cte::where('arquivo', basename($arquivo))->exists();
                if ($jaExiste) {
                    $resultado['duplicados']++;
                    // Mover mesmo assim
                    self::moverParaProcessados($arquivo);
                    continue;
                }

                Cte::create([
                    'numero_cte' => $dados['numero_cte'],
                    'chave_cte' => $dados['chave_cte'],
                    'chave_nfe' => $dados['chave_nfe'],
                    'valor_frete' => $dados['valor_frete'],
                    'remetente' => $dados['remetente'],
                    'destinatario' => $dados['destinatario'],
                    'transportadora' => $dados['transportadora'],
                    'arquivo' => basename($arquivo),
                ]);

                self::moverParaProcessados($arquivo);
                $resultado['importados']++;
                $resultado['detalhes'][] = basename($arquivo) . ": CT-e {$dados['numero_cte']} - R$ " . number_format($dados['valor_frete'], 2, ',', '.');

            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = basename($arquivo) . ': ' . $e->getMessage();
                Log::warning("CTE: Erro ao processar XML", ['arquivo' => basename($arquivo), 'error' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    /**
     * Busca CT-es no banco pela chave NF-e de uma venda.
     */
    public static function buscarCtesParaVenda(Venda $venda): array
    {
        $chaveNfe = $venda->nfe_chave_acesso;
        if (empty($chaveNfe)) {
            return [];
        }

        return Cte::where('chave_nfe', $chaveNfe)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Aplica o valor do CT-e na venda (soma todos os CT-es da NF-e).
     */
    public static function aplicarCteNaVenda(Venda $venda): array
    {
        $ctes = self::buscarCtesParaVenda($venda);

        if (empty($ctes)) {
            return ['success' => false, 'msg' => 'Nenhum CT-e encontrado para esta NF-e.'];
        }

        $totalFrete = 0;
        $numeros = [];
        $cteIds = [];
        foreach ($ctes as $cte) {
            $totalFrete += (float) $cte['valor_frete'];
            $numeros[] = $cte['numero_cte'];
            $cteIds[] = $cte['id'];
        }

        $venda->update([
            'valor_frete_transportadora' => round($totalFrete, 2),
            'frete_pago' => true,
        ]);

        // Marcar CTEs como utilizados
        Cte::whereIn('id', $cteIds)->update([
            'utilizado' => true,
            'venda_id' => $venda->id_venda,
        ]);

        VendaRecalculoService::recalcularMargens($venda);

        $qtd = count($ctes);
        $nums = implode(', ', $numeros);
        return [
            'success' => true,
            'msg' => "{$qtd} CT-e(s) encontrado(s): {$nums} — Frete total: R$ " . number_format($totalFrete, 2, ',', '.'),
        ];
    }

    /**
     * Processa CT-e para um PedidoBlingStaging (compatibilidade com fluxo antigo).
     */
    public static function processarCte(\App\Models\PedidoBlingStaging $staging): array
    {
        $chaveNfe = $staging->nfe_chave_acesso;
        if (empty($chaveNfe)) {
            return ['success' => false, 'msg' => 'CT-e não encontrado para esta NF-e.'];
        }

        $ctes = Cte::where('chave_nfe', $chaveNfe)->get();
        if ($ctes->isEmpty()) {
            return ['success' => false, 'msg' => 'CT-e não encontrado para esta NF-e.'];
        }

        $totalFrete = $ctes->sum('valor_frete');
        $staging->update(['custo_frete' => round($totalFrete, 2)]);

        return [
            'success' => true,
            'msg' => "CT-e encontrado. Frete: R$ " . number_format($totalFrete, 2, ',', '.'),
        ];
    }

    /**
     * Extrai dados de um XML de CT-e.
     */
    private static function extrairDadosXml(string $arquivo): ?array
    {
        $xml = simplexml_load_file($arquivo);
        if (!$xml) return null;

        $namespaces = $xml->getNamespaces(true);
        $ns = reset($namespaces) ?: '';

        $dados = [
            'numero_cte' => '',
            'chave_cte' => '',
            'chave_nfe' => '',
            'valor_frete' => 0,
            'remetente' => '',
            'destinatario' => '',
            'transportadora' => '',
            'data_emissao' => null,
        ];

        if ($ns) {
            $xml->registerXPathNamespace('cte', $ns);

            $nCT = $xml->xpath('//cte:nCT');
            $dados['numero_cte'] = !empty($nCT) ? (string) $nCT[0] : '';

            $chaveNfe = $xml->xpath('//cte:infNFe/cte:chave');
            $dados['chave_nfe'] = !empty($chaveNfe) ? (string) $chaveNfe[0] : '';

            $vTPrest = $xml->xpath('//cte:vTPrest');
            $dados['valor_frete'] = !empty($vTPrest) ? round((float) $vTPrest[0], 2) : 0;

            $chaveCte = $xml->xpath('//cte:infCte/@Id');
            if (!empty($chaveCte)) {
                $dados['chave_cte'] = str_replace('CTe', '', (string) $chaveCte[0]);
            }

            $rem = $xml->xpath('//cte:rem/cte:xNome');
            $dados['remetente'] = !empty($rem) ? (string) $rem[0] : '';

            $dest = $xml->xpath('//cte:dest/cte:xNome');
            $dados['destinatario'] = !empty($dest) ? (string) $dest[0] : '';

            $emit = $xml->xpath('//cte:emit/cte:xNome');
            $dados['transportadora'] = !empty($emit) ? (string) $emit[0] : '';

            // Data de emissão (dhEmi)
            $dhEmi = $xml->xpath('//cte:dhEmi');
            if (!empty($dhEmi)) {
                try {
                    $dados['data_emissao'] = \Carbon\Carbon::parse((string) $dhEmi[0])->format('Y-m-d');
                } catch (\Exception $e) {}
            }
        } else {
            $nCT = $xml->xpath('//nCT');
            $dados['numero_cte'] = !empty($nCT) ? (string) $nCT[0] : '';

            $chaveNfe = $xml->xpath('//infNFe/chave');
            $dados['chave_nfe'] = !empty($chaveNfe) ? (string) $chaveNfe[0] : '';

            $vTPrest = $xml->xpath('//vTPrest');
            $dados['valor_frete'] = !empty($vTPrest) ? round((float) $vTPrest[0], 2) : 0;
        }

        return $dados;
    }

    private static function moverParaProcessados(string $arquivo): void
    {
        $destino = storage_path('app/' . self::$pastaProcessados);
        if (!File::isDirectory($destino)) {
            File::makeDirectory($destino, 0755, true);
        }
        File::move($arquivo, $destino . '/' . basename($arquivo));
    }
}
