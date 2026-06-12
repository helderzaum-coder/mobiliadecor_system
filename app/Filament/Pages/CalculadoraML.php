<?php

namespace App\Filament\Pages;

use App\Models\CanalVenda;
use App\Models\Cnpj;
use App\Models\ImpostoMensal;
use App\Models\RegraComissao;
use Filament\Pages\Page;

class CalculadoraML extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Ferramentas';
    protected static ?string $navigationLabel = 'Calculadora Marketplace';
    protected static ?string $title = 'Calculadora de Preço - Marketplace';
    protected static string $view = 'filament.pages.calculadora-ml';
    protected static ?int $navigationSort = 10;

    public string $modo = 'margem';
    public ?float $custo_produto = null;
    public ?float $preco_venda = null;
    public ?float $preco_de_pct = null;
    public ?float $preco_por_pct = null;
    public ?float $peso_unitario = null;
    public int $quantidade = 1;

    // Cubagem ML
    public bool $usar_cubagem = false;
    public ?float $cubagem_altura = null;
    public ?float $cubagem_comprimento = null;
    public ?float $cubagem_largura = null;

    // Frete ML
    public string $tipo_frete = 'ME2';
    public ?float $custo_frete_manual = null;
    public bool $frete_manual_override = false;

    public ?array $resultados = null;

    // ─── Canais config ─────────────────────────────────────────

    private function getCanaisConfig(): array
    {
        $canaisDB = CanalVenda::where('ativo', true)->with(['regrasComissao' => fn($q) => $q->where('ativo', true)])->get()->keyBy('nome_canal');

        // Helper: busca regra de comissão de um canal, com filtro opcional de tipo anúncio/frete ML
        $getRegra = function (?CanalVenda $canal, ?string $tipoAnuncio = null, ?string $tipoFrete = null) {
            if (!$canal) return ['pct' => 0, 'fixo' => 0];
            $regras = $canal->regrasComissao;
            if ($tipoAnuncio) {
                $regras = $regras->filter(fn($r) => !$r->ml_tipo_anuncio || $r->ml_tipo_anuncio === $tipoAnuncio);
            }
            if ($tipoFrete) {
                $regras = $regras->filter(fn($r) => !$r->ml_tipo_frete || $r->ml_tipo_frete === $tipoFrete);
            }
            $regra = $regras->first();
            return ['pct' => (float) ($regra->percentual ?? 0), 'fixo' => (float) ($regra->valor_fixo ?? 0)];
        };

        $mlDB = $canaisDB->get('Mercadolivre');
        $shopeeDB = $canaisDB->get('Shopee');
        $magaluDB = $canaisDB->get('Magalu');
        $webconDB = $canaisDB->get('Webcontinental');
        $mmDB = $canaisDB->get('Madeira Madeira');
        $amazonDB = $canaisDB->get('Amazon');
        $viaDB = $canaisDB->get('Via (Cnova)');
        $tiktokDB = $canaisDB->get('Tiktokshop');
        $leroyDB = $canaisDB->get('LeroyMerlin');
        $siteDB = $canaisDB->get('Site Mobília') ?? $canaisDB->first(fn($c) => str_contains($c->nome_canal, 'Mob'));

        $tipoFreteAtual = $this->tipo_frete;

        // Comissões do banco
        $mlPremium = $getRegra($mlDB, 'Premium', $tipoFreteAtual);
        $mlClassico = $getRegra($mlDB, 'Clássico', $tipoFreteAtual);
        $magalu = $getRegra($magaluDB);
        $webcon = $getRegra($webconDB);
        $mm = $getRegra($mmDB);
        $via = $getRegra($viaDB);
        $tiktok = $getRegra($tiktokDB);
        $leroy = $getRegra($leroyDB);
        $siteCartao = ['pct' => 0, 'fixo' => 0];
        if ($siteDB) {
            $regraCartao = $siteDB->regrasComissao->first(fn($r) => str_contains(strtolower($r->nome_regra ?? ''), 'cart') && str_contains($r->nome_regra ?? '', '6'));
            if (!$regraCartao) {
                $regraCartao = $siteDB->regrasComissao->sortByDesc('percentual')->first(fn($r) => str_contains(strtolower($r->nome_regra ?? ''), 'cart'));
            }
            $siteCartao = ['pct' => (float) ($regraCartao->percentual ?? 0), 'fixo' => (float) ($regraCartao->valor_fixo ?? 0)];
        }
        $sitePix = $siteDB ? $siteDB->regrasComissao->first(fn($r) => str_contains(strtolower($r->nome_regra ?? ''), 'pix')) : null;
        $sitePixFixo = (float) ($sitePix->valor_fixo ?? 0.99);

        $nota = fn(?CanalVenda $c, string $default) => $c->tipo_nota ?? $default;
        $impFrete = fn(?CanalVenda $c) => (bool) ($c->imposto_sobre_frete ?? false);
        $antecipacao = fn(?CanalVenda $c) => (float) ($c->percentual_antecipacao ?? 0);

        return [
            'ml_premium_1'  => ['label' => 'ML Premium', 'cor' => '#8b5cf6', 'icone' => '🟣', 'comissao_pct' => $mlPremium['pct'], 'fixo' => $mlPremium['fixo'], 'tipo_nota' => $nota($mlDB, 'produto'), 'imposto_sobre_frete' => $impFrete($mlDB), 'antecipacao_pct' => $antecipacao($mlDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'ml'],
            'ml_classico_1' => ['label' => 'ML Clássico', 'cor' => '#6366f1', 'icone' => '🔵', 'comissao_pct' => $mlClassico['pct'], 'fixo' => $mlClassico['fixo'], 'tipo_nota' => $nota($mlDB, 'produto'), 'imposto_sobre_frete' => $impFrete($mlDB), 'antecipacao_pct' => $antecipacao($mlDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'ml'],
            'ml_premium_2'  => ['label' => 'ML Premium', 'cor' => '#8b5cf6', 'icone' => '🟣', 'comissao_pct' => $mlPremium['pct'], 'fixo' => $mlPremium['fixo'], 'tipo_nota' => $nota($mlDB, 'produto'), 'imposto_sobre_frete' => $impFrete($mlDB), 'antecipacao_pct' => $antecipacao($mlDB), 'id_cnpj' => 2, 'cnpj_label' => 'HES Móveis', 'tipo' => 'ml'],
            'ml_classico_2' => ['label' => 'ML Clássico', 'cor' => '#6366f1', 'icone' => '🔵', 'comissao_pct' => $mlClassico['pct'], 'fixo' => $mlClassico['fixo'], 'tipo_nota' => $nota($mlDB, 'produto'), 'imposto_sobre_frete' => $impFrete($mlDB), 'antecipacao_pct' => $antecipacao($mlDB), 'id_cnpj' => 2, 'cnpj_label' => 'HES Móveis', 'tipo' => 'ml'],
            'shopee_1'      => ['label' => 'Shopee', 'cor' => '#ea580c', 'icone' => '🟠', 'comissao_pct' => 0, 'fixo' => 0, 'tipo_nota' => $nota($shopeeDB, 'meia_nota'), 'imposto_sobre_frete' => $impFrete($shopeeDB), 'antecipacao_pct' => $antecipacao($shopeeDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'shopee'],
            'shopee_2'      => ['label' => 'Shopee', 'cor' => '#ea580c', 'icone' => '🟠', 'comissao_pct' => 0, 'fixo' => 0, 'tipo_nota' => $nota($shopeeDB, 'meia_nota'), 'imposto_sobre_frete' => $impFrete($shopeeDB), 'antecipacao_pct' => $antecipacao($shopeeDB), 'id_cnpj' => 2, 'cnpj_label' => 'HES Móveis', 'tipo' => 'shopee'],
            'magalu_1'      => ['label' => 'Magalu', 'cor' => '#2563eb', 'icone' => '🔷', 'comissao_pct' => $magalu['pct'], 'fixo' => $magalu['fixo'], 'tipo_nota' => $nota($magaluDB, 'cheia'), 'imposto_sobre_frete' => $impFrete($magaluDB), 'antecipacao_pct' => $antecipacao($magaluDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'fixo'],
            'webcon_1'      => ['label' => 'Webcontinental', 'cor' => '#0891b2', 'icone' => '🌐', 'comissao_pct' => $webcon['pct'], 'fixo' => $webcon['fixo'], 'tipo_nota' => $nota($webconDB, 'cheia'), 'imposto_sobre_frete' => $impFrete($webconDB), 'antecipacao_pct' => $antecipacao($webconDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'fixo'],
            'madeiramadeira_1' => ['label' => 'Madeira Madeira', 'cor' => '#16a34a', 'icone' => '🌳', 'comissao_pct' => $mm['pct'], 'fixo' => $mm['fixo'], 'tipo_nota' => $nota($mmDB, 'cheia'), 'imposto_sobre_frete' => $impFrete($mmDB), 'antecipacao_pct' => $antecipacao($mmDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'fixo'],
            'amazon_1'         => ['label' => 'Amazon', 'cor' => '#f59e0b', 'icone' => '📦', 'comissao_pct' => 0, 'fixo' => 0, 'tipo_nota' => $nota($amazonDB, 'produto'), 'imposto_sobre_frete' => $impFrete($amazonDB), 'antecipacao_pct' => $antecipacao($amazonDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'amazon'],
            'via_1'            => ['label' => 'Via (Cnova)', 'cor' => '#dc2626', 'icone' => '🔴', 'comissao_pct' => $via['pct'], 'fixo' => $via['fixo'], 'tipo_nota' => $nota($viaDB, 'produto'), 'imposto_sobre_frete' => $impFrete($viaDB), 'antecipacao_pct' => $antecipacao($viaDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'fixo'],
            'tiktok_1'         => ['label' => 'Tiktokshop', 'cor' => '#000000', 'icone' => '🎵', 'comissao_pct' => $tiktok['pct'], 'fixo' => $tiktok['fixo'], 'tipo_nota' => $nota($tiktokDB, 'cheia'), 'imposto_sobre_frete' => $impFrete($tiktokDB), 'antecipacao_pct' => $antecipacao($tiktokDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'fixo'],
            'leroy_1'          => ['label' => 'LeroyMerlin', 'cor' => '#65a30d', 'icone' => '🏠', 'comissao_pct' => $leroy['pct'], 'fixo' => $leroy['fixo'], 'tipo_nota' => $nota($leroyDB, 'produto'), 'imposto_sobre_frete' => $impFrete($leroyDB), 'antecipacao_pct' => $antecipacao($leroyDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'fixo'],
            'site_cartao_1'    => ['label' => 'Site (Cartão 6x)', 'cor' => '#7c3aed', 'icone' => '💳', 'comissao_pct' => $siteCartao['pct'], 'fixo' => $siteCartao['fixo'], 'tipo_nota' => $nota($siteDB, 'cheia'), 'imposto_sobre_frete' => $impFrete($siteDB), 'antecipacao_pct' => $antecipacao($siteDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'fixo'],
            'site_pix_1'       => ['label' => 'Site (Pix -15%)', 'cor' => '#059669', 'icone' => '💲', 'comissao_pct' => 0, 'fixo' => $sitePixFixo, 'tipo_nota' => $nota($siteDB, 'cheia'), 'imposto_sobre_frete' => $impFrete($siteDB), 'antecipacao_pct' => $antecipacao($siteDB), 'id_cnpj' => 1, 'cnpj_label' => 'HES Decor', 'tipo' => 'site_pix'],
        ];
    }

    private function getImpostosPorCnpj(): array
    {
        $mes = now()->month;
        $ano = now()->year;
        $impostos = ImpostoMensal::where('mes_referencia', $mes)
            ->where('ano_referencia', $ano)
            ->pluck('percentual_imposto', 'id_cnpj')
            ->toArray();

        // Fallback: se não tiver do mês atual, pega o mais recente
        if (empty($impostos)) {
            $impostos = ImpostoMensal::orderByDesc('ano_referencia')
                ->orderByDesc('mes_referencia')
                ->get()
                ->pluck('percentual_imposto', 'id_cnpj')
                ->toArray();
        }

        return $impostos;
    }

    // ─── Tabelas ───────────────────────────────────────────────

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

    private static array $tabelaShopee = [
        [0, 79.99, 20, 4], [80, 99.99, 14, 16], [100, 199.99, 14, 20],
        [200, 499.99, 14, 26], [500, 999999, 14, 26],
    ];

    private static array $tabelaAmazon = [
        [0.01, 199.99, 15, 0], [200, 999999, 10, 30],
    ];

    private function calcularComissaoAmazon(float $preco): array
    {
        foreach (self::$tabelaAmazon as [$min, $max, $pct, $fixo]) {
            if ($preco >= $min && $preco <= $max) {
                return ['comissao' => round($preco * $pct / 100 + $fixo, 2), 'pct' => $pct, 'fixo' => $fixo];
            }
        }
        $last = end(self::$tabelaAmazon);
        return ['comissao' => round($preco * $last[2] / 100 + $last[3], 2), 'pct' => $last[2], 'fixo' => $last[3]];
    }

    private function getPesoCubado(): float
    {
        if (!$this->usar_cubagem || !$this->cubagem_altura || !$this->cubagem_comprimento || !$this->cubagem_largura) return 0;
        return round(($this->cubagem_altura * $this->cubagem_comprimento * $this->cubagem_largura) / 6000, 3);
    }

    private function getPesoTotal(): float
    {
        $pesoReal = round(($this->peso_unitario ?? 0) * $this->quantidade, 3);
        $pesoCubado = $this->getPesoCubado() * $this->quantidade;
        return max($pesoReal, $pesoCubado);
    }

    private function getCustoTotal(): float { return round(($this->custo_produto ?? 0) * $this->quantidade, 2); }

    private function detectarFaixaPeso(float $peso): string
    {
        $faixas = [
            '0-0.3'=>[0,0.3],'0.3-0.5'=>[0.3,0.5],'0.5-1'=>[0.5,1],'1-1.5'=>[1,1.5],'1.5-2'=>[1.5,2],
            '2-3'=>[2,3],'3-4'=>[3,4],'4-5'=>[4,5],'5-6'=>[5,6],'6-7'=>[6,7],'7-8'=>[7,8],'8-9'=>[8,9],
            '9-11'=>[9,11],'11-13'=>[11,13],'13-15'=>[13,15],'15-17'=>[15,17],'17-20'=>[17,20],
            '20-25'=>[20,25],'25-30'=>[25,30],'30-40'=>[30,40],'40-50'=>[40,50],'50-60'=>[50,60],
            '60-70'=>[60,70],'70-80'=>[70,80],'80-90'=>[80,90],'90-100'=>[90,100],
            '100-125'=>[100,125],'125-150'=>[125,150],'150+'=>[150,99999],
        ];
        foreach ($faixas as $key => [$min, $max]) {
            if ($key === '0-0.3' && $peso <= 0.3 && $peso > 0) return $key;
            if ($peso > $min && $peso <= $max) return $key;
        }
        return '150+';
    }

    private function getFaixaPesoLabel(string $f): string
    {
        $l = ['0-0.3'=>'Até 0,3 kg','0.3-0.5'=>'0,3 a 0,5 kg','0.5-1'=>'0,5 a 1 kg','1-1.5'=>'1 a 1,5 kg',
            '1.5-2'=>'1,5 a 2 kg','2-3'=>'2 a 3 kg','3-4'=>'3 a 4 kg','4-5'=>'4 a 5 kg','5-6'=>'5 a 6 kg',
            '6-7'=>'6 a 7 kg','7-8'=>'7 a 8 kg','8-9'=>'8 a 9 kg','9-11'=>'9 a 11 kg','11-13'=>'11 a 13 kg',
            '13-15'=>'13 a 15 kg','15-17'=>'15 a 17 kg','17-20'=>'17 a 20 kg','20-25'=>'20 a 25 kg',
            '25-30'=>'25 a 30 kg','30-40'=>'30 a 40 kg','40-50'=>'40 a 50 kg','50-60'=>'50 a 60 kg',
            '60-70'=>'60 a 70 kg','70-80'=>'70 a 80 kg','80-90'=>'80 a 90 kg','90-100'=>'90 a 100 kg',
            '100-125'=>'100 a 125 kg','125-150'=>'125 a 150 kg','150+'=>'Mais de 150 kg'];
        return $l[$f] ?? $f;
    }

    private function calcularFreteML(float $preco, float $pesoTotal): float
    {
        if ($this->tipo_frete === 'ME1') return (float) ($this->custo_frete_manual ?? 0);
        if ($this->frete_manual_override && $this->custo_frete_manual > 0) return (float) $this->custo_frete_manual;
        if ($pesoTotal <= 0) return 0;
        $faixa = $this->detectarFaixaPeso($pesoTotal);
        if (!isset(self::$tabelaFreteML[$faixa])) return 0;
        $valores = self::$tabelaFreteML[$faixa];
        foreach (self::$faixasPreco as $i => [$min, $max]) {
            if ($preco >= $min && $preco <= $max) return $valores[$i] ?? end($valores);
        }
        return end($valores);
    }

    private function calcularComissaoShopee(float $preco): array
    {
        foreach (self::$tabelaShopee as [$min, $max, $pct, $fixo]) {
            if ($preco >= $min && $preco <= $max) {
                return ['comissao' => round($preco * $pct / 100 + $fixo, 2), 'pct' => $pct, 'fixo' => $fixo];
            }
        }
        $last = end(self::$tabelaShopee);
        return ['comissao' => round($preco * $last[2] / 100 + $last[3], 2), 'pct' => $last[2], 'fixo' => $last[3]];
    }

    /**
     * Calcula imposto baseado no tipo_nota e imposto_sobre_frete do canal
     * cheia = imposto sobre preço total
     * produto = imposto sobre (preço - frete), a menos que imposto_sobre_frete=true
     * meia_nota = imposto sobre metade do preço
     */
    private function calcularImposto(float $preco, float $frete, string $tipoNota, float $impostoPct, bool $impostoSobreFrete = false): float
    {
        $base = match ($tipoNota) {
            'produto' => $impostoSobreFrete ? $preco : ($preco - $frete),
            'meia_nota' => $impostoSobreFrete ? ($preco + $frete) / 2 : $preco / 2,
            default => $preco, // cheia
        };
        return round(max(0, $base) * $impostoPct / 100, 2);
    }

    // ─── Cálculos ──────────────────────────────────────────────

    public function calcular(): void
    {
        if (!$this->custo_produto || $this->custo_produto <= 0) { $this->resultados = null; return; }

        if ($this->modo === 'margem') {
            if (!$this->preco_venda || $this->preco_venda <= 0) { $this->resultados = null; return; }
            $this->calcularMargem();
        } else {
            if (!$this->preco_de_pct && !$this->preco_por_pct) { $this->resultados = null; return; }
            $this->calcularPrecoIdeal();
        }
    }

    private function calcularMargem(): void
    {
        $preco = $this->preco_venda;
        $custoTotal = $this->getCustoTotal();
        $pesoTotal = $this->getPesoTotal();
        $impostos = $this->getImpostosPorCnpj();
        $canaisConfig = $this->getCanaisConfig();

        $canais = [];
        $freteML = $this->calcularFreteML($preco, $pesoTotal);

        foreach ($canaisConfig as $key => $cfg) {
            $impostoPct = (float) ($impostos[$cfg['id_cnpj']] ?? 0);

            // Calcular comissão
            if ($cfg['tipo'] === 'shopee') {
                $s = $this->calcularComissaoShopee($preco);
                $comissao = $s['comissao'];
                $comissaoPct = $s['pct'];
                $comissaoFixa = $s['fixo'];
                $frete = 0;
            } elseif ($cfg['tipo'] === 'ml') {
                $comissao = round($preco * $cfg['comissao_pct'] / 100, 2);
                $comissaoPct = $cfg['comissao_pct'];
                $comissaoFixa = null;
                $frete = $freteML;
            } elseif ($cfg['tipo'] === 'amazon') {
                $a = $this->calcularComissaoAmazon($preco);
                $comissao = $a['comissao'];
                $comissaoPct = $a['pct'];
                $comissaoFixa = $a['fixo'];
                $frete = 0;
            } elseif ($cfg['tipo'] === 'site_pix') {
                // Pix: preço é o de venda com 15% de desconto
                $precoPix = round($preco * 0.85, 2);
                $comissao = $cfg['fixo'];
                $comissaoPct = 0;
                $comissaoFixa = $cfg['fixo'];
                $frete = 0;
                $imposto = $this->calcularImposto($precoPix, 0, $cfg['tipo_nota'], $impostoPct, $cfg['imposto_sobre_frete']);
                $antecipacao = round($precoPix * ($cfg['antecipacao_pct'] ?? 0) / 100, 2);
                $recebe = round($precoPix - $comissao - $antecipacao, 2);
                $margem = round($recebe - $custoTotal - $imposto, 2);

                $canais[] = [
                    'key' => $key,
                    'canal' => $cfg['label'], 'cor' => $cfg['cor'], 'icone' => $cfg['icone'],
                    'cnpj_label' => $cfg['cnpj_label'], 'id_cnpj' => $cfg['id_cnpj'],
                    'comissao_pct' => 0, 'comissao' => $comissao,
                    'comissao_fixa' => $cfg['fixo'],
                    'antecipacao_pct' => $cfg['antecipacao_pct'] ?? 0, 'antecipacao' => $antecipacao,
                    'frete' => 0, 'recebe' => $recebe,
                    'imposto_pct' => $impostoPct, 'imposto' => $imposto,
                    'tipo_nota' => $cfg['tipo_nota'],
                    'preco_pix' => $precoPix,
                    'margem' => $margem,
                    'margem_pct' => $precoPix > 0 ? round(($margem / $precoPix) * 100, 1) : 0,
                ];
                continue;
            } else { // fixo (magalu etc)
                $comissao = round($preco * $cfg['comissao_pct'] / 100 + $cfg['fixo'], 2);
                $comissaoPct = $cfg['comissao_pct'];
                $comissaoFixa = $cfg['fixo'];
                $frete = 0;
            }

            $imposto = $this->calcularImposto($preco, $frete, $cfg['tipo_nota'], $impostoPct, $cfg['imposto_sobre_frete']);
            $antecipacao = round($preco * ($cfg['antecipacao_pct'] ?? 0) / 100, 2);
            $recebe = round($preco - $comissao - $frete - $antecipacao, 2);
            $margem = round($recebe - $custoTotal - $imposto, 2);

            $canais[] = [
                'key' => $key,
                'canal' => $cfg['label'], 'cor' => $cfg['cor'], 'icone' => $cfg['icone'],
                'cnpj_label' => $cfg['cnpj_label'], 'id_cnpj' => $cfg['id_cnpj'],
                'comissao_pct' => $comissaoPct, 'comissao' => $comissao,
                'comissao_fixa' => $comissaoFixa,
                'antecipacao_pct' => $cfg['antecipacao_pct'] ?? 0, 'antecipacao' => $antecipacao,
                'frete' => $frete, 'recebe' => $recebe,
                'imposto_pct' => $impostoPct, 'imposto' => $imposto,
                'tipo_nota' => $cfg['tipo_nota'],
                'margem' => $margem,
                'margem_pct' => $preco > 0 ? round(($margem / $preco) * 100, 1) : 0,
            ];
        }

        $faixaPeso = $pesoTotal > 0 ? $this->getFaixaPesoLabel($this->detectarFaixaPeso($pesoTotal)) : null;
        if (!$this->frete_manual_override && $this->tipo_frete === 'ME2') $this->custo_frete_manual = $freteML;

        $this->resultados = [
            'modo' => 'margem',
            'preco_venda' => $preco,
            'custo_unitario' => $this->custo_produto,
            'custo_total' => $custoTotal,
            'quantidade' => $this->quantidade,
            'peso_total' => $pesoTotal,
            'faixa_peso' => $faixaPeso,
            'canais' => $canais,
        ];
    }

    private function calcularPrecoIdeal(): void
    {
        $custoTotal = $this->getCustoTotal();
        $pesoTotal = $this->getPesoTotal();
        $impostos = $this->getImpostosPorCnpj();
        $canaisConfig = $this->getCanaisConfig();
        $faixaPeso = $pesoTotal > 0 ? $this->getFaixaPesoLabel($this->detectarFaixaPeso($pesoTotal)) : null;

        $margens = [];
        if ($this->preco_de_pct) $margens['preco_de'] = $this->preco_de_pct;
        if ($this->preco_por_pct) $margens['preco_por'] = $this->preco_por_pct;

        $canais = [];

        foreach ($canaisConfig as $key => $cfg) {
            $impostoPct = (float) ($impostos[$cfg['id_cnpj']] ?? 0);

            foreach ($margens as $tipo => $mp) {
                // site_pix: usa o preço do site_cartao com -15%
                if ($cfg['tipo'] === 'site_pix') {
                    $precoCartao = $canais['site_cartao_1'][$tipo]['preco_venda'] ?? null;
                    if (!$precoCartao) continue;
                    $precoPix = round($precoCartao * 0.85, 2);
                    $comissao = $cfg['fixo'];
                    $imposto = $this->calcularImposto($precoPix, 0, $cfg['tipo_nota'], $impostoPct, $cfg['imposto_sobre_frete']);
                    $recebe = round($precoPix - $comissao, 2);
                    $margem = round($recebe - $custoTotal - $imposto, 2);

                    $canais[$key][$tipo] = [
                        'preco_venda' => $precoPix,
                        'comissao_pct' => 0, 'comissao' => $comissao,
                        'comissao_fixa' => $cfg['fixo'],
                        'frete' => 0, 'recebe' => $recebe,
                        'imposto_pct' => $impostoPct, 'imposto' => $imposto,
                        'tipo_nota' => $cfg['tipo_nota'],
                        'margem' => $margem,
                        'margem_pct' => $precoPix > 0 ? round(($margem / $precoPix) * 100, 1) : 0,
                    ];
                    continue;
                }

                $preco = $this->calcularPrecoIterativo($custoTotal, $pesoTotal, $cfg, $impostoPct, $mp);
                if (!$preco) continue;

                // Recalcular detalhes com o preço encontrado
                if ($cfg['tipo'] === 'shopee') {
                    $s = $this->calcularComissaoShopee($preco);
                    $comissao = $s['comissao'];
                    $comissaoPct = $s['pct'];
                    $comissaoFixa = $s['fixo'];
                    $frete = 0;
                } elseif ($cfg['tipo'] === 'ml') {
                    $comissao = round($preco * $cfg['comissao_pct'] / 100, 2);
                    $comissaoPct = $cfg['comissao_pct'];
                    $comissaoFixa = null;
                    $frete = $this->calcularFreteML($preco, $pesoTotal);
                } elseif ($cfg['tipo'] === 'amazon') {
                    $a = $this->calcularComissaoAmazon($preco);
                    $comissao = $a['comissao'];
                    $comissaoPct = $a['pct'];
                    $comissaoFixa = $a['fixo'];
                    $frete = 0;
                } else {
                    $comissao = round($preco * $cfg['comissao_pct'] / 100 + $cfg['fixo'], 2);
                    $comissaoPct = $cfg['comissao_pct'];
                    $comissaoFixa = $cfg['fixo'];
                    $frete = 0;
                }

                $imposto = $this->calcularImposto($preco, $frete, $cfg['tipo_nota'], $impostoPct, $cfg['imposto_sobre_frete']);
                $antecipacao = round($preco * ($cfg['antecipacao_pct'] ?? 0) / 100, 2);
                $recebe = round($preco - $comissao - $frete - $antecipacao, 2);
                $margem = round($recebe - $custoTotal - $imposto, 2);

                $canais[$key][$tipo] = [
                    'preco_venda' => $preco,
                    'comissao_pct' => $comissaoPct, 'comissao' => $comissao,
                    'comissao_fixa' => $comissaoFixa,
                    'antecipacao_pct' => $cfg['antecipacao_pct'] ?? 0, 'antecipacao' => $antecipacao,
                    'frete' => $frete, 'recebe' => $recebe,
                    'imposto_pct' => $impostoPct, 'imposto' => $imposto,
                    'tipo_nota' => $cfg['tipo_nota'],
                    'margem' => $margem,
                    'margem_pct' => $preco > 0 ? round(($margem / $preco) * 100, 1) : 0,
                ];
            }
        }

        $this->resultados = [
            'modo' => 'preco_ideal',
            'custo_unitario' => $this->custo_produto,
            'custo_total' => $custoTotal,
            'quantidade' => $this->quantidade,
            'peso_total' => $pesoTotal,
            'faixa_peso' => $faixaPeso,
            'preco_de_pct' => $this->preco_de_pct,
            'preco_por_pct' => $this->preco_por_pct,
            'canais' => $canais,
            'canais_config' => $canaisConfig,
        ];
    }

    private function calcularPrecoIterativo(float $custoTotal, float $pesoTotal, array $cfg, float $impostoPct, float $margemPct): ?float
    {
        $antecipacaoPct = $cfg['antecipacao_pct'] ?? 0;
        // Ajustar imposto efetivo conforme tipo_nota
        $impostoEfetivo = match ($cfg['tipo_nota']) {
            'meia_nota' => $impostoPct / 2,
            'produto' => $impostoPct, // será ajustado iterativamente para ML
            default => $impostoPct,
        };

        if ($cfg['tipo'] === 'ml') {
            // ML: imposto sobre (preço - frete), precisa iteração
            $divisorBase = 1 - ($cfg['comissao_pct'] / 100) - ($antecipacaoPct / 100) - ($impostoPct / 100) - ($margemPct / 100);
            if ($divisorBase <= 0) return null;

            if ($this->tipo_frete === 'ME1' || $pesoTotal <= 0) {
                $frete = (float) ($this->custo_frete_manual ?? 0);
                $preco = ($custoTotal + $frete) / $divisorBase;
                $impCredito = round($frete * $impostoPct / 100, 2);
                return round(($custoTotal + $frete - $impCredito) / (1 - ($cfg['comissao_pct'] / 100) - ($antecipacaoPct / 100) - ($impostoPct / 100) - ($margemPct / 100)) + $impCredito / (1 - ($cfg['comissao_pct'] / 100) - ($antecipacaoPct / 100) - ($margemPct / 100)), 2);
            }

            // Iteração com frete variável
            $preco = $custoTotal * 2;
            for ($i = 0; $i < 25; $i++) {
                $frete = $this->calcularFreteML($preco, $pesoTotal);
                $imposto = $this->calcularImposto($preco, $frete, $cfg['tipo_nota'], $impostoPct, $cfg['imposto_sobre_frete'] ?? false);
                $comissao = $preco * $cfg['comissao_pct'] / 100;
                $antecipacao = $preco * $antecipacaoPct / 100;
                $novoPreco = round(($custoTotal + $frete + $imposto + $comissao + $antecipacao) / (1 - ($margemPct / 100)), 2);
                if (abs($novoPreco - $preco) < 0.01) break;
                $preco = $novoPreco;
            }
            return $preco;

        } elseif ($cfg['tipo'] === 'shopee') {
            $preco = $custoTotal * 2;
            for ($i = 0; $i < 30; $i++) {
                $s = $this->calcularComissaoShopee($preco);
                $imposto = $this->calcularImposto($preco, 0, $cfg['tipo_nota'], $impostoPct, $cfg['imposto_sobre_frete'] ?? false);
                $novoPreco = round(($custoTotal + $s['fixo'] + $imposto) / (1 - ($s['pct'] / 100) - ($antecipacaoPct / 100) - ($margemPct / 100)), 2);
                if (abs($novoPreco - $preco) < 0.01) break;
                $preco = $novoPreco;
            }
            return $preco;

        } elseif ($cfg['tipo'] === 'amazon') {
            $preco = $custoTotal * 2;
            for ($i = 0; $i < 30; $i++) {
                $a = $this->calcularComissaoAmazon($preco);
                $imposto = $this->calcularImposto($preco, 0, $cfg['tipo_nota'], $impostoPct, $cfg['imposto_sobre_frete'] ?? false);
                $novoPreco = round(($custoTotal + $a['fixo'] + $imposto) / (1 - ($a['pct'] / 100) - ($antecipacaoPct / 100) - ($margemPct / 100)), 2);
                if (abs($novoPreco - $preco) < 0.01) break;
                $preco = $novoPreco;
            }
            return $preco;

        } elseif ($cfg['tipo'] === 'site_pix') {
            return null; // calculado a partir do site_cartao

        } else {
            // Fixo (magalu etc)
            $divisor = 1 - ($cfg['comissao_pct'] / 100) - ($antecipacaoPct / 100) - ($impostoPct / 100) - ($margemPct / 100);
            if ($divisor <= 0) return null;
            return round(($custoTotal + $cfg['fixo']) / $divisor, 2);
        }
    }

    public function limpar(): void
    {
        $this->custo_produto = null;
        $this->preco_venda = null;
        $this->preco_de_pct = null;
        $this->preco_por_pct = null;
        $this->custo_frete_manual = null;
        $this->peso_unitario = null;
        $this->quantidade = 1;
        $this->resultados = null;
        $this->frete_manual_override = false;
        $this->usar_cubagem = false;
        $this->cubagem_altura = null;
        $this->cubagem_comprimento = null;
        $this->cubagem_largura = null;
    }

    public function updatedModo(): void { $this->resultados = null; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'marketing']) ?? false;
    }
}
