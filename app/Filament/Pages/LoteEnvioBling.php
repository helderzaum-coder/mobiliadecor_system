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
            // Buscar pedido pela NF-e no staging
            $staging = \App\Models\PedidoBlingStaging::where('bling_account', $this->blingAccount)
                ->where(function ($q) use ($nfeNumero) {
                    $q->where('nfe_numero', $nfeNumero)
                      ->orWhere('nota_fiscal', $nfeNumero);
                })
                ->first();

            // Fallback: buscar na tabela vendas
            if (!$staging) {
                $venda = \App\Models\Venda::where('bling_account', $this->blingAccount)
                    ->where('numero_nota_fiscal', $nfeNumero)
                    ->first();

                if ($venda && $venda->bling_id) {
                    $res = $client->alterarSituacaoPedido((int) $venda->bling_id, $this->situacaoId);
                    $this->resultados[] = [
                        'nfe' => $nfeNumero,
                        'pedido' => $venda->numero_pedido_canal,
                        'success' => $res['success'],
                        'msg' => $res['success'] ? 'OK' : ('Erro HTTP ' . ($res['http_code'] ?? '?')),
                    ];
                    $res['success'] ? $sucesso++ : $erros++;
                    continue;
                }

                $this->resultados[] = [
                    'nfe' => $nfeNumero,
                    'pedido' => '-',
                    'success' => false,
                    'msg' => 'NF-e não encontrada no sistema',
                ];
                $erros++;
                continue;
            }

            if (!$staging->bling_id) {
                $this->resultados[] = [
                    'nfe' => $nfeNumero,
                    'pedido' => $staging->numero_pedido,
                    'success' => false,
                    'msg' => 'Pedido sem bling_id',
                ];
                $erros++;
                continue;
            }

            $res = $client->alterarSituacaoPedido((int) $staging->bling_id, $this->situacaoId);
            $this->resultados[] = [
                'nfe' => $nfeNumero,
                'pedido' => $staging->numero_pedido,
                'success' => $res['success'],
                'msg' => $res['success'] ? 'OK' : ('Erro HTTP ' . ($res['http_code'] ?? '?')),
            ];
            $res['success'] ? $sucesso++ : $erros++;
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
