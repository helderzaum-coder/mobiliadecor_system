<?php

namespace App\Filament\Pages;

use App\Models\Venda;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

class DashboardVendas extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Dashboard de Vendas';
    protected static ?string $slug = '/';
    protected static string $view = 'filament.pages.dashboard-vendas';
    protected static ?int $navigationSort = -2;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected $queryString = [
        'periodo'      => ['except' => 'este_mes', 'as' => 'periodo'],
        'mes_selecionado' => ['except' => '', 'as' => 'mes'],
        'canal'        => ['except' => '', 'as' => 'canal'],
        'conta'        => ['except' => '', 'as' => 'conta'],
        'status_filtro' => ['except' => '', 'as' => 'status'],
        'busca_pedido' => ['except' => '', 'as' => 'pedido'],
        'data_inicio'  => ['except' => '', 'as' => 'de'],
        'data_fim'     => ['except' => '', 'as' => 'ate'],
        'pagina'       => ['except' => 1, 'as' => 'pag'],
    ];

    public ?string $periodo = 'este_mes';
    public ?string $mes_selecionado = null;
    public ?string $canal = null;
    public ?string $conta = null;
    public ?string $status_filtro = null;
    public ?string $busca_pedido = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public int $pagina = 1;
    public int $porPagina = 20;

    public function mount(): void
    {
        if (!auth()->user()?->hasRole('admin')) {
            $this->redirect('/faq');
            return;
        }

        if (empty($this->mes_selecionado)) {
            $this->mes_selecionado = now()->format('Y-m');
        }
    }

    // ---- Ações ----

    public function buscarNfe(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::buscarNfe($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function buscarNfePorNumero(int $vendaId, string $numero): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::buscarNfePorNumero($venda, $numero);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function buscarCte(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::buscarCte($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function aplicarPlanilhaML(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::aplicarPlanilhaML($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function aplicarPlanilhaShopee(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::aplicarPlanilhaShopee($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function aplicarPlanilhaMM(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::aplicarPlanilhaMM($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function aplicarPlanilhaWC(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $result = \App\Services\VendaRecalculoService::aplicarPlanilhaWC($venda);
        \Filament\Notifications\Notification::make()->title($result['msg'])
            ->{$result['success'] ? 'success' : 'warning'}()->send();
    }

    public function recalcular(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        \App\Services\VendaRecalculoService::recalcularMargens($venda);
        \Filament\Notifications\Notification::make()->title('Margens recalculadas.')->success()->send();
    }

    public function marcarFreteEnvias(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $venda->update([
            'valor_frete_cliente' => 0,
            'valor_frete_transportadora' => 0,
            'frete_pago' => true,
        ]);
        \App\Services\VendaRecalculoService::recalcularMargens($venda);
        \Filament\Notifications\Notification::make()->title('Frete zerado (Envias). Margens recalculadas.')->success()->send();
    }

    public function lancarFreteManual(int $vendaId, string $valor, string $transportadora): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $valorFrete = (float) str_replace(',', '.', $valor);
        if ($valorFrete <= 0) {
            \Filament\Notifications\Notification::make()->title('Valor inválido.')->warning()->send();
            return;
        }
        $venda->update([
            'valor_frete_transportadora' => round($valorFrete, 2),
            'frete_pago' => true,
            'transportadora_manual' => trim($transportadora) ?: null,
        ]);
        \App\Services\VendaRecalculoService::recalcularMargens($venda);
        \Filament\Notifications\Notification::make()
            ->title('Frete manual lançado: R$ ' . number_format($valorFrete, 2, ',', '.') . ($transportadora ? " ({$transportadora})" : ''))
            ->success()->send();
    }

    public function buscarCustos(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;

        $staging = \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
        if (!$staging) {
            \Filament\Notifications\Notification::make()->title('Staging não encontrado.')->warning()->send();
            return;
        }

        $atualizados = \App\Services\Bling\BlingImportService::buscarCustosProdutos($staging);

        if ($atualizados > 0) {
            // Recalcular custo total na venda
            $custoProdutos = 0;
            foreach ($staging->fresh()->itens ?? [] as $item) {
                $custoProdutos += ((float) ($item['custo'] ?? 0)) * ((int) ($item['quantidade'] ?? 1));
            }
            $venda->update(['custo_produtos' => round($custoProdutos, 2)]);
            \App\Services\VendaRecalculoService::recalcularMargens($venda);
            \Filament\Notifications\Notification::make()->title("{$atualizados} custo(s) atualizado(s). Margens recalculadas.")->success()->send();
        } else {
            \Filament\Notifications\Notification::make()->title('Nenhum custo encontrado no Bling.')->warning()->send();
        }
    }

    public function marcarAguardandoEnvio(int $vendaId, string $dataPrevista): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $venda->update(['data_prevista_envio' => $dataPrevista]);
        // Gerar conta a receber se tiver custo
        \App\Services\ContaReceberService::gerarSeCompleta($venda->fresh());
        \Filament\Notifications\Notification::make()->title('Pedido marcado como aguardando envio até ' . \Carbon\Carbon::parse($dataPrevista)->format('d/m/Y'))->success()->send();
    }

    public function removerAguardandoEnvio(int $vendaId): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;
        $venda->update(['data_prevista_envio' => null]);
        \Filament\Notifications\Notification::make()->title('Data prevista removida.')->success()->send();
    }

    public function cancelarComEstorno(int $vendaId, string $data = ''): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;

        $canal = $venda->canal?->nome_canal ?? 'Marketplace';
        $isMagalu = str_contains(strtolower($canal), 'magalu');
        $afiliado = (float) ($venda->comissao_afiliado ?? 0);
        $repasse = $isMagalu
            ? (float) $venda->valor_total_venda - (float) $venda->comissao - $afiliado
            : (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao - $afiliado;

        // Gerar conta a receber se não existe (forçar mesmo sem NF-e)
        $contaReceber = \App\Models\ContaReceber::where('id_venda', $venda->id_venda)->first();

        // Usar valor da conta a receber existente (valor real recebido)
        if ($contaReceber) {
            $repasse = (float) $contaReceber->valor_parcela;
        }
        if (!$contaReceber) {
            $contaReceber = \App\Models\ContaReceber::create([
                'id_venda' => $venda->id_venda,
                'valor_parcela' => round(abs($repasse), 2),
                'data_vencimento' => $venda->data_venda,
                'status' => 'pendente',
                'numero_parcela' => 1,
                'total_parcelas' => 1,
                'forma_pagamento' => $canal,
                'observacoes' => "Antecipação #{$venda->numero_pedido_canal} (cancelado com estorno)",
                'lancamento_manual' => false,
                'estorno_pendente' => true,
            ]);
        } else {
            $contaReceber->update(['estorno_pendente' => true]);
        }

        // Criar conta a pagar com o valor do estorno
        \App\Models\ContaPagar::create([
            'valor_parcela' => round(abs($repasse), 2),
            'data_vencimento' => $data ? $data : now()->toDateString(),
            'status' => 'pendente',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'forma_pagamento' => 'Estorno',
            'observacoes' => "Estorno {$canal} - Pedido #{$venda->numero_pedido_canal}. Valor antecipado ser\u00e1 estornado.",
            'lancamento_manual' => true,
        ]);

        // Marcar pedido no staging como cancelado
        \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->update(['status' => 'cancelado']);

        // Marcar venda como cancelada
        $venda->update(['cancelada' => true]);

        \Filament\Notifications\Notification::make()
            ->title("Pedido cancelado. Estorno de R$ " . number_format(abs($repasse), 2, ',', '.') . " registrado.")
            ->success()->send();
    }

    public function registrarReembolso(int $vendaId, string $data = ''): void
    {
        $venda = Venda::find($vendaId);
        if (!$venda) return;

        $canal = $venda->canal?->nome_canal ?? 'Marketplace';
        $isMagalu = str_contains(strtolower($canal), 'magalu');
        $afiliado = (float) ($venda->comissao_afiliado ?? 0);
        $repasse = $isMagalu
            ? (float) $venda->valor_total_venda - (float) $venda->comissao - $afiliado
            : (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao - $afiliado;

        $contaReceber = \App\Models\ContaReceber::where('id_venda', $venda->id_venda)->first();

        // Usar valor da conta a receber existente (valor real recebido)
        if ($contaReceber) {
            $repasse = (float) $contaReceber->valor_parcela;
            $contaReceber->update(['estorno_pendente' => true]);
        }

        \App\Models\ContaPagar::create([
            'valor_parcela' => round(abs($repasse), 2),
            'data_vencimento' => $data ? $data : now()->toDateString(),
            'status' => 'pendente',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'forma_pagamento' => 'Reembolso',
            'observacoes' => "Reembolso {$canal} - Pedido #{$venda->numero_pedido_canal}. Valor debitado pelo marketplace.",
            'lancamento_manual' => true,
        ]);

        // Marcar venda como cancelada
        $venda->update(['cancelada' => true]);

        \Filament\Notifications\Notification::make()
            ->title("Reembolso de R$ " . number_format(abs($repasse), 2, ',', '.') . " registrado.")
            ->success()->send();
    }

    public function buscarNfeLote(): void
    {
        $ids = $this->buildQuery()
            ->where(fn ($q) => $q->whereNull('nfe_chave_acesso')->orWhere('nfe_chave_acesso', ''))
            ->pluck('id_venda')->toArray();

        if (empty($ids)) {
            \Filament\Notifications\Notification::make()->title('Nenhuma venda sem NF-e no período.')->warning()->send();
            return;
        }

        \App\Jobs\BuscarDadosVendaLoteJob::dispatch('nfe', $ids, auth()->id());
        \Filament\Notifications\Notification::make()->title(count($ids) . ' venda(s) enviadas para busca de NF-e.')->info()->send();
    }

    public function buscarCteLote(): void
    {
        $ids = $this->buildQuery()
            ->where('frete_pago', false)
            ->where(fn ($q) => $q->whereNotNull('nfe_chave_acesso')->where('nfe_chave_acesso', '!=', ''))
            ->where(fn ($q) => $q->whereNull('ml_tipo_frete')->orWhere('ml_tipo_frete', 'ME1'))
            ->pluck('id_venda')->toArray();

        if (empty($ids)) {
            \Filament\Notifications\Notification::make()->title('Nenhuma venda pendente de CT-e no período.')->warning()->send();
            return;
        }

        \App\Jobs\BuscarDadosVendaLoteJob::dispatch('cte', $ids, auth()->id());
        \Filament\Notifications\Notification::make()->title(count($ids) . ' venda(s) enviadas para busca de CT-e.')->info()->send();
    }

    public function buscarCustosLote(): void
    {
        $ids = $this->buildQuery()
            ->where(fn ($q) => $q->where('custo_produtos', '<=', 0)->orWhereNull('custo_produtos'))
            ->pluck('id_venda')->toArray();

        if (empty($ids)) {
            \Filament\Notifications\Notification::make()->title('Nenhuma venda sem custo no período.')->warning()->send();
            return;
        }

        \App\Jobs\BuscarDadosVendaLoteJob::dispatch('custos', $ids, auth()->id());
        \Filament\Notifications\Notification::make()->title(count($ids) . ' venda(s) enviadas para busca de custos.')->info()->send();
    }

    public function recalcularImpostosLote(): void
    {
        $vendas = $this->buildQuery()
            ->where(fn ($q) => $q->whereNotNull('nfe_chave_acesso')->where('nfe_chave_acesso', '!=', ''))
            ->where(fn ($q) => $q->where('valor_imposto', '<=', 0)->orWhereNull('valor_imposto'))
            ->get();

        if ($vendas->isEmpty()) {
            \Filament\Notifications\Notification::make()->title('Nenhuma venda com NF-e e imposto zerado no período.')->warning()->send();
            return;
        }

        $count = 0;
        foreach ($vendas as $venda) {
            $staging = \App\Models\PedidoBlingStaging::where('bling_id', $venda->bling_id)->first();
            if (!$staging) continue;

            $percentual = \App\Services\Bling\BlingImportService::buscarPercentualImpostoPublic($staging);
            if ($percentual <= 0) continue;

            $base = (float) $venda->nfe_valor ?: (float) $venda->valor_total_venda;
            $valorImposto = round($base * ($percentual / 100), 2);

            $venda->update([
                'base_imposto' => $base,
                'percentual_imposto' => $percentual,
                'valor_imposto' => $valorImposto,
            ]);

            \App\Services\VendaRecalculoService::recalcularMargens($venda);
            $count++;
        }

        \Filament\Notifications\Notification::make()->title("{$count} venda(s) com imposto recalculado.")->success()->send();
    }

    public function aplicarPlanilhaShopeeLote(): void
    {
        $ids = $this->buildQuery()
            ->where('planilha_processada', false)
            ->where('canal_nome', 'like', '%shopee%')
            ->pluck('id_venda')->toArray();

        if (empty($ids)) {
            \Filament\Notifications\Notification::make()->title('Nenhuma venda Shopee sem planilha no período.')->warning()->send();
            return;
        }

        \App\Jobs\BuscarDadosVendaLoteJob::dispatch('shopee', $ids, auth()->id());
        \Filament\Notifications\Notification::make()->title(count($ids) . ' venda(s) Shopee enviadas para aplicar planilha.')->info()->send();
    }

    public function exportarPlanilha(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $vendas = $this->buildQuery()->get();

        // Carregar CTes vinculados por nfe_chave_acesso
        $chaves = $vendas->pluck('nfe_chave_acesso')->filter()->unique()->toArray();
        $ctesPorChave = \App\Models\Cte::whereIn('chave_nfe', $chaves)
            ->orderBy('id')
            ->get()
            ->groupBy('chave_nfe');

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pedidos_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        return response()->streamDownload(function () use ($vendas, $ctesPorChave) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para Excel
            fputs($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Pedido', 'Data', 'Canal', 'Conta', 'Cliente',
                'Total Pedido', 'Subtotal Produtos', 'Custo Produtos',
                'Comissão', 'Imposto', 'Frete Cobrado', 'Frete Pago',
                'Lucro Final', 'Margem %',
                // Status de completude
                'NF-e Número', 'NF-e Chave', 'NF-e Valor',
                'Frete Lançado', 'CTe Número', 'CTe Transportadora', 'CTe Valor', 'Transportadora Manual',
                'Planilha Canal', 'Planilha Afiliado (Shopee)',
                'Custo Lançado',
                'Completo',
                'Cancelado',
            ], ';');

            foreach ($vendas as $v) {
                $canal = $v->canal?->nome_canal ?? ($v->canal_nome ?? '-');
                $isShopee = str_contains(strtolower($canal), 'shopee');
                $isML = str_contains(strtolower($canal), 'mercado');
                $isMagalu = str_contains(strtolower($canal), 'magalu');
                $isWC = str_contains(strtolower($canal), 'webcontinental');
                $isMM = str_contains(strtolower($canal), 'madeira');
                $precisaPlanilha = $isShopee || $isML || $isMagalu || $isWC || $isMM;
                $precisaAfiliado = $isShopee;

                $temNfe = !empty($v->nfe_chave_acesso);
                $fretePago = (bool) $v->frete_pago;
                $isMlMe2Full = in_array($v->ml_tipo_frete, ['ME2', 'FULL']);
                $freteCliente = (float) $v->valor_frete_cliente;
                $custoFrete = (float) $v->valor_frete_transportadora;
                $freteOk = $fretePago || $isMlMe2Full || ($freteCliente == 0 && $custoFrete == 0);
                $planilhaOk = (bool) $v->planilha_processada;
                $afiliadoOk = (bool) $v->planilha_afiliado_processada;
                $custoOk = (float) $v->custo_produtos > 0;
                $completo = $temNfe && $freteOk && (!$precisaPlanilha || $planilhaOk) && (!$precisaAfiliado || $afiliadoOk);

                // CTe vinculado
                $ctes = $temNfe ? ($ctesPorChave[$v->nfe_chave_acesso] ?? collect()) : collect();
                $cte = $ctes->first();

                fputcsv($out, [
                    $v->numero_pedido_canal,
                    $v->data_venda?->format('d/m/Y'),
                    $canal,
                    $v->bling_account === 'primary' ? 'Mobilia Decor' : 'HES Móveis',
                    $v->cliente_nome,
                    number_format((float) $v->valor_total_venda, 2, ',', '.'),
                    number_format((float) $v->total_produtos, 2, ',', '.'),
                    number_format((float) $v->custo_produtos, 2, ',', '.'),
                    number_format((float) $v->comissao, 2, ',', '.'),
                    number_format((float) $v->valor_imposto, 2, ',', '.'),
                    number_format($freteCliente, 2, ',', '.'),
                    number_format($custoFrete, 2, ',', '.'),
                    number_format((float) $v->margem_venda_total, 2, ',', '.'),
                    number_format((float) $v->margem_contribuicao, 2, ',', '.') . '%',
                    // NF-e
                    $v->numero_nota_fiscal ?? '',
                    $v->nfe_chave_acesso ?? '',
                    $v->nfe_valor ? number_format((float) $v->nfe_valor, 2, ',', '.') : '',
                    // Frete
                    $freteOk ? 'Sim' : 'Não',
                    $cte?->numero_cte ?? ($v->transportadora_manual ? '' : ''),
                    $cte?->transportadora ?? '',
                    $cte ? number_format((float) $cte->valor_frete, 2, ',', '.') : '',
                    $v->transportadora_manual ?? '',
                    // Planilhas
                    $precisaPlanilha ? ($planilhaOk ? 'Sim' : 'Não') : 'N/A',
                    $precisaAfiliado ? ($afiliadoOk ? 'Sim' : 'Não') : 'N/A',
                    // Custo
                    $custoOk ? 'Sim' : 'Não',
                    // Completo
                    $completo ? 'Sim' : 'Não',
                    (bool) $v->cancelada ? 'Sim' : 'Não',
                ], ';');
            }

            fclose($out);
        }, 'pedidos.csv', $headers);
    }

    public function paginaAnterior(): void
    {
        if ($this->pagina > 1) $this->pagina--;
    }

    public function proximaPagina(): void
    {
        $this->pagina++;
    }

    public function updatedPeriodo(): void { $this->pagina = 1; }
    public function updatedMesSelecionado(): void { $this->pagina = 1; }
    public function updatedCanal(): void { $this->pagina = 1; }
    public function updatedConta(): void { $this->pagina = 1; }
    public function updatedStatusFiltro(): void { $this->pagina = 1; }
    public function updatedBuscaPedido(): void { $this->pagina = 1; }
    public function updatedDataInicio(): void { $this->pagina = 1; }
    public function updatedDataFim(): void { $this->pagina = 1; }

    // ---- Form ----

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(5)->schema([
                Forms\Components\TextInput::make('busca_pedido')
                    ->label('Pedido')
                    ->placeholder('Nº pedido...')
                    ->reactive()
                    ->debounce(400),
                Forms\Components\Select::make('periodo')
                    ->label('Período')
                    ->options([
                        'hoje' => 'Hoje',
                        'esta_semana' => 'Esta semana',
                        'este_mes' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'selecionar_mes' => 'Selecionar mês',
                        'customizado' => 'Período customizado',
                    ])
                    ->reactive(),
                Forms\Components\Select::make('mes_selecionado')
                    ->label('Mês')
                    ->options(function () {
                        $options = [];
                        for ($i = 0; $i < 12; $i++) {
                            $d = now()->subMonths($i)->startOfMonth();
                            $options[$d->format('Y-m')] = ucfirst($d->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
                        }
                        return $options;
                    })
                    ->visible(fn ($get) => $get('periodo') === 'selecionar_mes')
                    ->reactive(),
                Forms\Components\DatePicker::make('data_inicio')
                    ->label('De')
                    ->displayFormat('d/m/Y')
                    ->visible(fn ($get) => $get('periodo') === 'customizado')
                    ->reactive(),
                Forms\Components\DatePicker::make('data_fim')
                    ->label('Até')
                    ->displayFormat('d/m/Y')
                    ->visible(fn ($get) => $get('periodo') === 'customizado')
                    ->reactive(),
                Forms\Components\Select::make('canal')
                    ->label('Canal')
                    ->options(fn () => \App\Models\CanalVenda::orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray())
                    ->placeholder('Todos')
                    ->reactive(),
                Forms\Components\Select::make('conta')
                    ->label('Conta')
                    ->options(['primary' => 'Mobilia Decor', 'secondary' => 'HES Móveis'])
                    ->placeholder('Todas')
                    ->reactive(),
                Forms\Components\Select::make('status_filtro')
                    ->label('Status')
                    ->options([
                        'falta_nfe' => '⚠ Falta NF-e',
                        'falta_frete' => '🚚 Falta Frete',
                        'falta_planilha' => '📊 Falta Planilha',
                        'falta_afiliado' => '👥 Falta Afiliado (Shopee)',
                        'sem_custo' => '💰 Sem Custo Produto',
                        'aguardando_envio' => '📦 Aguardando Envio',
                        'me2_full' => '📦 ME2/FULL',
                        'shopee_xpress' => '🚚 Shopee Xpress',
                        'envias' => '🚚 Envias',
                        'madeira_envios' => '🚚 Madeira Envios',
                        'incompleto' => '❌ Incompleto',
                        'completo' => '✅ Completo',
                        'recebido' => '💰 Recebido',
                        'nao_recebido' => '⏳ Não Recebido',
                        'cancelados' => '🚫 Cancelados/Estornos',
                    ])
                    ->placeholder('Todos')
                    ->reactive(),
            ]),
        ]);
    }

    // ---- Dados ----

    private function buildQuery()
    {
        $query = Venda::with('canal')->orderBy('data_venda', 'desc');

        // Excluir canceladas por padrão, exceto quando filtro é 'cancelados'
        if ($this->status_filtro !== 'cancelados') {
            $query->where(fn ($q) => $q->where('cancelada', false)->orWhereNull('cancelada'));
        }

        // Se tem busca por pedido, ignorar filtro de período
        if (empty($this->busca_pedido)) {
            $query = match ($this->periodo) {
                'hoje' => $query->whereDate('data_venda', today()),
                'esta_semana' => $query->whereBetween('data_venda', [now()->startOfWeek(), now()->endOfWeek()]),
                'este_mes' => $query->whereBetween('data_venda', [now()->startOfMonth(), now()->endOfMonth()]),
                'mes_passado' => $query->whereBetween('data_venda', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
                'selecionar_mes' => $this->mes_selecionado
                    ? $query->whereBetween('data_venda', [
                        now()->createFromFormat('Y-m', $this->mes_selecionado)->startOfMonth(),
                        now()->createFromFormat('Y-m', $this->mes_selecionado)->endOfMonth(),
                    ])
                    : $query,
                'customizado' => $query
                    ->when($this->data_inicio, fn ($q) => $q->whereDate('data_venda', '>=', $this->data_inicio))
                    ->when($this->data_fim, fn ($q) => $q->whereDate('data_venda', '<=', $this->data_fim)),
                default => $query,
            };
        }

        if ($this->busca_pedido) {
            $query->where('numero_pedido_canal', 'like', '%' . $this->busca_pedido . '%');
        }
        if ($this->canal) {
            $query->whereHas('canal', fn ($q) => $q->where('nome_canal', $this->canal));
        }
        if ($this->conta) {
            $query->where('bling_account', $this->conta);
        }

        // Filtro de status
        if ($this->status_filtro === 'falta_nfe') {
            $query->where(fn ($q) => $q->whereNull('nfe_chave_acesso')->orWhere('nfe_chave_acesso', ''));
        } elseif ($this->status_filtro === 'falta_frete') {
            // Falta frete: frete_pago=false, EXCETO ML ME2/FULL e Shopee Xpress (frete=0)
            $query->where('frete_pago', false)
                ->where(fn ($q) => $q
                    ->whereNull('ml_tipo_frete')
                    ->orWhere('ml_tipo_frete', 'ME1')
                )
                ->where('valor_frete_cliente', '>', 0);
        } elseif ($this->status_filtro === 'falta_planilha') {
            $query->where('planilha_processada', false)
                ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%')->orWhere('nome_canal', 'like', '%ercado%')->orWhere('nome_canal', 'like', '%agalu%')->orWhere('nome_canal', 'like', '%ebcontinental%')->orWhere('nome_canal', 'like', '%adeira%'))
                ->where(fn ($q) => $q->whereNull('ml_sale_fee')->orWhere('ml_sale_fee', '<=', 0));
        } elseif ($this->status_filtro === 'falta_afiliado') {
            $query->where('planilha_afiliado_processada', false)
                ->whereHas('canal', fn ($q) => $q->where('nome_canal', 'like', '%hopee%'));
        } elseif ($this->status_filtro === 'sem_custo') {
            $query->where(fn ($q) => $q->where('custo_produtos', '<=', 0)->orWhereNull('custo_produtos'));
        } elseif ($this->status_filtro === 'aguardando_envio') {
            $query->whereNotNull('data_prevista_envio');
        } elseif ($this->status_filtro === 'me2_full') {
            $query->whereIn('ml_tipo_frete', ['ME2', 'FULL']);
        } elseif ($this->status_filtro === 'shopee_xpress') {
            $query->where('canal_nome', 'like', '%shopee%')
                ->where('valor_frete_cliente', 0)
                ->where('valor_frete_transportadora', 0);
        } elseif ($this->status_filtro === 'envias') {
            $query->where(fn ($q) => $q->where('canal_nome', 'like', '%via%')->orWhere('canal_nome', 'like', '%cnova%'))
                ->where('valor_frete_cliente', 0)
                ->where('valor_frete_transportadora', 0);
        } elseif ($this->status_filtro === 'madeira_envios') {
            $query->where('canal_nome', 'like', '%adeira%')
                ->where('valor_frete_cliente', 0)
                ->where('valor_frete_transportadora', 0)
                ->where('frete_pago', true);
        } elseif ($this->status_filtro === 'incompleto') {
            // Incompleto: tudo que NÃO é completo
            $query->where(function ($q) {
                $q->where(fn ($q2) => $q2->whereNull('nfe_chave_acesso')->orWhere('nfe_chave_acesso', ''))
                  ->orWhere(function ($q2) {
                      $q2->where('frete_pago', false)
                         ->where(fn ($q3) => $q3->whereNull('ml_tipo_frete')->orWhereNotIn('ml_tipo_frete', ['ME2', 'FULL']))
                         ->where('valor_frete_cliente', '>', 0)
                         ->where('valor_frete_transportadora', '<=', 0);
                  })
                  ->orWhere(function ($q2) {
                      $q2->where('planilha_processada', false)
                         ->where(fn ($q3) => $q3->whereNull('ml_sale_fee')->orWhere('ml_sale_fee', '<=', 0))
                         ->whereHas('canal', fn ($q3) => $q3->where('nome_canal', 'like', '%hopee%')->orWhere('nome_canal', 'like', '%ercado%')->orWhere('nome_canal', 'like', '%agalu%')->orWhere('nome_canal', 'like', '%ebcontinental%')->orWhere('nome_canal', 'like', '%adeira%'));
                  });
            });
        } elseif ($this->status_filtro === 'completo') {
            // Completo: (tem NF-e + frete OK + planilha OK) OU (tem data_prevista_envio + custo > 0)
            $query->where(function ($q) {
                // Completo normal
                $q->where(function ($q2) {
                    $q2->where(fn ($q3) => $q3->whereNotNull('nfe_chave_acesso')->where('nfe_chave_acesso', '!=', ''))
                        ->where(fn ($q3) => $q3
                            ->where('frete_pago', true)
                            ->orWhereIn('ml_tipo_frete', ['ME2', 'FULL'])
                        )
                        ->where(fn ($q3) => $q3
                            ->where('planilha_processada', true)
                            ->orWhereDoesntHave('canal', fn ($q4) => $q4->where('nome_canal', 'like', '%hopee%')->orWhere('nome_canal', 'like', '%ercado%')->orWhere('nome_canal', 'like', '%agalu%')->orWhere('nome_canal', 'like', '%ebcontinental%')->orWhere('nome_canal', 'like', '%adeira%'))
                        )
                        ->where(fn ($q3) => $q3
                            ->where('planilha_afiliado_processada', true)
                            ->orWhereDoesntHave('canal', fn ($q4) => $q4->where('nome_canal', 'like', '%hopee%'))
                        );
                })
                // OU aguardando envio com custo
                ->orWhere(function ($q2) {
                    $q2->whereNotNull('data_prevista_envio')
                        ->where('custo_produtos', '>', 0);
                })
                // OU Shopee Xpress (frete = 0, sem custo frete)
                ->orWhere(function ($q2) {
                    $q2->where('valor_frete_cliente', 0)
                        ->where('valor_frete_transportadora', 0)
                        ->where(fn ($q3) => $q3->whereNotNull('nfe_chave_acesso')->where('nfe_chave_acesso', '!=', ''))
                        ->where('planilha_processada', true);
                });
            });
        } elseif ($this->status_filtro === 'cancelados') {
            $query->where('cancelada', true);
        } elseif ($this->status_filtro === 'recebido') {
            $query->where('repasse_recebido', true);
        } elseif ($this->status_filtro === 'nao_recebido') {
            $query->where(fn ($q) => $q->where('repasse_recebido', false)->orWhereNull('repasse_recebido'));
        }

        return $query;
    }

    public function getVendasProperty(): \Illuminate\Support\Collection
    {
        $vendas = $this->buildQuery()
            ->skip(($this->pagina - 1) * $this->porPagina)
            ->take($this->porPagina)
            ->get();

        // Carregar itens e documento do staging
        $blingIds = $vendas->pluck('bling_id')->filter()->toArray();
        if (!empty($blingIds)) {
            $stagings = \App\Models\PedidoBlingStaging::whereIn('bling_id', $blingIds)
                ->select('bling_id', 'itens', 'cliente_documento')
                ->get()
                ->keyBy('bling_id');

            foreach ($vendas as $venda) {
                $staging = $stagings[$venda->bling_id] ?? null;
                $venda->staging_itens = $staging?->itens ?? [];
                if (empty($venda->cliente_documento) && !empty($staging?->cliente_documento)) {
                    $venda->cliente_documento = $staging->cliente_documento;
                }
            }
        }

        return $vendas;
    }

    public function getTotaisProperty(): array
    {
        $query = $this->buildQuery();
        $total = (clone $query)->sum('valor_total_venda');
        $lucro = (clone $query)->sum('margem_venda_total');
        $count = (clone $query)->count();
        $comLucro = (clone $query)->where('margem_venda_total', '>=', 0)->count();
        $comPrejuizo = $count - $comLucro;

        return [
            'qtd' => $count,
            'total' => $total,
            'lucro' => $lucro,
            'margem' => $total > 0 ? round(($lucro / $total) * 100, 1) : 0,
            'com_lucro' => $comLucro,
            'com_prejuizo' => $comPrejuizo,
        ];
    }

    public function getTotalPaginasProperty(): int
    {
        return max(1, (int) ceil($this->totais['qtd'] / $this->porPagina));
    }

    public function getGraficoVendasDiariasProperty(): array
    {
        $rows = $this->buildQuery()
            ->reorder()
            ->selectRaw('DATE(data_venda) as dia, SUM(valor_total_venda) as faturamento, SUM(margem_venda_total) as lucro, COUNT(*) as qtd')
            ->groupByRaw('DATE(data_venda)')
            ->orderBy('dia')
            ->get();

        return [
            'labels' => $rows->pluck('dia')->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))->toArray(),
            'faturamento' => $rows->pluck('faturamento')->map(fn ($v) => round((float) $v, 2))->toArray(),
            'lucro' => $rows->pluck('lucro')->map(fn ($v) => round((float) $v, 2))->toArray(),
        ];
    }

    public function getVendasPorCanalProperty(): array
    {
        $dados = $this->buildQuery()
            ->reorder()
            ->selectRaw('COALESCE(canal_nome, "Outros") as canal, COUNT(*) as qtd, SUM(valor_total_venda) as total, SUM(margem_venda_total) as lucro')
            ->groupBy('canal_nome')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'canal' => $r->canal,
                'qtd' => $r->qtd,
                'total' => round((float) $r->total, 2),
                'lucro' => round((float) $r->lucro, 2),
            ])
            ->toArray();

        // Agrupar canais com apenas 1 venda ou nomes inválidos em "Outros"
        $principais = [];
        $outros = ['canal' => 'Outros', 'qtd' => 0, 'total' => 0, 'lucro' => 0];

        foreach ($dados as $d) {
            if ($d['qtd'] >= 2 && strlen($d['canal']) <= 30) {
                $principais[] = $d;
            } else {
                $outros['qtd'] += $d['qtd'];
                $outros['total'] += $d['total'];
                $outros['lucro'] += $d['lucro'];
            }
        }

        if ($outros['qtd'] > 0) {
            $principais[] = $outros;
        }

        return $principais;
    }

    public static function canAccess(): bool
    {
        return true;
    }
}
