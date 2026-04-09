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
                $tipoPessoa = strlen($cpf) > 11 ? 'J' : 'F';
                $payload = [
                    'nome' => $nome,
                    'tipo' => $tipoPessoa,
                    'situacao' => 'A',
                ];

                if ($telefone) {
                    // Remover código do país (55) se presente
                    $tel = preg_replace('/\D/', '', $telefone);
                    if (strlen($tel) >= 12 && str_starts_with($tel, '55')) {
                        $tel = substr($tel, 2);
                    }
                    $payload['telefone'] = $tel;
                }
                if ($cpf) {
                    $payload['numeroDocumento'] = $cpf;
                }

                // Endereço
                $enderecoCompleto = trim($row['BA'] ?? '');
                if ($endereco || $cidade || $uf || $cep) {
                    $ufSigla = self::ufParaSigla($uf);

                    // Extrair número do endereço (segundo elemento separado por vírgula)
                    $numero = '';
                    $rua = $endereco;
                    $partes = array_map('trim', explode(',', $endereco));
                    if (count($partes) >= 2) {
                        $rua = $partes[0];
                        // Segundo elemento pode ser o número
                        if (preg_match('/^\d+\w*$/', $partes[1])) {
                            $numero = $partes[1];
                        }
                    }

                    $payload['endereco'] = [
                        'endereco' => $rua,
                        'numero' => $numero,
                        'bairro' => $bairro,
                        'municipio' => $cidade,
                        'uf' => $ufSigla,
                        'cep' => $cep,
                        'complemento' => $enderecoCompleto ? "Endereço completo: {$enderecoCompleto}" : '',
                    ];
                }

                $res = $client->put("/contatos/{$contatoId}", [], $payload);

                if ($res['success']) {
                    // Atualizar staging local
                    $staging->update([
                        'cliente_nome' => $nome,
                        'cliente_documento' => $cpf ? self::formatarCpf($cpf) : $staging->cliente_documento,
                    ]);

                    // Atualizar venda se já foi aprovada
                    \App\Models\Venda::where('bling_id', $staging->bling_id)->update([
                        'cliente_nome' => $nome,
                        'cliente_documento' => $cpf ? self::formatarCpf($cpf) : null,
                    ]);

                    // Atualizar observações do pedido no Bling com dados financeiros
                    self::atualizarObservacoesPedido($client, $staging, $pedidoId);

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

    /**
     * Atualiza observações do pedido no Bling com dados financeiros da planilha.
     */
    private static function atualizarObservacoesPedido(BlingClient $client, PedidoBlingStaging $staging, string $pedidoId): void
    {
        try {
            $planilha = \App\Models\PlanilhaShopeeDado::where('numero_pedido', $pedidoId)->first();
            $originais = $planilha?->dados_originais ?? [];

            $subtotal = (float) ($originais['total_produtos'] ?? $staging->total_produtos ?? 0);
            $frete = (float) ($originais['frete'] ?? $staging->frete ?? 0);
            $faturar = round($subtotal / 2, 2);

            $obs = "=== DADOS SHOPEE ===\n"
                . "Subtotal: R$ " . number_format($subtotal, 2, ',', '.') . "\n"
                . "Faturar (meia nota): R$ " . number_format($faturar, 2, ',', '.') . "\n"
                . "Frete recebido: R$ " . number_format($frete, 2, ',', '.');

            // Buscar pedido completo no Bling (PATCH exige campos obrigatórios)
            $pedidoBling = $client->getPedido((int) $staging->bling_id);
            if (!$pedidoBling['success']) return;

            $pedidoData = $pedidoBling['body']['data'] ?? [];
            $contatoId = $pedidoData['contato']['id'] ?? null;
            if (!$contatoId) return;

            // Montar itens no formato que o Bling aceita
            $itens = [];
            foreach ($pedidoData['itens'] ?? [] as $item) {
                $itens[] = [
                    'id' => $item['id'] ?? 0,
                    'quantidade' => $item['quantidade'] ?? 1,
                    'valor' => $item['valor'] ?? 0,
                    'descricao' => $item['descricao'] ?? '',
                ];
            }
            if (empty($itens)) return;

            $payload = [
                'contato' => ['id' => $contatoId],
                'data' => $pedidoData['data'] ?? now()->format('Y-m-d'),
                'itens' => $itens,
                'observacoes' => $obs,
            ];

            $res = $client->put("/pedidos/vendas/{$staging->bling_id}", [], $payload);

            if (!$res['success']) {
                Log::warning("ShopeeCorrigir: erro ao atualizar observações do pedido", [
                    'pedido' => $pedidoId,
                    'bling_id' => $staging->bling_id,
                    'response' => $res,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("ShopeeCorrigir: erro ao atualizar observações", [
                'pedido' => $pedidoId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function formatarCpf(string $cpf): string
    {
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
        return $cpf;
    }

    private static function ufParaSigla(string $uf): string
    {
        $uf = trim($uf);
        // Se já é sigla (2 caracteres), retorna
        if (strlen($uf) === 2) return strtoupper($uf);

        $mapa = [
            'acre' => 'AC', 'alagoas' => 'AL', 'amapá' => 'AP', 'amapa' => 'AP',
            'amazonas' => 'AM', 'bahia' => 'BA', 'ceará' => 'CE', 'ceara' => 'CE',
            'distrito federal' => 'DF', 'espírito santo' => 'ES', 'espirito santo' => 'ES',
            'goiás' => 'GO', 'goias' => 'GO', 'maranhão' => 'MA', 'maranhao' => 'MA',
            'mato grosso' => 'MT', 'mato grosso do sul' => 'MS',
            'minas gerais' => 'MG', 'pará' => 'PA', 'para' => 'PA',
            'paraíba' => 'PB', 'paraiba' => 'PB', 'paraná' => 'PR', 'parana' => 'PR',
            'pernambuco' => 'PE', 'piauí' => 'PI', 'piaui' => 'PI',
            'rio de janeiro' => 'RJ', 'rio grande do norte' => 'RN',
            'rio grande do sul' => 'RS', 'rondônia' => 'RO', 'rondonia' => 'RO',
            'roraima' => 'RR', 'santa catarina' => 'SC', 'são paulo' => 'SP',
            'sao paulo' => 'SP', 'sergipe' => 'SE', 'tocantins' => 'TO',
        ];

        return $mapa[strtolower($uf)] ?? strtoupper(substr($uf, 0, 2));
    }
}
