<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingClient;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShopeeCorrigirDadosService
{
    /**
     * Processa planilha Shopee e corrige dados dos contatos no Bling.
     *
     * Colunas utilizadas:
     * A  = Nº do pedido
     * AX = Nome do destinatário
     * AY = Telefone
     * AZ = CPF do Comprador
     * BA = Endereço de entrega
     * BB = Cidade (pode estar vazio, usar BD)
     * BC = Bairro
     * BD = Cidade (alternativo)
     * BE = UF
     * BG = CEP
     */
    public static function processar(string $filePath): array
    {
        $resultado = ['corrigidos' => 0, 'erros' => 0, 'nao_encontrados' => 0, 'detalhes' => []];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            return ['corrigidos' => 0, 'erros' => 1, 'nao_encontrados' => 0, 'detalhes' => ["Erro ao ler arquivo: {$e->getMessage()}"]];
        }

        // Agrupar por pedido (pegar apenas primeira linha de cada pedido)
        $pedidos = [];
        $header = null;
        foreach ($rows as $row) {
            if (!$header) { $header = $row; continue; }
            $pedidoId = trim($row['A'] ?? '');
            if (empty($pedidoId) || isset($pedidos[$pedidoId])) continue;
            $pedidos[$pedidoId] = $row;
        }

        // Buscar stagings correspondentes
        $stagings = PedidoBlingStaging::whereIn('numero_loja', array_keys($pedidos))
            ->whereNotNull('bling_id')
            ->get()
            ->keyBy('numero_loja');

        foreach ($pedidos as $pedidoId => $row) {
            $staging = $stagings[$pedidoId] ?? null;
            if (!$staging) {
                $resultado['nao_encontrados']++;
                continue;
            }

            $nome = trim($row['AX'] ?? '');
            $telefone = trim($row['AY'] ?? '');
            $cpf = preg_replace('/\D/', '', trim($row['AZ'] ?? ''));
            $endereco = trim($row['BA'] ?? '');
            $bairro = trim($row['BC'] ?? '');
            $cidade = trim($row['BB'] ?? '') ?: trim($row['BD'] ?? '');
            $uf = trim($row['BE'] ?? '');
            $cep = preg_replace('/\D/', '', trim($row['BG'] ?? ''));

            if (empty($nome)) continue;

            try {
                $client = new BlingClient($staging->bling_account);

                // Buscar pedido no Bling para pegar o ID do contato
                $pedidoBling = $client->getPedido((int) $staging->bling_id);
                if (!$pedidoBling['success']) {
                    $resultado['erros']++;
                    $resultado['detalhes'][] = "{$pedidoId}: erro ao buscar pedido no Bling";
                    continue;
                }

                $contatoId = $pedidoBling['body']['data']['contato']['id'] ?? null;
                if (!$contatoId) {
                    $resultado['erros']++;
                    $resultado['detalhes'][] = "{$pedidoId}: contato não encontrado no pedido";
                    continue;
                }

                // Montar payload para atualizar contato
                $payload = [
                    'nome' => $nome,
                ];

                if ($telefone) {
                    $payload['telefone'] = $telefone;
                }
                if ($cpf) {
                    $payload['numeroDocumento'] = $cpf;
                }

                // Endereço
                if ($endereco || $cidade || $uf || $cep) {
                    // Extrair número do endereço
                    $numero = '';
                    $rua = $endereco;
                    if (preg_match('/,\s*(\d+)/', $endereco, $m)) {
                        $numero = $m[1];
                        $rua = trim(preg_replace('/,\s*\d+.*$/', '', $endereco));
                    }

                    $payload['endereco'] = [
                        'endereco' => $rua,
                        'numero' => $numero,
                        'bairro' => $bairro,
                        'municipio' => $cidade,
                        'uf' => $uf,
                        'cep' => $cep,
                    ];
                }

                $res = $client->put("/contatos/{$contatoId}", [], $payload);

                if ($res['success']) {
                    // Atualizar staging local também
                    $staging->update([
                        'cliente_nome' => $nome,
                        'cliente_documento' => $cpf ? self::formatarCpf($cpf) : $staging->cliente_documento,
                    ]);
                    $resultado['corrigidos']++;
                } else {
                    $erro = $res['body']['error']['message'] ?? $res['body']['message'] ?? "HTTP {$res['http_code']}";
                    $resultado['erros']++;
                    $resultado['detalhes'][] = "{$pedidoId}: {$erro}";
                    Log::warning("ShopeeCorrigir: erro ao atualizar contato", ['pedido' => $pedidoId, 'response' => $res]);
                }

            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = "{$pedidoId}: {$e->getMessage()}";
            }
        }

        return $resultado;
    }

    private static function formatarCpf(string $cpf): string
    {
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
        return $cpf;
    }
}
