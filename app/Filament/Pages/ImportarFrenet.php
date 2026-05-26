<?php

namespace App\Filament\Pages;

use App\Models\FrenetFrete;
use App\Models\Venda;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportarFrenet extends Page
{
    protected static ?string $navigationIcon    = 'heroicon-o-truck';
    protected static ?string $navigationGroup   = 'Integrações';
    protected static ?string $navigationLabel   = 'Frenet - Fretes';
    protected static ?string $title             = 'Frenet — Fretes';
    protected static string  $view              = 'filament.pages.importar-frenet';

    public string  $filtro      = 'nao_utilizados';
    public string  $busca       = '';
    public string  $periodo     = '';
    public ?string $data_inicio = null;
    public ?string $data_fim    = null;

    // Modal
    public bool    $modalAberto    = false;
    public ?int    $modalFrenetId  = null;
    public ?array  $modalVendaDados = null;

    // ── Upload ──────────────────────────────────────────────────────────────

    public function importarCsv(string $conteudo): void
    {
        $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo));
        if (empty($linhas)) {
            Notification::make()->title('Arquivo vazio.')->danger()->send();
            return;
        }

        // Detectar separador (tab ou ponto-e-vírgula)
        $header = $linhas[0];
        $sep = str_contains($header, "\t") ? "\t" : ';';

        $novos = 0;
        $duplicados = 0;

        foreach (array_slice($linhas, 1) as $linha) {
            $linha = trim($linha);
            if ($linha === '') continue;

            $cols = str_getcsv($linha, $sep);
            // Colunas: ID | Data | Etiqueta | Destinatario | Cidade/UF | Modalidade | Preco | Status
            if (count($cols) < 7) continue;

            $frenetId    = trim($cols[0]);
            $dataStr     = trim($cols[1]);
            $etiqueta    = trim($cols[2]);
            $destinatario = trim($cols[3]);
            $cidadeUf    = trim($cols[4]);
            $modalidade  = trim($cols[5]);
            $precoRaw    = trim($cols[6]);
            $status      = trim($cols[7] ?? '');

            if (empty($frenetId)) continue;

            if (FrenetFrete::where('frenet_id', $frenetId)->exists()) {
                $duplicados++;
                continue;
            }

            // Converter "R$ 24,06" → 24.06
            $valor = (float) str_replace(['.', ','], ['', '.'], preg_replace('/[^0-9,.]/', '', $precoRaw));

            // Converter data "22/05/2026" → "2026-05-22"
            $data = null;
            if ($dataStr) {
                try {
                    $data = \Carbon\Carbon::createFromFormat('d/m/Y', $dataStr)->toDateString();
                } catch (\Exception) {}
            }

            FrenetFrete::create([
                'frenet_id'    => $frenetId,
                'data_envio'   => $data,
                'etiqueta'     => $etiqueta,
                'destinatario' => $destinatario,
                'cidade_uf'    => $cidadeUf,
                'modalidade'   => $modalidade,
                'valor_frete'  => $valor,
                'status'       => $status,
            ]);

            $novos++;
        }

        if ($novos > 0) {
            Notification::make()->title("{$novos} frete(s) importado(s). {$duplicados} duplicado(s) ignorado(s).")->success()->send();
        } else {
            Notification::make()->title("Nenhum frete novo. {$duplicados} duplicado(s).")->warning()->send();
        }
    }

    // ── Vinculação automática ────────────────────────────────────────────────

    public function vincularAuto(int $frenetId): void
    {
        $frete = FrenetFrete::find($frenetId);
        if (!$frete) return;

        $venda = Venda::where('cliente_nome', 'like', '%' . $frete->destinatario . '%')
            ->where('frete_pago', false)
            ->orderBy('data_venda', 'desc')
            ->first();

        if (!$venda) {
            Notification::make()
                ->title("Nenhuma venda pendente encontrada para '{$frete->destinatario}'")
                ->warning()->send();
            return;
        }

        $this->modalFrenetId  = $frete->id;
        $this->modalVendaDados = $this->montarDadosModal($frete, $venda);
        $this->modalAberto    = true;
    }

    public function buscarPedidoParaVincular(int $frenetId, string $numeroPedido): void
    {
        $frete = FrenetFrete::find($frenetId);
        if (!$frete) return;

        $venda = Venda::where('numero_pedido_canal', $numeroPedido)->first();

        if (!$venda) {
            $blingId = \App\Models\PedidoBlingStaging::where('numero_pedido', $numeroPedido)->value('bling_id');
            if ($blingId) {
                $venda = Venda::where('bling_id', $blingId)->first();
            }
        }

        if (!$venda) {
            Notification::make()->title("Pedido '{$numeroPedido}' não encontrado.")->danger()->send();
            return;
        }

        $this->modalFrenetId  = $frete->id;
        $this->modalVendaDados = $this->montarDadosModal($frete, $venda);
        $this->modalAberto    = true;
    }

    private function montarDadosModal(FrenetFrete $frete, Venda $venda): array
    {
        return [
            'id_venda'            => $venda->id_venda,
            'numero_pedido_canal' => $venda->numero_pedido_canal,
            'cliente_nome'        => $venda->cliente_nome,
            'nota_fiscal'         => $venda->numero_nota_fiscal ?: 'N/A',
            'canal'               => $venda->canal_nome,
            'valor_total'         => number_format((float) $venda->valor_total_venda, 2, ',', '.'),
            'data_venda'          => $venda->data_venda?->format('d/m/Y'),
            'frete_destinatario'  => $frete->destinatario,
            'frete_valor'         => number_format((float) $frete->valor_frete, 2, ',', '.'),
            'frete_modalidade'    => $frete->modalidade,
        ];
    }

    public function confirmarVinculacao(): void
    {
        if (!$this->modalFrenetId || !$this->modalVendaDados) return;

        $frete = FrenetFrete::find($this->modalFrenetId);
        $venda = Venda::find($this->modalVendaDados['id_venda']);

        if (!$frete || !$venda) {
            $this->fecharModal();
            return;
        }

        $venda->update([
            'valor_frete_transportadora' => $frete->valor_frete,
            'frete_pago'                 => true,
            'transportadora_manual'      => $frete->modalidade,
        ]);

        $frete->update([
            'utilizado' => true,
            'venda_id'  => $venda->id_venda,
        ]);

        \App\Services\VendaRecalculoService::recalcularMargens($venda);

        Notification::make()
            ->title("Frete Frenet vinculado à venda #{$venda->numero_pedido_canal} — R$ " . number_format((float) $frete->valor_frete, 2, ',', '.'))
            ->success()->send();

        $this->fecharModal();
    }

    public function fecharModal(): void
    {
        $this->modalAberto    = false;
        $this->modalFrenetId  = null;
        $this->modalVendaDados = null;
    }

    // ── Dados ────────────────────────────────────────────────────────────────

    public function getFretesProperty()
    {
        return $this->buildQuery()->limit(300)->get();
    }

    public function getTotaisProperty(): array
    {
        return [
            'total'              => FrenetFrete::count(),
            'utilizados'         => FrenetFrete::where('utilizado', true)->count(),
            'nao_utilizados'     => FrenetFrete::where('utilizado', false)->count(),
            'valor_nao_utilizado'=> FrenetFrete::where('utilizado', false)->sum('valor_frete'),
        ];
    }

    private function buildQuery()
    {
        $query = FrenetFrete::orderBy('data_envio', 'desc')->orderBy('id', 'desc');

        if ($this->filtro === 'nao_utilizados') {
            $query->where('utilizado', false);
        } elseif ($this->filtro === 'utilizados') {
            $query->where('utilizado', true);
        }

        if ($this->busca) {
            $b = $this->busca;
            $query->where(fn ($q) => $q
                ->where('destinatario', 'like', "%{$b}%")
                ->orWhere('frenet_id', 'like', "%{$b}%")
                ->orWhere('cidade_uf', 'like', "%{$b}%")
                ->orWhere('modalidade', 'like', "%{$b}%")
            );
        }

        if ($this->periodo) {
            $datas = match ($this->periodo) {
                'hoje'        => [today()->toDateString(), today()->toDateString()],
                'esta_semana' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
                'este_mes'    => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
                'mes_passado' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
                'customizado' => [$this->data_inicio, $this->data_fim],
                default       => null,
            };
            if ($datas) {
                if ($datas[0]) $query->where('data_envio', '>=', $datas[0]);
                if ($datas[1]) $query->where('data_envio', '<=', $datas[1]);
            }
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
