<?php

namespace App\Services;

use App\Models\PedidoBlingStaging;
use App\Services\Bling\BlingClient;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ShopeeCorrigirDadosService
{
    public static function processar(string $filePath): array
    {
        $resultado = ['corrigidos' => 0, 'erros' => 0, 'nao_encontrados' => 0, 'detalhes' => []];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Exception $e) {
            return [
                'corrigidos' => 0,
                'erros' => 1,
                'nao_encontrados' => 0,
                'detalhes' => ["Erro ao ler arquivo: {$e->getMessage()}"]
            ];
        }

        $pedidos = [];
        $header = null;
        foreach ($rows as $row) {
            if (!$header) {
                $header = $row;
                continue;
            }

            $pedidoId = trim($row['A'] ?? '');
            if (empty($pedidoId) || isset($pedidos[$pedidoId])) {
                continue;
            }

            $pedidos[$pedidoId] = $row;
        }

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

            if (empty($nome)) {
                continue;
            }

            try {
                $client = new BlingClient($staging->bling_account);
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

                $tipoPessoa = strlen($cpf) > 11 ? 'J' : 'F';
                $payloadContato = [
                    'nome' => $nome,
                    'tipo' => $tipoPessoa,
                    'situacao' => 'A',
                ];

                if ($telefone) {
                    $tel = preg_replace('/\D/', '', $telefone);
                    if (strlen($tel) >= 12 && str_starts_with($tel, '55')) {
                        $tel = substr($tel, 2);
                    }
                    if (strlen($tel) >= 10) {
                        $payloadContato['telefone'] = $tel;
                    }
                }

                if ($cpf && (strlen($cpf) == 11 || strlen($cpf) == 14)) {
                    $payloadContato['numeroDocumento'] = $cpf;
                }

                if ($endereco || $cidade || $uf || $cep) {
                    $ufSigla = self::ufParaSigla($uf);
                    $partes = array_map('trim', explode(',', $endereco));
                    $rua = $partes[0] ?? $endereco;
                    $numero = '';

                    if (count($partes) >= 2 && preg_match('/^\d+\w*$/', $partes[1])) {
                        $numero = $partes[1];
                    }

                    $payloadContato['endereco'] = [
                        'endereco' => $rua,
                        'numero' => $numero,
                        'bairro' => $bairro,
                        'municipio' => $cidade,
                        'uf' => (strlen($ufSigla) == 2) ? $ufSigla : '',
                        'cep' => $cep,
                        'complemento' => $endereco ? "Endereço completo: {$endereco}" : '',
                    ];

                    if (empty($payloadContato['endereco']['uf'])) {
                        unset($payloadContato['endereco']['uf']);
                    }
                }

                Log::info('ShopeeCorrigir DEBUG: payload contato', [
                    'pedidoId' => $pedidoId,
                    'contatoId' => $contatoId,
                    'payload_contato' => $payloadContato,
                ]);

                $resContato = $client->put("/contatos/{$contatoId}", [], $payloadContato);

                if (!$resContato['success']) {
                    Log::warning('ShopeeCorrigir: Falha ao atualizar contato, mas prosseguindo para o pedido', [
                        'pedido' => $pedidoId,
                        'response' => $resContato['body'] ?? []
                    ]);
                }

                self::atualizarDadosPedido($client, $staging, $pedidoId, $row, $pedidoBling['body']['data'] ?? []);

                $staging->update([
                    'cliente_nome' => $nome,
                    'cliente_documento' => $cpf ? self::formatarCpf($cpf) : $staging->cliente_documento,
                ]);

                \App\Models\Venda::where('bling_id', $staging->bling_id)->update([
                    'cliente_nome' => $nome,
                    'cliente_documento' => $cpf ? self::formatarCpf($cpf) : null,
                ]);

                $resultado['corrigidos']++;
            } catch (\Exception $e) {
                $resultado['erros']++;
                $resultado['detalhes'][] = "{$pedidoId}: {$e->getMessage()}";
            }
        }

        return $resultado;
    }

    private static function atualizarDadosPedido(BlingClient $client, PedidoBlingStaging $staging, string $pedidoId, array $row, array $pedidoData): void
    {
        try {
            $precoU = self::parseDecimalValue($row['U'] ?? 0);
            $subsidioY = abs(self::parseDecimalValue($row['Y'] ?? 0));
            $subtotal = $precoU - $subsidioY;
            $taxaEnvio = self::parseDecimalValue($row['AM'] ?? 0);
            $descontoFrete = abs(self::parseDecimalValue($row['AN'] ?? 0));
            $frete = $taxaEnvio + $descontoFrete;
            $faturar = round($subtotal / 2, 2);

            $obs = "=== DADOS SHOPEE ===\n"
                . "ID Pedido: {$pedidoId}\n"
                . "Subtotal: R$ " . number_format($subtotal, 2, ',', '.') . "\n"
                . "Faturar (meia nota): R$ " . number_format($faturar, 2, ',', '.') . "\n"
                . "Frete recebido: R$ " . number_format($frete, 2, ',', '.');

            $obsAtual = trim((string) ($pedidoData['observacoesInternas'] ?? ''));
            if ($obsAtual === trim($obs)) {
                Log::info('ShopeeCorrigir DEBUG: observacoesInternas já está igual, pulando update', [
                    'pedidoId' => $pedidoId,
                    'bling_id' => $staging->bling_id,
                ]);
                return;
            }

            $itens = [];
            foreach ($pedidoData['itens'] ?? [] as $item) {
                $itemData = [
                    'id' => $item['id'] ?? 0,
                    'descricao' => $item['descricao'] ?? '',
                    'quantidade' => $item['quantidade'] ?? 1,
                    'valor' => $item['valor'] ?? 0,
                    'unidade' => $item['unidade'] ?? 'UN',
                ];
                if (!empty($item['produto']['id'])) {
                    $itemData['produto'] = ['id' => $item['produto']['id']];
                }
                $itens[] = $itemData;
            }

            $numeroPedidoLoja = trim((string) ($staging->numero_loja ?? ''));
            if ($numeroPedidoLoja === '') {
                $numeroPedidoLoja = trim((string) ($pedidoData['numeroPedidoLoja'] ?? $pedidoId));
            }

            $payload = [
                'contato' => ['id' => $pedidoData['contato']['id'] ?? null],
                'data' => $pedidoData['data'] ?? now()->format('Y-m-d'),
                'numero' => $pedidoData['numero'] ?? null,
                'loja' => $pedidoData['loja'] ?? null,
                'numeroPedidoLoja' => $numeroPedidoLoja,
                'itens' => $itens,
                'observacoesInternas' => $obs,
            ];

            foreach (['transporte', 'parcelas', 'desconto', 'outrasDespesas', 'dataSaida', 'dataPrevista', 'observacoes'] as $campo) {
                if (isset($pedidoData[$campo])) {
                    $payload[$campo] = $pedidoData[$campo];
                }
            }

            Log::info('ShopeeCorrigir DEBUG: update pedido com observacoesInternas', [
                'pedidoId' => $pedidoId,
                'bling_id' => $staging->bling_id,
                'observacoesInternas_atual' => $obsAtual,
                'observacoesInternas_nova' => $obs,
                'numeroPedidoLoja_final' => $numeroPedidoLoja,
                'loja_no_payload' => $payload['loja'] ?? null,
            ]);

            $res = $client->put("/pedidos/vendas/{$staging->bling_id}", [], $payload);

            if (!$res['success']) {
                Log::error('ShopeeCorrigir: Erro ao atualizar pedido', [
                    'pedido' => $pedidoId,
                    'response' => $res['body'] ?? []
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ShopeeCorrigir: Erro crítico em atualizarDadosPedido', [
                'pedido' => $pedidoId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private static function parseDecimalValue($value): float
    {
        if (is_numeric($value)) return (float) $value;
        $str = trim((string) $value);
        if ($str === '') return 0;
        if (str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        }
        return is_numeric($str) ? (float) $str : 0;
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
        $uf = mb_strtolower(trim($uf));
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

        return $mapa[$uf] ?? '';
    }
}