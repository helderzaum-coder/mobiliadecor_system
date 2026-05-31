<?php

namespace App\Filament\Pages;

use App\Jobs\VariacaoTamposJob;
use App\Models\ProdutoEstoque;
use App\Models\TrocaTampoConfig;
use App\Services\EstoqueService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class ContagemEstoque extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Contagem de Estoque';
    protected static ?string $title = 'Contagem de Estoque';
    protected static string $view = 'filament.pages.contagem-estoque';

    public string $codigoInput = '';
    public array $itensContados = [];
    public bool $contagemFinalizada = false;
    public array $divergencias = [];

    public function bipar(): void
    {
        $codigo = trim($this->codigoInput);
        $this->codigoInput = '';

        if (empty($codigo)) return;

        $produto = ProdutoEstoque::where('codigo_barras', $codigo)
            ->orWhere('sku', $codigo)
            ->where('ativo', true)
            ->first();

        if (!$produto) {
            Notification::make()
                ->title("Produto não encontrado: {$codigo}")
                ->danger()
                ->send();
            return;
        }

        $key = $produto->sku;

        // Verificar se pertence a grupo de troca de tampo
        $grupoTampo = TrocaTampoConfig::where('sku_produto', $produto->sku)->first();
        $grupoLabel = $grupoTampo ? "{$grupoTampo->grupo} {$grupoTampo->cor}" : null;

        if (isset($this->itensContados[$key])) {
            $this->itensContados[$key]['quantidade']++;
        } else {
            $this->itensContados[$key] = [
                'sku' => $produto->sku,
                'codigo_barras' => $produto->codigo_barras,
                'nome' => $produto->nome,
                'quantidade' => 1,
                'saldo_sistema' => $produto->saldo_fisico,
                'grupo_tampo' => $grupoLabel,
            ];
        }

        $body = "Qtd: {$this->itensContados[$key]['quantidade']}";
        if ($grupoLabel) {
            $body .= " (Grupo: {$grupoLabel})";
        }

        Notification::make()
            ->title("{$produto->sku} — {$produto->nome}")
            ->body($body)
            ->success()
            ->duration(2000)
            ->send();
    }

    public function removerItem(string $sku): void
    {
        unset($this->itensContados[$sku]);
    }

    public function ajustarQuantidade(string $sku, int $quantidade): void
    {
        if (isset($this->itensContados[$sku])) {
            if ($quantidade <= 0) {
                unset($this->itensContados[$sku]);
            } else {
                $this->itensContados[$sku]['quantidade'] = $quantidade;
            }
        }
    }

    public function finalizarContagem(): void
    {
        if (empty($this->itensContados)) {
            Notification::make()->title('Nenhum item contado.')->warning()->send();
            return;
        }

        $this->divergencias = [];
        $atualizados = 0;
        $semAlteracao = 0;
        $gruposProcessados = [];

        foreach ($this->itensContados as $item) {
            $produto = ProdutoEstoque::where('sku', $item['sku'])->where('ativo', true)->first();
            if (!$produto) continue;

            // Verificar se pertence a grupo de troca de tampo
            $config = TrocaTampoConfig::where('sku_produto', $item['sku'])->first();

            if ($config) {
                $grupoKey = $config->grupo . '|' . $config->cor;

                // Acumular quantidade do grupo (somar todos os SKUs do mesmo grupo+cor)
                if (!isset($gruposProcessados[$grupoKey])) {
                    $gruposProcessados[$grupoKey] = [
                        'total' => 0,
                        'configs' => TrocaTampoConfig::where('grupo', $config->grupo)
                            ->where('cor', $config->cor)
                            ->get(),
                    ];
                }
                $gruposProcessados[$grupoKey]['total'] += $item['quantidade'];
            } else {
                // Produto normal: balanço individual
                $saldoAtual = $produto->saldo_fisico;
                $novoSaldo = $item['quantidade'];

                $this->divergencias[] = [
                    'sku' => $item['sku'],
                    'nome' => $item['nome'],
                    'saldo_sistema' => $saldoAtual,
                    'contagem' => $novoSaldo,
                    'diferenca' => $novoSaldo - $saldoAtual,
                    'grupo_tampo' => null,
                ];

                if ($novoSaldo !== $saldoAtual) {
                    EstoqueService::balanco(
                        $item['sku'],
                        $novoSaldo,
                        'contagem',
                        'Contagem de estoque por leitor',
                        auth()->id(),
                        true,
                        'fisico'
                    );
                    $atualizados++;
                } else {
                    $semAlteracao++;
                }
            }
        }

        // Processar grupos de troca de tampo: atualizar saldo_carcaca e disparar equalização
        foreach ($gruposProcessados as $grupoKey => $dados) {
            $totalCarcacas = $dados['total'];

            foreach ($dados['configs'] as $config) {
                $produto = ProdutoEstoque::where('sku', $config->sku_produto)->where('ativo', true)->first();
                if (!$produto) continue;

                $saldoAnterior = $produto->saldo_carcaca ?? 0;

                $this->divergencias[] = [
                    'sku' => $config->sku_produto,
                    'nome' => $config->nome_produto ?? $produto->nome,
                    'saldo_sistema' => $saldoAnterior,
                    'contagem' => $totalCarcacas,
                    'diferenca' => $totalCarcacas - $saldoAnterior,
                    'grupo_tampo' => "{$config->grupo} {$config->cor}",
                ];

                if ($totalCarcacas !== $saldoAnterior) {
                    $produto->update(['saldo_carcaca' => $totalCarcacas]);
                    $atualizados++;
                } else {
                    $semAlteracao++;
                }
            }

            // Disparar equalização para a família deste grupo
            $primeiraConfig = $dados['configs']->first();
            if ($primeiraConfig && $primeiraConfig->familia_tampo) {
                VariacaoTamposJob::dispatch('primary', $primeiraConfig->familia_tampo);
            }
        }

        $this->contagemFinalizada = true;

        Log::info("ContagemEstoque: finalizada por " . auth()->user()->name, [
            'atualizados' => $atualizados,
            'sem_alteracao' => $semAlteracao,
            'total_itens' => count($this->itensContados),
            'grupos_tampo' => count($gruposProcessados),
        ]);

        Notification::make()
            ->title("Contagem finalizada!")
            ->body("{$atualizados} produtos atualizados, {$semAlteracao} sem alteração. Estoque sincronizado com ambos os Blings.")
            ->success()
            ->send();
    }

    public function novaContagem(): void
    {
        $this->itensContados = [];
        $this->divergencias = [];
        $this->contagemFinalizada = false;
        $this->codigoInput = '';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
