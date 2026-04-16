<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class CalculadoraML extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Ferramentas';
    protected static ?string $navigationLabel = 'Calculadora Marketplace';
    protected static ?string $title = 'Calculadora de Preço - Marketplace';
    protected static string $view = 'filament.pages.calculadora-ml';
    protected static ?int $navigationSort = 10;

    public string $marketplace = 'ml';
    public string $modo = 'margem';
    public ?float $custo_produto = null;
    public ?float $preco_venda = null;
    public ?float $margem_desejada = null;
    public string $tipo_anuncio = 'classico';
    public ?float $percentual_imposto = null;
    public string $tipo_frete = 'ME2';
    public ?float $custo_frete_manual = null;
    public ?float $peso_unitario = null;
    public int $quantidade = 1;

    public ?array $resultado = null;

    // Tabela ML
    private static array $tabelaFreteML = [
        '0-0.3'   => [5.65, 6.55, 7.75, 12.35, 14.35, 16.45, 18.45, 20.95],
        '0.3-0.5' => [5.95, 6.65, 7.85, 13.25, 15.45, 17.65, 19.85, 22.55],
        '0.5-1'   => [6.05, 6.75, 7.95, 13.85, 16.15, 18.45, 20.75, 23.65],
        '1-1.5'   => [6.15, 6.85, 8.05, 14.15, 16.45, 18.85, 21.15, 24.65],
        '1.5-2'   => [6.25, 6.95, 8.15, 14.45, 16.85, 19.25, 21.65, 24.65],
        '2-3'     => [6.35, 7.95, 8.55, 15.75, 18.35, 21.05, 23.65, 26.25],
        '3-4'     => [6.45, 8.15, 8.95, 17.05, 19.85, 22.65, 25.55, 28.35],
        '4-5'     => [6.55, 8.35, 9.75, 18.45, 21.55, 24.65, 27.75, 30.75],
        '5-6'     => [6.65, 8.55, 9.95, 25.45, 28.55, 32.65, 35.75, 39.75],
        '6-7'     => [6.75, 8.75, 10.15, 27.05, 31.05, 36.05, 40.05, 44.05],
        '7-8'     => [6.85, 8.95, 10.35, 28.85, 33.65, 38.45, 43.25, 48.05],
        '8-9'     => [6.95, 9.15, 10.55, 29.65, 34.55, 39.55, 44.45, 49.35],
        '9-11'    => [7.05, 9.55, 10.95, 41.25, 48.05, 54.95, 61.75, 68.65],
        '11-13'   => [7.15, 9.95, 11.35, 42.15, 49.25, 56.25, 63.25, 70.25],
        '13-15'   => [7.25, 10.15, 11.55, 45.05, 52.45, 59.95, 67.45, 74.95],
        '15-17'   => [7.35, 10.35, 11.75, 48.55, 56.05, 63.55, 70.75, 78.65],
        '17-20'   => [7.45, 10.55, 11.95, 54.75, 63.85, 72.95, 82.05, 91.15],
        '20-25'   => [7.65, 10.95, 12.15, 64.05, 75.05, 84.75, 95.35, 105.95],
        '25-30'   => [7.75, 11.15, 12.35, 65.95, 75.45, 85.55, 96.25, 106.95],
        '30-40'   => [7.85, 11.35, 12.55, 67.75, 78.95, 88.95, 99.15, 107.05],
        '40-50'   => [7.95, 11.55, 12.75, 70.25, 81.05, 92.05, 102.55, 110.75],
        '50-60'   => [8.05, 11.75, 12.95, 74.95, 86.45, 98.15, 109.35, 118.15],
        '60-70'   => [8.15, 11.95, 13.15, 80.25, 92.95, 105.05, 117.15, 126.55],
        '70-80'   => [8.25, 12.15, 13.35, 83.95, 97.05, 109.85, 122.45, 132.25],
        '80-90'   => [8.35, 12.35, 13.55, 93.25, 107.45, 122.05, 136.05, 146.95],
        '90-100'  => [8.45, 12.55, 13.75, 106.55, 123.95, 139.55, 155.55, 167.95],
        '100-125' => [8.55, 12.75, 13.95, 119.25, 138.05, 156.05, 173.95, 187.95],
        '125-150' => [8.65, 12.75, 14.15, 126.55, 146.15, 165.65, 184.65, 199.45],
        '150+'    => [8.75, 12.95, 14.35, 166.15, 166.15, 166.15, 166.15, 166.15],
    ];

    private static array $faixasPreco = [
        [0, 18.99], [19, 48.99], [49, 78.99], [79, 99.99],
        [100, 119.99], [120, 149.99], [150, 199.99], [200, 999999],
    ];

    // Tabela Shopee
    private static array $tabelaShopee = [
        [0, 79.99, 20, 4],
        [80, 99.99, 14, 16],
        [100, 199.99, 14, 20],
        [200, 499.99, 14, 26],
        [500, 999999, 14, 26],
    ];

    private function calcularComissaoShopee(float $preco): array
    {
        foreach (self::$tabelaShopee as [$min, $max, $pct, $fixo]) {
            if ($preco >= $min && $preco <= $max) {
                $comissao = round($preco * $pct / 100 + $fixo, 2);
                return ['comissao' => $comissao, 'pct' => $pct, 'fixo' => $fixo];
            }
        }
        $last = end(self::$tabelaShopee);
        return ['comissao' => round($preco * $last[2] / 100 + $last[3], 2), 'pct' => $last[2], 'fixo' => $last[3]];
    }

    private function getPesoTotal(): float
    {
        return round(($this->peso_unitario ?? 0) * $this->quantidade, 3);
    }

    private function getCustoTotal(): float
    {
        return round(($this->custo_produto ?? 0) * $this->quantidade, 2);
    }

    private function detectarFaixaPeso(float $peso): ?string
    {
        $faixas = [
            '0-0.3' => [0, 0.3], '0.3-0.5' => [0.3, 0.5], '0.5-1' => [0.5, 1],
            '1-1.5' => [1, 1.5], '1.5-2' => [1.5, 2], '2-3' => [2, 3],
            '3-4' => [3, 4], '4-5' => [4, 5], '5-6' => [5, 6],
            '6-7' => [6, 7], '7-8' => [7, 8], '8-9' => [8, 9],
            '9-11' => [9, 11], '11-13' => [11, 13], '13-15' => [13, 15],
            '15-17' => [15, 17], '17-20' => [17, 20], '20-25' => [20, 25],
            '25-30' => [25, 30], '30-40' => [30, 40], '40-50' => [40, 50],
            '50-60' => [50, 60], '60-70' => [60, 70], '70-80' => [70, 80],
            '80-90' => [80, 90], '90-100' => [90, 100], '100-125' => [100, 125],
            '125-150' => [125, 150], '150+' => [150, 99999],
        ];
        foreach ($faixas as $key => [$min, $max]) {
            if ($peso > $min && $peso <= $max) return $key;
            if ($key === '0-0.3' && $peso <= 0.3 && $peso > 0) return $key;
        }
        return '150+';
    }

    private function getFaixaPesoLabel(string $faixa): string
    {
        $labels = [
            '0-0.3' => 'Até 0,3 kg', '0.3-0.5' => '0,3 a 0,5 kg', '0.5-1' => '0,5 a 1 kg',
            '1-1.5' => '1 a 1,5 kg', '1.5-2' => '1,5 a 2 kg', '2-3' => '2 a 3 kg',
            '3-4' => '3 a 4 kg', '4-5' => '4 a 5 kg', '5-6' => '5 a 6 kg',
            '6-7' => '6 a 7 kg', '7-8' => '7 a 8 kg', '8-9' => '8 a 9 kg',
            '9-11' => '9 a 11 kg', '11-13' => '11 a 13 kg', '13-15' => '13 a 15 kg',
            '15-17' => '15 a 17 kg', '17-20' => '17 a 20 kg', '20-25' => '20 a 25 kg',
            '25-30' => '25 a 30 kg', '30-40' => '30 a 40 kg', '40-50' => '40 a 50 kg',
            '50-60' => '50 a 60 kg', '60-70' => '60 a 70 kg', '70-80' => '70 a 80 kg',
            '80-90' => '80 a 90 kg', '90-100' => '90 a 100 kg', '100-125' => '100 a 125 kg',
            '125-150' => '125 a 150 kg', '150+' => 'Mais de 150 kg',
        ];
        return $labels[$faixa] ?? $faixa;
    }

    private function calcularFreteML(float $preco, float $pesoTotal): float
    {
        if ($this->tipo_frete === 'ME1') return (float) ($this->custo_frete_manual ?? 0);
        if ($pesoTotal <= 0) return 0;
        $faixa = $this->detectarFaixaPeso($pesoTotal);
        if (!$faixa || !isset(self::$tabelaFreteML[$faixa])) return 0;
        $valores = self::$tabelaFreteML[$faixa];
        foreach (self::$faixasPreco as $i => [$min, $max]) {
            if ($preco >= $min && $preco <= $max) return $valores[$i] ?? end($valores);
        }
        return end($valores);
    }

    private function calcularFreteMLIterativo(float $custoTotal, float $pesoTotal, float $comissaoPct, float $impostoPct, float $margemPct): array
    {
        if ($this->tipo_frete === 'ME1' || $pesoTotal <= 0) {
            $frete = (float) ($this->custo_frete_manual ?? 0);
            $divisor = 1 - ($comissaoPct / 100) - ($impostoPct / 100) - ($margemPct / 100);
            if ($divisor <= 0) return ['erro' => true];
            return ['preco' => round(($custoTotal + $frete) / $divisor, 2), 'frete' => $frete];
        }
        $preco = $custoTotal * 2;
        for ($i = 0; $i < 20; $i++) {
            $frete = $this->calcularFreteML($preco, $pesoTotal);
            $divisor = 1 - ($comissaoPct / 100) - ($impostoPct / 100) - ($margemPct / 100);
            if ($divisor <= 0) return ['erro' => true];
            $novoPreco = round(($custoTotal + $frete) / $divisor, 2);
            if (abs($novoPreco - $preco) < 0.01) break;
            $preco = $novoPreco;
        }
        return ['preco' => $preco, 'frete' => $this->calcularFreteML($preco, $pesoTotal)];
    }

    public function calcular(): void
    {
        if (!$this->custo_produto || $this->custo_produto <= 0) { $this->resultado = null; return; }

        if ($this->marketplace === 'shopee') {
            $this->calcularShopee();
        } else {
            $comissaoPct = $this->tipo_anuncio === 'premium' ? 16.5 : 11.5;
            $impostoPct = (float) ($this->percentual_imposto ?? 0);
            if ($this->modo === 'margem') $this->calcularMargemML($comissaoPct, $impostoPct);
            else $this->calcularPrecoIdealML($comissaoPct, $impostoPct);
        }
    }

    private function calcularShopee(): void
    {
        $custoTotal = $this->getCustoTotal();
        $impostoPct = (float) ($this->percentual_imposto ?? 0);

        if ($this->modo === 'margem') {
            if (!$this->preco_venda || $this->preco_venda <= 0) { $this->resultado = null; return; }
            $preco = $this->preco_venda;
            $shopee = $this->calcularComissaoShopee($preco);
            $imposto = round($preco * $impostoPct / 100, 2);
            $recebe = round($preco - $shopee['comissao'], 2);
            $margem = round($recebe - $custoTotal - $imposto, 2);
            $margemPct = $preco > 0 ? round(($margem / $preco) * 100, 1) : 0;

            $this->resultado = [
                'modo' => 'margem', 'marketplace' => 'shopee',
                'preco_venda' => $preco, 'custo_unitario' => $this->custo_produto,
                'custo_total' => $custoTotal, 'quantidade' => $this->quantidade,
                'comissao_pct' => $shopee['pct'], 'comissao_fixa' => $shopee['fixo'],
                'comissao' => $shopee['comissao'],
                'imposto_pct' => $impostoPct, 'imposto' => $imposto,
                'custo_frete' => 0, 'recebe' => $recebe,
                'margem' => $margem, 'margem_pct' => $margemPct,
            ];
        } else {
            if (!$this->margem_desejada) { $this->resultado = null; return; }
            $margemPct = $this->margem_desejada;

            // Iterar: Shopee tem faixas, o % muda conforme o preço
            $preco = $custoTotal * 2;
            for ($i = 0; $i < 30; $i++) {
                $shopee = $this->calcularComissaoShopee($preco);
                $imposto = $preco * $impostoPct / 100;
                // preco = custoTotal + comissao + imposto + margem
                // preco = custoTotal + preco*pct/100 + fixo + preco*imposto/100 + preco*margem/100
                $divisor = 1 - ($shopee['pct'] / 100) - ($impostoPct / 100) - ($margemPct / 100);
                if ($divisor <= 0) { $this->resultado = ['erro' => 'Margem + Comissão + Imposto excedem 100%.']; return; }
                $novoPreco = round(($custoTotal + $shopee['fixo']) / $divisor, 2);
                if (abs($novoPreco - $preco) < 0.01) break;
                $preco = $novoPreco;
            }

            $shopee = $this->calcularComissaoShopee($preco);
            $imposto = round($preco * $impostoPct / 100, 2);
            $recebe = round($preco - $shopee['comissao'], 2);
            $margem = round($recebe - $custoTotal - $imposto, 2);
            $margemPctReal = $preco > 0 ? round(($margem / $preco) * 100, 1) : 0;

            $this->resultado = [
                'modo' => 'preco_ideal', 'marketplace' => 'shopee',
                'preco_venda' => $preco, 'custo_unitario' => $this->custo_produto,
                'custo_total' => $custoTotal, 'quantidade' => $this->quantidade,
                'comissao_pct' => $shopee['pct'], 'comissao_fixa' => $shopee['fixo'],
                'comissao' => $shopee['comissao'],
                'imposto_pct' => $impostoPct, 'imposto' => $imposto,
                'custo_frete' => 0, 'recebe' => $recebe,
                'margem' => $margem, 'margem_pct' => $margemPctReal,
                'margem_desejada' => $margemPct,
            ];
        }
    }

    private function calcularMargemML(float $comissaoPct, float $impostoPct): void
    {
        if (!$this->preco_venda || $this->preco_venda <= 0) { $this->resultado = null; return; }
        $preco = $this->preco_venda;
        $custoTotal = $this->getCustoTotal();
        $pesoTotal = $this->getPesoTotal();
        $custoFrete = $this->calcularFreteML($preco, $pesoTotal);
        $faixaPeso = $pesoTotal > 0 ? $this->detectarFaixaPeso($pesoTotal) : null;

        $comissao = round($preco * $comissaoPct / 100, 2);
        $imposto = round($preco * $impostoPct / 100, 2);
        $recebe = round($preco - $comissao - $custoFrete, 2);
        $margem = round($recebe - $custoTotal - $imposto, 2);
        $margemPct = $preco > 0 ? round(($margem / $preco) * 100, 1) : 0;

        $this->resultado = [
            'modo' => 'margem', 'marketplace' => 'ml',
            'preco_venda' => $preco, 'custo_unitario' => $this->custo_produto,
            'custo_total' => $custoTotal, 'quantidade' => $this->quantidade,
            'peso_unitario' => $this->peso_unitario, 'peso_total' => $pesoTotal,
            'faixa_peso' => $faixaPeso ? $this->getFaixaPesoLabel($faixaPeso) : 'N/A',
            'comissao_pct' => $comissaoPct, 'comissao' => $comissao,
            'imposto_pct' => $impostoPct, 'imposto' => $imposto,
            'custo_frete' => $custoFrete, 'recebe' => $recebe,
            'margem' => $margem, 'margem_pct' => $margemPct,
        ];
    }

    private function calcularPrecoIdealML(float $comissaoPct, float $impostoPct): void
    {
        if (!$this->margem_desejada) { $this->resultado = null; return; }
        $custoTotal = $this->getCustoTotal();
        $pesoTotal = $this->getPesoTotal();
        $calc = $this->calcularFreteMLIterativo($custoTotal, $pesoTotal, $comissaoPct, $impostoPct, $this->margem_desejada);
        if (isset($calc['erro'])) { $this->resultado = ['erro' => 'Margem + Comissão + Imposto excedem 100%.']; return; }

        $preco = $calc['preco'];
        $custoFrete = $calc['frete'];
        $faixaPeso = $pesoTotal > 0 ? $this->detectarFaixaPeso($pesoTotal) : null;
        $comissao = round($preco * $comissaoPct / 100, 2);
        $imposto = round($preco * $impostoPct / 100, 2);
        $recebe = round($preco - $comissao - $custoFrete, 2);
        $margem = round($recebe - $custoTotal - $imposto, 2);
        $margemPctReal = $preco > 0 ? round(($margem / $preco) * 100, 1) : 0;

        $this->resultado = [
            'modo' => 'preco_ideal', 'marketplace' => 'ml',
            'preco_venda' => $preco, 'custo_unitario' => $this->custo_produto,
            'custo_total' => $custoTotal, 'quantidade' => $this->quantidade,
            'peso_unitario' => $this->peso_unitario, 'peso_total' => $pesoTotal,
            'faixa_peso' => $faixaPeso ? $this->getFaixaPesoLabel($faixaPeso) : 'N/A',
            'comissao_pct' => $comissaoPct, 'comissao' => $comissao,
            'imposto_pct' => $impostoPct, 'imposto' => $imposto,
            'custo_frete' => $custoFrete, 'recebe' => $recebe,
            'margem' => $margem, 'margem_pct' => $margemPctReal,
            'margem_desejada' => $this->margem_desejada,
        ];
    }

    public function limpar(): void
    {
        $this->custo_produto = null; $this->preco_venda = null;
        $this->margem_desejada = null; $this->percentual_imposto = null;
        $this->custo_frete_manual = null; $this->peso_unitario = null;
        $this->quantidade = 1; $this->resultado = null;
    }

    public function updatedModo(): void { $this->resultado = null; }
    public function updatedMarketplace(): void { $this->resultado = null; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
