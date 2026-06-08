<?php

namespace App\Filament\Pages;

use App\Services\Bling\BlingClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class LoteEnvioBling extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Bling';
    protected static ?string $navigationLabel = 'Lote Envio';
    protected static ?string $title = 'Atualizar Situação em Lote';
    protected static string $view = 'filament.pages.lote-envio-bling';

    public string $blingAccount = 'primary';
    public string $numerosNfe = '';
    public ?int $situacaoId = null;
    public array $situacoes = [];
    public array $resultados = [];
    public bool $processado = false;

    public function mount(): void
    {
        $this->carregarSituacoes();
    }

    public function carregarSituacoes(): void
    {
        $client = new BlingClient($this->blingAccount);

        // Primeiro descobrir o ID do módulo "Vendas" dinamicamente
        $modulos = $client->get('/situacoes/modulos');
        $moduloVendasId = null;

        if ($modulos['success']) {
            foreach ($modulos['body']['data'] ?? [] as $mod) {
                if ($mod['nome'] === 'Vendas') {
                    $moduloVendasId = $mod['id'];
                    break;
                }
            }
        }

        if (!$moduloVendasId) {
            return;
        }

        $response = $client->getSituacoes($moduloVendasId);

        if ($response['success']) {
            $this->situacoes = collect($response['body']['data'] ?? [])
                ->map(fn ($s) => ['id' => $s['id'], 'nome' => $s['nome']])
                ->toArray();
        }
    }

    public function updatedBlingAccount(): void
    {
        $this->situacoes = [];
        $this->carregarSituacoes();
    }

    public function processar(): void
    {
        if (!$this->situacaoId) {
            Notification::make()->title('Selecione uma situação')->warning()->send();
            return;
        }

        $numeros = array_filter(
            array_map('trim', preg_split('/[\n,;]+/', $this->numerosNfe))
        );

        if (empty($numeros)) {
            Notification::make()->title('Informe ao menos um número de NF-e')->warning()->send();
            return;
        }

        $client = new BlingClient($this->blingAccount);
        $this->resultados = [];
        $sucesso = 0;
        $erros = 0;

        foreach ($numeros as $nfeNumero) {
            // Normalizar: tentar com e sem zeros à esquerda (até 6 dígitos)
            $variantes = [
                $nfeNumero,
                ltrim($nfeNumero, '0'),
                str_pad(ltrim($nfeNumero, '0'), 6, '0', STR_PAD_LEFT),
            ];
            $variantes = array_unique(array_filter($variantes));

            // Buscar na tabela vendas
            $venda = \App\Models\Venda::where('bling_account', $this->blingAccount)
                ->whereIn('numero_nota_fiscal', $variantes)
                ->first();

            if ($venda && $venda->bling_id) {
                $res = $client->alterarSituacaoPedido((int) $venda->bling_id, $this->situacaoId);
                $this->resultados[] = [
                    'nfe' => $nfeNumero,
                    'pedido' => $venda->numero_pedido_canal,
                    'success' => $res['success'] || $res['http_code'] === 204,
                    'msg' => $this->extrairMsgErro($res),
                ];
                ($res['success'] || $res['http_code'] === 204) ? $sucesso++ : $erros++;
                continue;
            }

            // Buscar no staging
            $staging = \App\Models\PedidoBlingStaging::where('bling_account', $this->blingAccount)
                ->where(function ($q) use ($variantes) {
                    $q->whereIn('nfe_numero', $variantes)
                      ->orWhereIn('nota_fiscal', $variantes);
                })
                ->first();

            if ($staging && $staging->bling_id) {
                $res = $client->alterarSituacaoPedido((int) $staging->bling_id, $this->situacaoId);
                $this->resultados[] = [
                    'nfe' => $nfeNumero,
                    'pedido' => $staging->numero_pedido,
                    'success' => $res['success'] || $res['http_code'] === 204,
                    'msg' => $this->extrairMsgErro($res),
                ];
                ($res['success'] || $res['http_code'] === 204) ? $sucesso++ : $erros++;
                continue;
            }

            // Fallback: buscar NF-e direto na API do Bling (formato com zeros à esquerda, 6 dígitos)
            $nfeNumeroFormatado = str_pad(ltrim($nfeNumero, '0'), 6, '0', STR_PAD_LEFT);
            $nfeRes = $client->getNfes(['numero' => $nfeNumeroFormatado]);
            $nfeData = $nfeRes['body']['data'][0] ?? null;

            if ($nfeData) {
                // Buscar detalhes da NF-e para pegar valor e contato
                $nfeDetalhe = $client->getNfe((int) $nfeData['id']);
                $nfeInfo = $nfeDetalhe['body']['data'] ?? $nfeData;
                $nfeValor = (float) ($nfeInfo['valorNota'] ?? 0);
                $contatoId = $nfeInfo['contato']['id'] ?? $nfeData['contato']['id'] ?? null;

                $pedidoVinculado = null;

                if ($contatoId) {
                    // Buscar pedidos deste contato
                    $pedidosRes = $client->getPedidos(['idContato' => (int) $contatoId]);
                    $pedidos = $pedidosRes['body']['data'] ?? [];

                    // Priorizar pedido com valor total próximo ao valor da NF-e
                    // e situação que permite transição (Verificado, checkout, ML Etiqueta, Em andamento)
                    $situacoesValidas = [24, 131906, 393372, 15, 733117, 733670];
                    foreach ($pedidos as $p) {
                        $sitId = $p['situacao']['id'] ?? 0;
                        if (!in_array($sitId, $situacoesValidas)) continue;

                        // Se valor bate (tolerância de R$1), é o pedido certo
                        if ($nfeValor > 0 && abs((float) $p['total'] - $nfeValor) <= 1.00) {
                            $pedidoVinculado = $p;
                            break;
                        }
                    }

                    // Se não achou por valor, pegar o primeiro com situação válida
                    if (!$pedidoVinculado) {
                        foreach ($pedidos as $p) {
                            if (in_array($p['situacao']['id'] ?? 0, $situacoesValidas)) {
                                $pedidoVinculado = $p;
                                break;
                            }
                        }
                    }
                }

                if ($pedidoVinculado) {
                    $res = $client->alterarSituacaoPedido((int) $pedidoVinculado['id'], $this->situacaoId);
                    $this->resultados[] = [
                        'nfe' => $nfeNumero,
                        'pedido' => '#' . ($pedidoVinculado['numero'] ?? $pedidoVinculado['id']),
                        'success' => $res['success'] || $res['http_code'] === 204,
                        'msg' => $this->extrairMsgErro($res),
                    ];
                    ($res['success'] || $res['http_code'] === 204) ? $sucesso++ : $erros++;
                    continue;
                }
            }

            $this->resultados[] = [
                'nfe' => $nfeNumero,
                'pedido' => '-',
                'success' => false,
                'msg' => 'NF-e não encontrada',
            ];
            $erros++;
        }

        $this->processado = true;

        $situacaoNome = collect($this->situacoes)->firstWhere('id', $this->situacaoId)['nome'] ?? '?';

        Log::info("LoteEnvioBling: {$sucesso} atualizados, {$erros} erros → situação '{$situacaoNome}'", [
            'account' => $this->blingAccount,
            'nfes' => $numeros,
        ]);

        Notification::make()
            ->title("Processado: {$sucesso} OK, {$erros} erros")
            ->color($erros > 0 ? 'warning' : 'success')
            ->send();
    }

    private function extrairMsgErro(array $res): string
    {
        if ($res['success'] || ($res['http_code'] ?? 0) === 204) {
            return 'OK';
        }

        // Extrair mensagem detalhada dos fields
        $fields = $res['body']['error']['fields'] ?? [];
        if (!empty($fields)) {
            return $fields[0]['msg'] ?? ($res['body']['error']['message'] ?? 'Erro');
        }

        return $res['body']['error']['message'] ?? ('HTTP ' . ($res['http_code'] ?? '?'));
    }
}
