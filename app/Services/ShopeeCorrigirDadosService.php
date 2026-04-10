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
            if (empty($nome)) {
                continue;
            }

            try {
                $client = new BlingClient($staging->bling_account);

                // ── PASSO 1: Busca o pedido completo ────────────────────────────────
                $pedidoBling = $client->getPedido((int) $staging->bling_id);

                if (!$pedidoBling['success']) {
                    $resultado['erros']++;
                    $resultado['detalhes'][] = "{$pedidoId}: erro ao buscar pedido no Bling";
                    continue;
                }

                $pedidoData = $pedidoBling['body']['data'] ?? [];
                $contatoId  = $pedidoData['contato']['id'] ?? null;

                if (!$contatoId) {
                    $resultado['erros']++;
                    $resultado['detalhes'][] = "{$pedidoId}: contato não encontrado no pedido";
                    continue;
                }

                // ── PASSO 2: PUT /contatos/{id} — atualiza dados do cliente ─────────
                self::atualizarContato($client, $pedidoId, $contatoId, $row);

                // ── PASSO 3: PUT /pedidos/vendas/{bling_id} — atualiza observações ──
                // A Bling v3 não documenta PATCH para este endpoint. Usamos PUT com
                // o payload completo do GET, sobrescrevendo apenas observacoesInternas.
                self::atualizarObservacoesPedido($client, $staging, $pedidoId, $row, $pedidoData);

                // ── PASSO 4: Atualiza banco local ────────────────────────────────────
                $cpf = preg_replace('/\D/', '', trim($row['AZ'] ?? ''));

                $staging->update([
                    'cliente_nome'      => $nome,
                    'cliente_documento' => $cpf ? self::formatarCpf($cpf) : $staging->cliente_documento,
                ]);

                \App\Models\Venda::where('bling_id', $staging->bling_id)->update([
                    'cliente_nome'      => $nome,
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

    // ════════════════════════════════════════════════════════════════════════
    // PASSO 2 — PUT /contatos/{id} 
    // Atualiza nome, CPF, telefone e endereço do cliente no cadastro Bling.
    // É independente do pedido — não afeta numeroPedidoLoja.
    // ════════════════════════════════════════════════════════════════════════
    private static function atualizarContato(BlingClient $client, string $pedidoId, int $contatoId, array $row): void
    {
        $nome     = trim($row['AX'] ?? '');
        $telefone = trim($row['AY'] ?? '');
        $cpf      = preg_replace('/\D/', '', trim($row['AZ'] ?? ''));
        $endereco = trim($row['BA'] ?? '');
        $bairro   = trim($row['BC'] ?? '');
        $cidade   = trim($row['BB'] ?? '') ?: trim($row['BD'] ?? '');
        $uf       = trim($row['BE'] ?? '');
        $cep      = preg_replace('/\D/', '', trim($row['BG'] ?? ''));

        $tipoPessoa = strlen($cpf) > 11 ? 'J' : 'F';

        // Busca contato atual para preservar campos existentes e descobrir estrutura
        $contatoAtual = $client->get("/contatos/{$contatoId}");
        $contatoData = $contatoAtual['success'] ? ($contatoAtual['body']['data'] ?? []) : [];

        Log::info('ShopeeCorrigir: estrutura contato atual do Bling', [
            'pedidoId'  => $pedidoId,
            'contatoId' => $contatoId,
            'campos'    => array_keys($contatoData),
            'contato'   => $contatoData,
        ]);

        $payload = [
            'nome'     => $nome,
            'tipo'     => $tipoPessoa,
            'situacao' => 'A',
        ];

        if ($telefone) {
            $tel = preg_replace('/\D/', '', $telefone);
            if (strlen($tel) >= 12 && str_starts_with($tel, '55')) {
                $tel = substr($tel, 2);
            }
            if (strlen($tel) >= 10) {
                $payload['telefone'] = $tel;
            }
        }

        if ($cpf && (strlen($cpf) === 11 || strlen($cpf) === 14)) {
            $payload['numeroDocumento'] = $cpf;
        }

        // Monta texto do endereço completo para observação
        $ufSigla = self::ufParaSigla($uf);
        $partesEndereco = array_filter([$endereco, $bairro, $cidade, $ufSigla, $cep]);
        $obsTexto = $partesEndereco ? 'Endereço completo: ' . implode(', ', $partesEndereco) : '';

        if ($endereco || $cidade || $uf || $cep) {
            $partes  = array_map('trim', explode(',', $endereco));
            $rua     = $partes[0] ?? $endereco;
            $numero  = '';

            if (count($partes) >= 2 && preg_match('/^\d+\w*$/', $partes[1])) {
                $numero = $partes[1];
            }

            $enderecoPayload = [
    'endereco'    => $rua,
    'numero'      => $numero,
    'bairro'      => $bairro,
    'municipio'   => $cidade,
    'uf'          => (strlen($ufSigla) === 2) ? $ufSigla : '',
    'cep'         => $cep,
    // NÃO coloque mais o complemento aqui
];

// Payload principal (o que você envia na requisição)
$payload = [ /* seus outros campos aqui... */ ];

// Adiciona a observação com o endereço completo
if (!empty($endereco)) {
    $payload['observacoes'] = "Endereço completo: {$endereco}";
}

// Se o endereço for um sub-array (ex: dentro de 'cliente'), faça assim:
$payload['cliente'] = $enderecoPayload;   // ou o nome do campo que você usa
// ou merge se já tiver outros campos do cliente:
$payload['cliente'] = array_merge($payload['cliente'] ?? [], $enderecoPayload);
        }

        // Tenta todos os campos possíveis de observação da API Bling v3
        if ($obsTexto) {
            $payload['observacao']  = $obsTexto;
            $payload['observacoes'] = $obsTexto;
        }

        Log::info('ShopeeCorrigir: atualizando contato', [
            'pedidoId'  => $pedidoId,
            'contatoId' => $contatoId,
            'payload'   => $payload,
        ]);

        $res = $client->put("/contatos/{$contatoId}", [], $payload);

        Log::info('ShopeeCorrigir: resposta PUT contato', [
            'pedido'    => $pedidoId,
            'success'   => $res['success'],
            'http_code' => $res['http_code'] ?? null,
            'response'  => $res['body'] ?? [],
        ]);

        if (!$res['success']) {
            Log::warning('ShopeeCorrigir: falha ao atualizar contato (prosseguindo)', [
                'pedido'    => $pedidoId,
                'http_code' => $res['http_code'] ?? null,
                'response'  => $res['body'] ?? [],
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // PASSO 3 — PUT /pedidos/vendas/{bling_id}
    //
    // A API Bling v3 NÃO documenta PATCH para este endpoint.
    // O único PATCH disponível é para situação:
    //   PATCH /pedidos/vendas/{id}/situacoes/{idSituacao}
    //
    // Estratégia: reutiliza o payload completo obtido no GET (Passo 1) e
    // sobrescreve apenas o campo "observacoesInternas". Assim o PUT não
    // apaga numeroPedidoLoja, itens, transporte, parcelas etc.
    // ════════════════════════════════════════════════════════════════════════
    private static function atualizarObservacoesPedido(
        BlingClient $client,
        PedidoBlingStaging $staging,
        string $pedidoId,
        array $row,
        array $pedidoData
    ): void {
        try {
            $precoU        = self::parseDecimalValue($row['U'] ?? 0);
            $subsidioY     = abs(self::parseDecimalValue($row['Y'] ?? 0));
            $subtotal      = $precoU - $subsidioY;
            $taxaEnvio     = self::parseDecimalValue($row['AM'] ?? 0);
            $descontoFrete = abs(self::parseDecimalValue($row['AN'] ?? 0));
            $frete         = $taxaEnvio + $descontoFrete;
            $faturar       = round($subtotal / 2, 2);

            $obs = "=== DADOS SHOPEE ===\n"
                . "ID Pedido: {$pedidoId}\n"
                . "Subtotal: R$ " . number_format($subtotal, 2, ',', '.') . "\n"
                . "Faturar (meia nota): R$ " . number_format($faturar, 2, ',', '.') . "\n"
                . "Frete recebido: R$ " . number_format($frete, 2, ',', '.');

            $obsAtual = trim((string) ($pedidoData['observacoesInternas'] ?? ''));

            if ($obsAtual === trim($obs)) {
                Log::info('ShopeeCorrigir: observacoesInternas já está igual, pulando PUT', [
                    'pedidoId' => $pedidoId,
                    'bling_id' => $staging->bling_id,
                ]);
                return;
            }

            // ── Monta payload PUT completo a partir dos dados do GET ──────────
            // Sobrescreve apenas observacoesInternas; tudo o mais é mantido.
            $payload = $pedidoData;
            $payload['observacoesInternas'] = $obs;

            // O PUT da Bling v3 aceita "contato" como objeto com apenas "id"
            if (isset($payload['contato']['id'])) {
                $payload['contato'] = ['id' => $payload['contato']['id']];
            }

            // Campos somente-leitura que a API rejeita se enviados no PUT
            foreach (['id', 'numero', 'situacao', 'dataOperacao', 'dataCriacao'] as $campo) {
                unset($payload[$campo]);
            }

            Log::info('ShopeeCorrigir: enviando PUT observacoesInternas', [
                'pedido'   => $pedidoId,
                'bling_id' => $staging->bling_id,
            ]);

            $res = $client->put("/pedidos/vendas/{$staging->bling_id}", [], $payload);

            if (!$res['success']) {
                Log::error('ShopeeCorrigir: erro no PUT de observacoesInternas', [
                    'pedido'    => $pedidoId,
                    'bling_id'  => $staging->bling_id,
                    'http_code' => $res['http_code'] ?? null,
                    'response'  => $res['body'] ?? [],
                ]);
            } else {
                Log::info('ShopeeCorrigir: PUT observacoesInternas aplicado com sucesso', [
                    'pedido'   => $pedidoId,
                    'bling_id' => $staging->bling_id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('ShopeeCorrigir: erro crítico em atualizarObservacoesPedido', [
                'pedido' => $pedidoId,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════════════

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