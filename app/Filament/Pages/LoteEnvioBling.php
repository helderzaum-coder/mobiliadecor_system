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
                $erro = $res['body']['error']['message'] ?? $res['body']['error']['description'] ?? null;
                $this->resultados[] = [
                    'nfe' => $nfeNumero,
                    'pedido' => $venda->numero_pedido_canal,
                    'success' => $res['success'],
                    'msg' => $res['success'] ? 'OK' : ('HTTP ' . ($res['http_code'] ?? '?') . ($erro ? " - {$erro}" : '')),
                ];
                $res['success'] ? $sucesso++ : $erros++;
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
                $erro = $res['body']['error']['message'] ?? $res['body']['error']['description'] ?? null;
                $this->resultados[] = [
                    'nfe' => $nfeNumero,
                    'pedido' => $staging->numero_pedido,
                    'success' => $res['success'],
                    'msg' => $res['success'] ? 'OK' : ('HTTP ' . ($res['http_code'] ?? '?') . ($erro ? " - {$erro}" : '')),
                ];
                $res['success'] ? $sucesso++ : $erros++;
                continue;
            }

            // Fallback: buscar NF-e direto na API do Bling
            $nfeRes = $client->getNfes(['numero' => ltrim($nfeNumero, '0')]);
            $nfeData = $nfeRes['body']['data'][0] ?? null;

            if ($nfeData) {
                // Buscar detalhe da NF-e para pegar o pedido vinculado
                $nfeDetalhe = $client->getNfe((int) $nfeData['id']);
                $pedidoVinculado = $nfeDetalhe['body']['data']['pedidoVenda']['id'] ?? null;

                if ($pedidoVinculado) {
                    $res = $client->alterarSituacaoPedido((int) $pedidoVinculado, $this->situacaoId);
                    $erro = $res['body']['error']['message'] ?? $res['body']['error']['description'] ?? null;
                    $this->resultados[] = [
                        'nfe' => $nfeNumero,
                        'pedido' => 'Bling #' . $pedidoVinculado,
                        'success' => $res['success'],
                        'msg' => $res['success'] ? 'OK (via API)' : ('HTTP ' . ($res['http_code'] ?? '?') . ($erro ? " - {$erro}" : '')),
                    ];
                    $res['success'] ? $sucesso++ : $erros++;
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
}
