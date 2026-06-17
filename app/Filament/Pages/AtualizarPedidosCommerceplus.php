<?php

namespace App\Filament\Pages;

use App\Services\Bling\BlingClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class AtualizarPedidosCommerceplus extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'Ferramentas';
    protected static ?string $navigationLabel = 'Atualizar Pedidos CP';
    protected static ?string $title = 'Atualizar Pedidos CommercePlus';
    protected static string $view = 'filament.pages.atualizar-pedidos-commerceplus';

    public int $etapaAtual = 1;
    public string $blingAccount = 'primary';

    // Etapa 1 - NF-e lançadas
    public string $numerosNfe = '';
    public array $nfesLancadas = []; // [{numero, chave, serie, valor}]

    // Etapa 2 - Planilha CommercePlus
    public $planilhaCp = null;
    public array $pedidosCp = []; // [{id_pedido_cp, numero_nfe, serie_nfe, chave_nfe}]

    // Etapa 3 - Vinculação NF-e ↔ Pedido CP
    public array $vinculacoes = []; // [{id_pedido_cp, numero_nfe, chave_nfe, serie_nfe, transportadora, codigo_rastreio}]

    // Etapa 4 - Planilha final
    public array $planilhaFinal = [];

    // Transportadoras que usam SSW: nome (parcial) => CNPJ
    private const TRANSPORTADORAS_SSW = [
        'lyon' => '30223802000121',
        'tnt' => '03789971000137',
        'patrus' => '05765527000189',
        'rodoviario' => '60627387000102',
    ];

    // Transportadoras que usam SSW: nome (parcial) => CNPJ
    private const TRANSPORTADORAS_SSW = [
        'lyon' => '30223802000121',
        'tnt' => '03789971000137',
        'patrus' => '05765527000189',
        'rodoviario' => '60627387000102',
    ];

    public function mount(): void
    {
        //
    }

    /**
     * Etapa 1: Salvar NF-e lançadas no envio
     */
    public function salvarNfes(): void
    {
        $numeros = array_filter(
            array_map('trim', preg_split('/[\n,;]+/', $this->numerosNfe))
        );

        if (empty($numeros)) {
            Notification::make()->title('Informe ao menos um número de NF-e')->warning()->send();
            return;
        }

        $this->nfesLancadas = [];
        $client = new BlingClient($this->blingAccount);

        foreach ($numeros as $nfeNumero) {
            $nfeNumeroFormatado = str_pad(ltrim($nfeNumero, '0'), 6, '0', STR_PAD_LEFT);

            // Buscar dados da NF-e no Bling
            $nfeRes = $client->getNfes(['numero' => $nfeNumeroFormatado]);
            $nfeData = $nfeRes['body']['data'][0] ?? null;

            $info = [
                'numero' => ltrim($nfeNumero, '0'),
                'chave' => '',
                'serie' => '',
                'valor' => 0,
                'xml_url' => '',
                'transportadora' => '',
                'encontrada' => false,
            ];

            if ($nfeData) {
                $detalhe = $client->getNfe((int) $nfeData['id']);
                $nfeInfo = $detalhe['body']['data'] ?? $nfeData;

                $info['chave'] = $nfeInfo['chaveAcesso'] ?? '';
                $info['serie'] = (string) ($nfeInfo['serie'] ?? '1');
                $info['valor'] = (float) ($nfeInfo['valorNota'] ?? 0);
                $info['xml_url'] = $nfeInfo['xml'] ?? '';
                $info['encontrada'] = true;

                // Buscar transportadora
                $transporte = $nfeInfo['transporte'] ?? [];
                $transportadora = $transporte['transportador']['nome'] ?? '';
                if (empty($transportadora)) {
                    $transportadora = $transporte['fretePorConta'] ?? '';
                }
                $info['transportadora'] = $transportadora;
            }

            $this->nfesLancadas[] = $info;
        }

        $encontradas = count(array_filter($this->nfesLancadas, fn($n) => $n['encontrada']));
        $total = count($this->nfesLancadas);

        Notification::make()
            ->title("{$encontradas}/{$total} NF-e encontradas no Bling")
            ->color($encontradas === $total ? 'success' : 'warning')
            ->send();

        $this->etapaAtual = 2;
    }

    /**
     * Etapa 2: Importar planilha do CommercePlus
     * Coluna A (0) = ID pedido CP | Coluna AJ (35) = Número NF-e
     */
    public function importarPlanilhaCp(): void
    {
        if (!$this->planilhaCp) {
            Notification::make()->title('Faça upload da planilha do CommercePlus')->warning()->send();
            return;
        }

        $path = $this->planilhaCp->getRealPath();
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        $header = $rows[0] ?? [];

        // Detectar coluna NF-e: procurar "nf" no cabeçalho ou fallback AJ(35)
        $colId = 0;
        $colNfe = 35;

        foreach ($header as $i => $colName) {
            $lower = strtolower(trim($colName ?? ''));
            if (str_contains($lower, 'nf') && !str_contains($lower, 'chave') && !str_contains($lower, 'xml') && !str_contains($lower, 'url') && !str_contains($lower, 'serie')) {
                $colNfe = $i;
                break;
            }
        }

        $this->pedidosCp = [];
        for ($i = 1; $i < count($rows); $i++) {
            $idPedido = trim((string) ($rows[$i][$colId] ?? ''));
            $nfeRaw = trim((string) ($rows[$i][$colNfe] ?? ''));

            if (empty($idPedido)) continue;

            $this->pedidosCp[] = [
                'id_pedido_cp' => $idPedido,
                'numero_nfe' => ltrim($nfeRaw, '0'),
                'serie_nfe' => '1',
                'chave_nfe' => '',
            ];
        }

        if (empty($this->pedidosCp)) {
            Notification::make()->title('Nenhum pedido encontrado na planilha')->warning()->send();
            return;
        }

        Notification::make()
            ->title(count($this->pedidosCp) . ' pedidos importados do CommercePlus')
            ->success()
            ->send();

        $this->vincularAutomaticamente();
        $this->etapaAtual = 3;
    }

    /**
     * Vinculação automática: para cada NF-e lançada, buscar o pedido CP que possui aquela NF-e
     */
    private function vincularAutomaticamente(): void
    {
        $this->vinculacoes = [];

        // Indexar pedidos CP por numero_nfe para busca rápida
        $cpPorNfe = [];
        foreach ($this->pedidosCp as $pedido) {
            $nfeNum = ltrim($pedido['numero_nfe'], '0');
            if (!empty($nfeNum)) {
                $cpPorNfe[$nfeNum] = $pedido;
            }
        }

        foreach ($this->nfesLancadas as $nfe) {
            $nfeNum = ltrim($nfe['numero'], '0');

            $vinc = [
                'numero_nfe' => $nfe['numero'],
                'chave_nfe' => $nfe['chave'],
                'serie_nfe' => $nfe['serie'],
                'transportadora' => $nfe['transportadora'],
                'codigo_rastreio' => '',
                'url_rastreio' => $this->gerarUrlRastreio($nfe['transportadora'], $nfe['numero']),
                'id_pedido_cp' => '',
                'vinculado' => false,
            ];

            // Vincular pela NF-e presente na planilha CP
            if (isset($cpPorNfe[$nfeNum])) {
                $vinc['id_pedido_cp'] = $cpPorNfe[$nfeNum]['id_pedido_cp'];
                if (empty($vinc['chave_nfe']) && !empty($cpPorNfe[$nfeNum]['chave_nfe'])) {
                    $vinc['chave_nfe'] = $cpPorNfe[$nfeNum]['chave_nfe'];
                }
                if (empty($vinc['serie_nfe']) && !empty($cpPorNfe[$nfeNum]['serie_nfe'])) {
                    $vinc['serie_nfe'] = $cpPorNfe[$nfeNum]['serie_nfe'];
                }
                $vinc['vinculado'] = true;
            }

            $this->vinculacoes[] = $vinc;
        }
    }

    /**
     * Gera URL de rastreio buscando no cadastro de transportadoras
     */
    private function gerarUrlRastreio(string $transportadora, string $nfeNumero): string
    {
        if (empty($transportadora)) return '';

        $transportadoraLower = strtolower($transportadora);

        // Buscar no cadastro: por nome ou aliases
        $transp = \App\Models\Transportadora::where('ativo', true)
            ->where('url_rastreio_template', '!=', '')
            ->whereNotNull('url_rastreio_template')
            ->get()
            ->first(function ($t) use ($transportadoraLower) {
                // Match por nome
                if (str_contains($transportadoraLower, strtolower($t->nome_transportadora))) return true;
                if (str_contains(strtolower($t->nome_transportadora), $transportadoraLower)) return true;
                // Match por aliases
                foreach ($t->aliases ?? [] as $alias) {
                    if (str_contains($transportadoraLower, strtolower($alias))) return true;
                    if (str_contains(strtolower($alias), $transportadoraLower)) return true;
                }
                return false;
            });

        if ($transp && $transp->url_rastreio_template) {
            return str_replace('{NFE}', $nfeNumero, $transp->url_rastreio_template);
        }

        // Fallback: mapeamento hardcoded SSW
        foreach (self::TRANSPORTADORAS_SSW as $nome => $cnpj) {
            if (str_contains($transportadoraLower, $nome)) {
                return "http://ssw.inf.br/cgi-local/tracking/{$cnpj}/{$nfeNumero}/1";
            }
        }

        return '';
    }

    /**
     * Etapa 3 → 4: Gerar planilha final
     */
    public function gerarPlanilha(): void
    {
        $this->planilhaFinal = [];

        foreach ($this->vinculacoes as $vinc) {
            $this->planilhaFinal[] = [
                'id_pedido_cp' => $vinc['id_pedido_cp'],
                'situacao' => 'em transporte',
                'codigo_rastreio' => $vinc['codigo_rastreio'],
                'url_rastreio' => $vinc['url_rastreio'] ?? '',
                'numero_nfe' => $vinc['numero_nfe'],
                'serie_nfe' => $vinc['serie_nfe'],
                'chave_nfe' => $vinc['chave_nfe'],
                'url_xml_nfe' => '',
            ];
        }

        $this->etapaAtual = 4;

        Notification::make()
            ->title('Planilha pronta para download com ' . count($this->planilhaFinal) . ' registros')
            ->success()
            ->send();
    }

    /**
     * Download da planilha .xls no modelo CommercePlus
     */
    public function downloadPlanilha()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Cabeçalhos conforme modelo CommercePlus
        $headers = ['A' => 'ID do pedido commerceplus', 'B' => 'situacao (preparando, em transporte, entregue)', 'C' => 'codigo rastreio', 'D' => 'url rastreio', 'E' => 'numero nfe', 'F' => 'serie nfe', 'G' => 'chave nfe', 'H' => 'url xml nfe'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '1', $header);
        }

        // Dados
        foreach ($this->planilhaFinal as $row => $data) {
            $r = $row + 2;
            $sheet->setCellValue("A{$r}", $data['id_pedido_cp']);
            $sheet->setCellValue("B{$r}", $data['situacao']);
            $sheet->setCellValue("C{$r}", $data['codigo_rastreio']);
            $sheet->setCellValue("D{$r}", $data['url_rastreio']);
            $sheet->setCellValue("E{$r}", $data['numero_nfe']);
            $sheet->setCellValue("F{$r}", $data['serie_nfe']);
            $sheet->setCellValue("G{$r}", $data['chave_nfe']);
            $sheet->setCellValue("H{$r}", $data['url_xml_nfe']);
        }

        $filename = 'commerceplus-importar-situacaopedidos-' . now()->format('Y-m-d_His') . '.xls';
        $path = storage_path('app/public/' . $filename);

        $writer = new Xls($spreadsheet);
        $writer->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function voltarEtapa(): void
    {
        if ($this->etapaAtual > 1) {
            $this->etapaAtual--;
        }
    }
}
