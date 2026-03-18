<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingClient;
use Illuminate\Support\Facades\Log;

class CotacaoWhatsappService
{
    /**
     * Gera o texto de cotação para WhatsApp a partir de um pedido staging.
     * Busca no Bling se o produto é simples ou kit para determinar volumes.
     *
     * Retorna array com:
     *   - texto: string formatada para copiar
     *   - volumes: int
     *   - tipo_produto: 'simples' | 'kit' | 'misto'
     *   - itens_detalhes: array com info de cada item
     *   - erro: string|null
     */
    public static function gerar(PedidoBlingStaging $record): array
    {
        $client = new BlingClient($record->bling_account);
        $itens = $record->itens ?? [];

        if (empty($itens)) {
            return ['erro' => 'Pedido sem itens cadastrados.', 'texto' => '', 'volumes' => 0, 'tipo_produto' => null, 'itens_detalhes' => []];
        }

        $totalVolumes = 0;
        $itensDetalhes = [];
        $tiposProduto = [];

        foreach ($itens as $item) {
            $sku = $item['codigo'] ?? '';
            $qtd = (int) ($item['quantidade'] ?? 1);

            if (empty($sku)) {
                $totalVolumes += $qtd;
                $itensDetalhes[] = ['sku' => $sku, 'descricao' => $item['descricao'] ?? '', 'tipo' => 'simples', 'volumes_unitario' => 1, 'quantidade' => $qtd];
                $tiposProduto[] = 'simples';
                continue;
            }

            // Buscar produto no Bling para verificar tipo e volumes
            $produto = $client->getProductBySku($sku);

            if (!$produto) {
                // Fallback: 1 volume por unidade
                $totalVolumes += $qtd;
                $itensDetalhes[] = ['sku' => $sku, 'descricao' => $item['descricao'] ?? '', 'tipo' => 'simples', 'volumes_unitario' => 1, 'quantidade' => $qtd, 'aviso' => 'Produto não encontrado no Bling'];
                $tiposProduto[] = 'simples';
                continue;
            }

            // Verificar se é kit/variação
            $tipo = self::detectarTipo($produto);
            $volumesUnitario = self::calcularVolumes($produto, $client, $tipo);

            $totalVolumes += $volumesUnitario * $qtd;
            $tiposProduto[] = $tipo;
            $itensDetalhes[] = [
                'sku'              => $sku,
                'descricao'        => $produto['nome'] ?? $item['descricao'] ?? '',
                'tipo'             => $tipo,
                'volumes_unitario' => $volumesUnitario,
                'quantidade'       => $qtd,
            ];
        }

        // Tipo geral do pedido
        $tiposUnicos = array_unique($tiposProduto);
        $tipoPedido = count($tiposUnicos) === 1 ? $tiposUnicos[0] : 'misto';

        // Dados do destinatário
        $dadosOriginais = $record->dados_originais ?? [];
        $contato = $dadosOriginais['contato'] ?? [];
        $etiqueta = $dadosOriginais['transporte']['etiqueta'] ?? [];

        $nomeCliente  = $record->cliente_nome ?: ($contato['nome'] ?? '');
        $cpf          = $record->cliente_documento ?: ($contato['numeroDocumento'] ?? '');
        $endereco     = self::formatarEndereco($etiqueta, $dadosOriginais);
        $cidade       = $record->dest_cidade ?: ($etiqueta['municipio'] ?? '');
        $uf           = $record->dest_uf ?: ($etiqueta['uf'] ?? '');
        $cep          = $record->dest_cep ?: ($etiqueta['cep'] ?? '');
        $cepFormatado = preg_replace('/(\d{5})(\d{3})/', '$1-$2', preg_replace('/\D/', '', $cep));

        $peso     = (float) ($record->peso_bruto ?? 0);
        $valorNf  = (float) ($record->nfe_valor ?: $record->total_pedido);

        // Montar texto
        $texto  = strtoupper($nomeCliente) . "\n";
        $texto .= 'CPF: ' . self::formatarCpfCnpj($cpf) . "\n";
        $texto .= $endereco . "\n";
        $texto .= "{$cidade}, {$uf}, {$cepFormatado}\n";
        $texto .= number_format($peso, 2, ',', '.') . 'kg'
            . ' - ' . $totalVolumes . ' vol'
            . ' - ' . number_format($valorNf, 2, ',', '.');

        return [
            'texto'          => $texto,
            'volumes'        => $totalVolumes,
            'tipo_produto'   => $tipoPedido,
            'itens_detalhes' => $itensDetalhes,
            'erro'           => null,
        ];
    }

    private static function detectarTipo(array $produto): string
    {
        // Formato 'E' = produto com estrutura (kit/composição), independente do tipo
        $formato = strtoupper($produto['formato'] ?? '');
        if ($formato === 'E') return 'kit';

        $tipo = strtoupper($produto['tipo'] ?? 'S');
        if ($tipo === 'K') return 'kit';
        if ($tipo === 'V') return 'variacao';
        return 'simples';
    }

    private static function calcularVolumes(array $produto, BlingClient $client, string $tipo): int
    {
        // Buscar detalhe completo para verificar estrutura
        $produtoId = $produto['id'] ?? null;
        if (!$produtoId) return 1;

        $detalhe = $client->getProductById((int) $produtoId);

        // Se tem componentes na estrutura, cada componente = 1 volume
        $componentes = $detalhe['estrutura']['componentes'] ?? [];
        if (!empty($componentes)) {
            return count($componentes);
        }

        // Produto simples: 1 volume
        return 1;
    }

    private static function formatarEndereco(array $etiqueta, array $dadosOriginais): string
    {
        // Tentar pegar endereço completo da etiqueta
        $logradouro  = $etiqueta['endereco'] ?? '';
        $numero      = $etiqueta['numero'] ?? '';
        $complemento = $etiqueta['complemento'] ?? '';
        $bairro      = $etiqueta['bairro'] ?? '';

        // Fallback: dados do contato
        if (empty($logradouro)) {
            $end = $dadosOriginais['contato']['endereco'] ?? [];
            $logradouro  = $end['endereco'] ?? '';
            $numero      = $end['numero'] ?? '';
            $complemento = $end['complemento'] ?? '';
            $bairro      = $end['bairro'] ?? '';
        }

        $partes = array_filter([
            trim($logradouro . ($numero ? ', N° ' . $numero : '')),
            trim($complemento),
            $bairro ? 'Bairro: ' . $bairro : '',
        ]);

        return implode(', ', $partes);
    }

    private static function formatarCpfCnpj(string $doc): string
    {
        $doc = preg_replace('/\D/', '', $doc);
        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }
        if (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }
        return $doc;
    }
}
