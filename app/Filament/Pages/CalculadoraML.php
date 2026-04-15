<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CalculadoraML extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Ferramentas';
    protected static ?string $navigationLabel = 'Calculadora ML';
    protected static ?string $title = 'Calculadora de Preço - Mercado Livre';
    protected static string $view = 'filament.pages.calculadora-ml';
    protected static ?int $navigationSort = 10;

    // Inputs
    public string $modo = 'margem'; // margem ou preco_ideal
    public ?float $custo_produto = null;
    public ?float $preco_venda = null;
    public ?float $margem_desejada = null;
    public string $tipo_anuncio = 'classico';
    public ?float $percentual_imposto = null;
    public string $tipo_frete = 'ME2';
    public ?float $custo_frete = null;

    // Resultado
    public ?array $resultado = null;

    public function calcular(): void
    {
        if (!$this->custo_produto || $this->custo_produto <= 0) {
            $this->resultado = null;
            return;
        }

        $comissaoPct = $this->tipo_anuncio === 'premium' ? 16.5 : 11.5;
        $impostoPct = (float) ($this->percentual_imposto ?? 0);
        $custoFrete = (float) ($this->custo_frete ?? 0);

        if ($this->modo === 'margem') {
            $this->calcularMargem($comissaoPct, $impostoPct, $custoFrete);
        } else {
            $this->calcularPrecoIdeal($comissaoPct, $impostoPct, $custoFrete);
        }
    }

    private function calcularMargem(float $comissaoPct, float $impostoPct, float $custoFrete): void
    {
        if (!$this->preco_venda || $this->preco_venda <= 0) {
            $this->resultado = null;
            return;
        }

        $preco = $this->preco_venda;
        $custo = $this->custo_produto;

        $comissao = round($preco * $comissaoPct / 100, 2);
        $imposto = round($preco * $impostoPct / 100, 2);
        $recebe = round($preco - $comissao - $custoFrete, 2);
        $margem = round($recebe - $custo - $imposto, 2);
        $margemPct = $preco > 0 ? round(($margem / $preco) * 100, 1) : 0;

        $this->resultado = [
            'modo' => 'margem',
            'preco_venda' => $preco,
            'custo_produto' => $custo,
            'comissao_pct' => $comissaoPct,
            'comissao' => $comissao,
            'imposto_pct' => $impostoPct,
            'imposto' => $imposto,
            'custo_frete' => $custoFrete,
            'recebe' => $recebe,
            'margem' => $margem,
            'margem_pct' => $margemPct,
        ];
    }

    private function calcularPrecoIdeal(float $comissaoPct, float $impostoPct, float $custoFrete): void
    {
        if (!$this->margem_desejada) {
            $this->resultado = null;
            return;
        }

        $custo = $this->custo_produto;
        $margemDesejadaPct = $this->margem_desejada;

        // Preço = (Custo + Frete + Imposto) / (1 - Comissão% - Margem%)
        // Imposto = Preço × Imposto%
        // Comissão = Preço × Comissão%
        // Margem = Preço × Margem%
        // Preço = Custo + Comissão + Imposto + Frete + Margem
        // Preço = Custo + Preço×Comissão% + Preço×Imposto% + Frete + Preço×Margem%
        // Preço - Preço×(Comissão% + Imposto% + Margem%) = Custo + Frete
        // Preço × (1 - Comissão% - Imposto% - Margem%) = Custo + Frete
        $divisor = 1 - ($comissaoPct / 100) - ($impostoPct / 100) - ($margemDesejadaPct / 100);

        if ($divisor <= 0) {
            $this->resultado = ['erro' => 'Margem + Comissão + Imposto excedem 100%. Reduza a margem desejada.'];
            return;
        }

        $preco = round(($custo + $custoFrete) / $divisor, 2);
        $comissao = round($preco * $comissaoPct / 100, 2);
        $imposto = round($preco * $impostoPct / 100, 2);
        $recebe = round($preco - $comissao - $custoFrete, 2);
        $margem = round($recebe - $custo - $imposto, 2);
        $margemPct = $preco > 0 ? round(($margem / $preco) * 100, 1) : 0;

        $this->resultado = [
            'modo' => 'preco_ideal',
            'preco_venda' => $preco,
            'custo_produto' => $custo,
            'comissao_pct' => $comissaoPct,
            'comissao' => $comissao,
            'imposto_pct' => $impostoPct,
            'imposto' => $imposto,
            'custo_frete' => $custoFrete,
            'recebe' => $recebe,
            'margem' => $margem,
            'margem_pct' => $margemPct,
            'margem_desejada' => $margemDesejadaPct,
        ];
    }

    public function limpar(): void
    {
        $this->custo_produto = null;
        $this->preco_venda = null;
        $this->margem_desejada = null;
        $this->percentual_imposto = null;
        $this->custo_frete = null;
        $this->resultado = null;
    }

    public function updatedModo(): void
    {
        $this->resultado = null;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
