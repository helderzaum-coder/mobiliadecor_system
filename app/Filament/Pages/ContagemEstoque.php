<?php

namespace App\Filament\Pages;

use App\Models\ProdutoEstoque;
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
    public array $itensContados = []; // ['codigo_barras' => ['sku' => ..., 'nome' => ..., 'quantidade' => ...]]
    public bool $contagemFinalizada = false;
    public array $divergencias = [];

    public function bipar(): void
    {
        $codigo = trim($this->codigoInput);
        $this->codigoInput = '';

        if (empty($codigo)) return;

        // Buscar por codigo_barras OU sku
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

        if (isset($this->itensContados[$key])) {
            $this->itensContados[$key]['quantidade']++;
        } else {
            $this->itensContados[$key] = [
                'sku' => $produto->sku,
                'codigo_barras' => $produto->codigo_barras,
                'nome' => $produto->nome,
                'quantidade' => 1,
                'saldo_sistema' => $produto->saldo_fisico,
            ];
        }

        Notification::make()
            ->title("{$produto->sku} — {$produto->nome}")
            ->body("Qtd: {$this->itensContados[$key]['quantidade']}")
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

        foreach ($this->itensContados as $item) {
            $produto = ProdutoEstoque::where('sku', $item['sku'])->where('ativo', true)->first();
            if (!$produto) continue;

            $saldoAtual = $produto->saldo_fisico;
            $novoSaldo = $item['quantidade'];

            $this->divergencias[] = [
                'sku' => $item['sku'],
                'nome' => $item['nome'],
                'saldo_sistema' => $saldoAtual,
                'contagem' => $novoSaldo,
                'diferenca' => $novoSaldo - $saldoAtual,
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

        $this->contagemFinalizada = true;

        Log::info("ContagemEstoque: finalizada por " . auth()->user()->name, [
            'atualizados' => $atualizados,
            'sem_alteracao' => $semAlteracao,
            'total_itens' => count($this->itensContados),
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
