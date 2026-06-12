<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CalculadoraWebcon extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Ferramentas';
    protected static ?string $navigationLabel = 'Calc. Webcontinental';
    protected static ?string $title = 'Calculadora Webcontinental';
    protected static string $view = 'filament.pages.calculadora-webcon';
    protected static ?int $navigationSort = 11;

    public ?float $custo = null;
    public float $margem = 20;
    public ?array $resultados = null;

    private const IMPOSTO = 13;
    private const COMISSOES = [10, 13, 15, 17];

    public function calcular(): void
    {
        if (!$this->custo || $this->custo <= 0) {
            $this->resultados = null;
            return;
        }

        $resultados = [];
        foreach (self::COMISSOES as $comissao) {
            $divisor = 1 - ($comissao / 100) - (self::IMPOSTO / 100) - ($this->margem / 100);
            if ($divisor <= 0) {
                $resultados[] = ['comissao' => $comissao, 'preco' => null, 'lucro' => null];
                continue;
            }
            $preco = round($this->custo / $divisor, 2);
            $valorComissao = round($preco * $comissao / 100, 2);
            $valorImposto = round($preco * self::IMPOSTO / 100, 2);
            $lucro = round($preco - $this->custo - $valorComissao - $valorImposto, 2);

            $resultados[] = [
                'comissao' => $comissao,
                'preco' => $preco,
                'valor_comissao' => $valorComissao,
                'valor_imposto' => $valorImposto,
                'lucro' => $lucro,
                'margem_real' => $preco > 0 ? round(($lucro / $preco) * 100, 1) : 0,
            ];
        }

        $this->resultados = $resultados;
    }

    public function updatedCusto(): void { $this->calcular(); }
    public function updatedMargem(): void { $this->calcular(); }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'marketing']) ?? false;
    }
}
