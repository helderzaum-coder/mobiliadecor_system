<?php

namespace App\Filament\Pages;

use App\Models\CategoriaFinanceira;
use App\Models\ContaBancaria;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Caixa extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Financeiro';
    protected static ?string $navigationLabel = 'Caixa';
    protected static ?string $title = 'Fluxo de Caixa';
    protected static string $view = 'filament.pages.caixa';
    protected static ?int $navigationSort = 1;

    public ?string $periodo = 'este_mes';
    public ?string $mes_selecionado = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public ?string $conta_bancaria_id = null;
    public ?string $categoria_id = null;
    public ?string $visao = 'diaria';
    public bool $exibir_saldo_anterior = true;
    public bool $exibir_transferencias = false;

    public function mount(): void
    {
        $this->mes_selecionado = now()->format('Y-m');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Grid::make(6)->schema([
                Forms\Components\Select::make('periodo')
                    ->label('Período')
                    ->options([
                        'este_mes' => 'Este mês',
                        'mes_passado' => 'Mês passado',
                        'selecionar_mes' => 'Selecionar mês',
                        'customizado' => 'Customizado',
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
                    ->visible(fn ($get) => $get('periodo') === 'customizado')
                    ->reactive(),
                Forms\Components\DatePicker::make('data_fim')
                    ->label('Até')
                    ->visible(fn ($get) => $get('periodo') === 'customizado')
                    ->reactive(),
                Forms\Components\Select::make('conta_bancaria_id')
                    ->label('Banco')
                    ->options(fn () => ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                    ->placeholder('Todos')
                    ->reactive(),
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->options(fn () => CategoriaFinanceira::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                    ->placeholder('Todas')
                    ->reactive(),
                Forms\Components\Select::make('visao')
                    ->label('Visão')
                    ->options([
                        'diaria' => '📅 Diária',
                        'categoria' => '📊 Por Categoria',
                    ])
                    ->default('diaria')
                    ->reactive(),
                Forms\Components\Toggle::make('exibir_saldo_anterior')
                    ->label('Exibir saldo anterior')
                    ->default(true)
                    ->reactive(),
                Forms\Components\Toggle::make('exibir_transferencias')
                    ->label('Exibir transferências')
                    ->default(false)
                    ->reactive(),
            ]),
        ]);
    }

    private function getDataRange(): array
    {
        return match ($this->periodo) {
            'este_mes' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'mes_passado' => [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()],
            'selecionar_mes' => $this->mes_selecionado
                ? [
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->startOfMonth()->toDateString(),
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->endOfMonth()->toDateString(),
                ]
                : [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'customizado' => [
                $this->data_inicio ?? now()->startOfMonth()->toDateString(),
                $this->data_fim ?? now()->endOfMonth()->toDateString(),
            ],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    private function isCategoriaTransferencia(): bool
    {
        if (!$this->categoria_id) return false;
        return CategoriaFinanceira::where('id', $this->categoria_id)->where('sistema', true)->where('nome', 'Transferência')->exists();
    }

    private function getEntradas(): Collection
    {
        [$inicio, $fim] = $this->getDataRange();
        $filtrandoTransferencia = $this->isCategoriaTransferencia();

        $query = ContaReceber::with(['venda', 'contaBancaria', 'categoria', 'loteRecebimento'])
            ->where('status', 'recebido')
            ->whereNotNull('data_recebimento')
            ->whereBetween('data_recebimento', [$inicio, $fim]);

        // Ocultar transferências se toggle desligado, sem filtro de banco e sem filtro de categoria transferência
        if (!$this->exibir_transferencias && !$this->conta_bancaria_id && !$filtrandoTransferencia) {
            $query->where('forma_pagamento', '!=', 'Transferência');
        }

        if ($this->conta_bancaria_id) {
            $query->where('conta_bancaria_id', $this->conta_bancaria_id);
        }

        // Se filtrando por categoria Transferência, buscar por categoria_id OU forma_pagamento (registros antigos)
        if ($filtrandoTransferencia) {
            $query->where(fn ($q) => $q->where('categoria_id', $this->categoria_id)->orWhere('forma_pagamento', 'Transferência'));
        } elseif ($this->categoria_id) {
            $query->where('categoria_id', $this->categoria_id);
        }

        $registros = $query->get();

        // Separar: com lote vs sem lote
        $comLote = $registros->filter(fn ($r) => !empty($r->lote_recebimento_id));
        $semLote = $registros->filter(fn ($r) => empty($r->lote_recebimento_id));

        $resultado = collect();

        // Lotes: uma única linha por lote com valor líquido (entradas - descontos)
        foreach ($comLote->groupBy('lote_recebimento_id') as $loteId => $itensLote) {
            $lote = $itensLote->first()->loteRecebimento;
            $totalEntradas = (float) $itensLote->sum('valor_parcela');

            // Buscar descontos vinculados ao mesmo lote
            $descontosLote = ContaPagar::where('lote_recebimento_id', $loteId)->sum('valor_parcela');
            $valorLiquido = $totalEntradas - (float) $descontosLote;

            $descricao = $lote?->descricao ?? $itensLote->first()->observacoes ?? 'Lote #' . $loteId;
            $resultado->push([
                'data' => $itensLote->first()->data_recebimento->format('Y-m-d'),
                'tipo' => 'entrada',
                'descricao' => $descricao,
                'categoria' => $itensLote->first()->categoria?->nome ?? $itensLote->first()->forma_pagamento ?? '-',
                'banco' => $itensLote->first()->contaBancaria?->nome ?? '-',
                'valor' => round($valorLiquido, 2),
            ]);
        }

        // Sem lote: agrupar por observacoes+data (lotes antigos) ou individual
        $comObs = $semLote->filter(fn ($r) => !empty($r->observacoes) && !str_starts_with($r->observacoes, 'Repasse #'));
        $semObs = $semLote->filter(fn ($r) => empty($r->observacoes) || str_starts_with($r->observacoes, 'Repasse #'));

        foreach ($comObs->groupBy(fn ($r) => $r->observacoes . '|' . $r->data_recebimento->format('Y-m-d')) as $chave => $itensLote) {
            $resultado->push([
                'data' => $itensLote->first()->data_recebimento->format('Y-m-d'),
                'tipo' => 'entrada',
                'descricao' => $itensLote->first()->observacoes,
                'categoria' => $itensLote->first()->categoria?->nome ?? $itensLote->first()->forma_pagamento ?? '-',
                'banco' => $itensLote->first()->contaBancaria?->nome ?? '-',
                'valor' => (float) $itensLote->sum('valor_parcela'),
            ]);
        }

        foreach ($semObs as $r) {
            $resultado->push([
                'data' => $r->data_recebimento->format('Y-m-d'),
                'tipo' => 'entrada',
                'descricao' => $r->venda ? "Repasse #{$r->venda->numero_pedido_canal}" : ($r->observacoes ?: 'Recebimento'),
                'categoria' => $r->categoria?->nome ?? $r->forma_pagamento ?? '-',
                'banco' => $r->contaBancaria?->nome ?? '-',
                'valor' => (float) $r->valor_parcela,
            ]);
        }

        return $resultado;
    }

    private function getSaidas(): Collection
    {
        [$inicio, $fim] = $this->getDataRange();
        $filtrandoTransferencia = $this->isCategoriaTransferencia();

        $query = ContaPagar::with(['fatura', 'contaBancaria', 'categoria'])
            ->where('status', 'pago')
            ->whereNotNull('data_pagamento')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->whereNull('lote_recebimento_id'); // Descontos de lote já estão abatidos na entrada

        // Ocultar transferências se toggle desligado, sem filtro de banco e sem filtro de categoria transferência
        if (!$this->exibir_transferencias && !$this->conta_bancaria_id && !$filtrandoTransferencia) {
            $query->where('forma_pagamento', '!=', 'Transferência');
        }

        if ($this->conta_bancaria_id) {
            $query->where('conta_bancaria_id', $this->conta_bancaria_id);
        }

        // Se filtrando por categoria Transferência, buscar por categoria_id OU forma_pagamento (registros antigos)
        if ($filtrandoTransferencia) {
            $query->where(fn ($q) => $q->where('categoria_id', $this->categoria_id)->orWhere('forma_pagamento', 'Transferência'));
        } elseif ($this->categoria_id) {
            $query->where('categoria_id', $this->categoria_id);
        }

        return $query->get()->map(fn ($r) => [
            'data' => $r->data_pagamento->format('Y-m-d'),
            'tipo' => 'saida',
            'descricao' => $r->descricao ?: $r->observacoes ?: ($r->fatura ? "Fatura #{$r->fatura->id_fatura}" : 'Pagamento'),
            'categoria' => $r->categoria?->nome ?? $r->forma_pagamento ?? '-',
            'banco' => $r->contaBancaria?->nome ?? '-',
            'valor' => (float) $r->valor_parcela,
        ]);
    }

    public function getSaldoAnteriorProperty(): float
    {
        if (!$this->exibir_saldo_anterior) return 0;

        [$inicio] = $this->getDataRange();

        $entradasAntes = ContaReceber::where('status', 'recebido')
            ->whereNotNull('data_recebimento')
            ->where('data_recebimento', '<', $inicio)
            ->when(!$this->exibir_transferencias && !$this->conta_bancaria_id, fn ($q) => $q->where('forma_pagamento', '!=', 'Transferência'))
            ->when($this->conta_bancaria_id, fn ($q) => $q->where('conta_bancaria_id', $this->conta_bancaria_id))
            ->sum('valor_parcela');

        // Descontos vinculados a lotes já estão abatidos das entradas, excluir das saídas
        $saidasAntes = ContaPagar::where('status', 'pago')
            ->whereNotNull('data_pagamento')
            ->where('data_pagamento', '<', $inicio)
            ->whereNull('lote_recebimento_id')
            ->when(!$this->exibir_transferencias && !$this->conta_bancaria_id, fn ($q) => $q->where('forma_pagamento', '!=', 'Transferência'))
            ->when($this->conta_bancaria_id, fn ($q) => $q->where('conta_bancaria_id', $this->conta_bancaria_id))
            ->sum('valor_parcela');

        // Descontos de lotes (abatidos das entradas)
        $descontosLotesAntes = ContaPagar::where('status', 'pago')
            ->whereNotNull('data_pagamento')
            ->where('data_pagamento', '<', $inicio)
            ->whereNotNull('lote_recebimento_id')
            ->when($this->conta_bancaria_id, fn ($q) => $q->where('conta_bancaria_id', $this->conta_bancaria_id))
            ->sum('valor_parcela');

        $saldoInicialBanco = 0;
        if ($this->conta_bancaria_id) {
            $banco = ContaBancaria::find($this->conta_bancaria_id);
            $saldoInicialBanco = (float) ($banco->saldo_inicial ?? 0);
        } else {
            $saldoInicialBanco = (float) ContaBancaria::where('ativo', true)->sum('saldo_inicial');
        }

        return $saldoInicialBanco + (float) $entradasAntes - (float) $descontosLotesAntes - (float) $saidasAntes;
    }

    public function getMovimentacoesProperty(): array
    {
        $entradas = $this->getEntradas();
        $saidas = $this->getSaidas();

        $todas = $entradas->concat($saidas)->sortBy('data')->values();

        if ($this->visao === 'categoria') {
            return $this->agruparPorCategoria($todas);
        }

        return $this->agruparPorDia($todas);
    }

    private function agruparPorDia(Collection $movimentacoes): array
    {
        $dias = [];
        $saldoAcumulado = $this->saldoAnterior;

        foreach ($movimentacoes->groupBy('data') as $data => $itens) {
            $entradasDia = $itens->where('tipo', 'entrada')->sum('valor');
            $saidasDia = $itens->where('tipo', 'saida')->sum('valor');

            $dias[] = [
                'data' => $data,
                'itens' => $itens->values()->toArray(),
                'entradas' => $entradasDia,
                'saidas' => $saidasDia,
                'saldo_dia' => $entradasDia - $saidasDia,
                'saldo_inicio_dia' => $saldoAcumulado,
                'saldo_acumulado' => $saldoAcumulado + $entradasDia - $saidasDia,
            ];

            $saldoAcumulado += $entradasDia - $saidasDia;
        }

        return $dias;
    }

    private function agruparPorCategoria(Collection $movimentacoes): array
    {
        $categorias = [];

        foreach ($movimentacoes->groupBy('categoria') as $cat => $itens) {
            $entradas = $itens->where('tipo', 'entrada')->sum('valor');
            $saidas = $itens->where('tipo', 'saida')->sum('valor');

            $categorias[] = [
                'categoria' => $cat,
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $entradas - $saidas,
                'qtd' => $itens->count(),
            ];
        }

        usort($categorias, fn ($a, $b) => $a['categoria'] <=> $b['categoria']);

        return $categorias;
    }

    public function getTotaisProperty(): array
    {
        $entradas = $this->getEntradas()->sum('valor');
        $saidas = $this->getSaidas()->sum('valor');

        return [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'resultado' => $entradas - $saidas,
            'saldo_final' => $this->saldoAnterior + $entradas - $saidas,
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('transferencia')
                ->label('Transferência')
                ->icon('heroicon-o-arrows-right-left')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('conta_origem_id')
                        ->label('Conta Origem')
                        ->options(fn () => ContaBancaria::where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->toArray())
                        ->required()
                        ->reactive(),
                    Forms\Components\Select::make('conta_destino_id')
                        ->label('Conta Destino')
                        ->options(fn ($get) => ContaBancaria::where('ativo', true)
                            ->when($get('conta_origem_id'), fn ($q, $id) => $q->where('id', '!=', $id))
                            ->orderBy('nome')->pluck('nome', 'id')->toArray())
                        ->required(),
                    Forms\Components\TextInput::make('valor')
                        ->label('Valor')
                        ->numeric()
                        ->prefix('R$')
                        ->required()
                        ->minValue(0.01),
                    Forms\Components\DatePicker::make('data')
                        ->label('Data')
                        ->default(now())
                        ->required(),
                    Forms\Components\TextInput::make('descricao')
                        ->label('Descrição (opcional)')
                        ->placeholder('Ex: Transferência para pagar fornecedor'),
                ])
                ->action(function (array $data) {
                    $this->executarTransferencia($data);
                }),
            Actions\Action::make('importar_transferencias')
                ->label('Importar Transferências')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    Forms\Components\FileUpload::make('arquivo')
                        ->label('Planilha CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->helperText('Formato: banco_origem;banco_destino;valor;data (dd/mm/aaaa). Separador: ponto-e-vírgula.')
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports'),
                ])
                ->action(function (array $data) {
                    $this->importarTransferencias($data);
                }),
        ];
    }

    private function executarTransferencia(array $data): void
    {
        $origem = ContaBancaria::find($data['conta_origem_id']);
        $destino = ContaBancaria::find($data['conta_destino_id']);
        $valor = round((float) $data['valor'], 2);
        $desc = $data['descricao'] ?: "Transferência {$origem->nome} → {$destino->nome}";
        $transferenciaId = Str::uuid()->toString();
        $categoriaTransf = CategoriaFinanceira::where('nome', 'Transferência')->where('sistema', true)->first()?->id;

        ContaPagar::create([
            'valor_parcela' => $valor,
            'data_vencimento' => $data['data'],
            'data_pagamento' => $data['data'],
            'data_lancamento' => now()->toDateString(),
            'status' => 'pago',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'forma_pagamento' => 'Transferência',
            'descricao' => $desc,
            'observacoes' => "↗ {$desc}",
            'lancamento_manual' => true,
            'conta_bancaria_id' => $data['conta_origem_id'],
            'categoria_id' => $categoriaTransf,
            'transferencia_id' => $transferenciaId,
        ]);

        ContaReceber::create([
            'valor_parcela' => $valor,
            'data_vencimento' => $data['data'],
            'data_recebimento' => $data['data'],
            'status' => 'recebido',
            'numero_parcela' => 1,
            'total_parcelas' => 1,
            'forma_pagamento' => 'Transferência',
            'observacoes' => "↙ {$desc}",
            'lancamento_manual' => true,
            'conta_bancaria_id' => $data['conta_destino_id'],
            'categoria_id' => $categoriaTransf,
            'transferencia_id' => $transferenciaId,
        ]);

        Notification::make()
            ->title("Transferência de R$ " . number_format($valor, 2, ',', '.') . " realizada")
            ->body("{$origem->nome} → {$destino->nome}")
            ->success()
            ->send();
    }

    private function importarTransferencias(array $data): void
    {
        $path = storage_path('app/' . $data['arquivo']);
        if (!file_exists($path)) {
            Notification::make()->title('Arquivo não encontrado.')->danger()->send();
            return;
        }

        $conteudo = file_get_contents($path);
        $linhas = array_filter(explode("\n", str_replace("\r", '', $conteudo)));
        $bancos = ContaBancaria::where('ativo', true)->get()->keyBy(fn ($b) => mb_strtolower(trim($b->nome)));

        $importados = 0;
        $erros = [];

        foreach ($linhas as $i => $linha) {
            $num = $i + 1;
            $linha = trim($linha);
            if (empty($linha)) continue;

            // Pular cabeçalho
            if ($num === 1 && (str_contains(mb_strtolower($linha), 'origem') || str_contains(mb_strtolower($linha), 'banco'))) {
                continue;
            }

            $cols = str_getcsv($linha, ';');
            if (count($cols) < 4) {
                $erros[] = "Linha {$num}: formato inválido (menos de 4 colunas)";
                continue;
            }

            $nomeOrigem = mb_strtolower(trim($cols[0]));
            $nomeDestino = mb_strtolower(trim($cols[1]));
            $valorStr = trim($cols[2]);
            $dataStr = trim($cols[3]);

            $origem = $bancos[$nomeOrigem] ?? null;
            $destino = $bancos[$nomeDestino] ?? null;

            if (!$origem) {
                $erros[] = "Linha {$num}: banco origem '{$cols[0]}' não encontrado";
                continue;
            }
            if (!$destino) {
                $erros[] = "Linha {$num}: banco destino '{$cols[1]}' não encontrado";
                continue;
            }
            if ($origem->id === $destino->id) {
                $erros[] = "Linha {$num}: origem e destino são iguais";
                continue;
            }

            // Parsear valor (aceita vírgula como decimal)
            $valor = (float) str_replace(['.', ','], ['', '.'], $valorStr);
            if ($valor <= 0) {
                $erros[] = "Linha {$num}: valor inválido '{$valorStr}'";
                continue;
            }

            // Parsear data (dd/mm/aaaa ou aaaa-mm-dd)
            $dataParsed = null;
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataStr, $m)) {
                $dataParsed = "{$m[3]}-{$m[2]}-{$m[1]}";
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataStr)) {
                $dataParsed = $dataStr;
            }

            if (!$dataParsed) {
                $erros[] = "Linha {$num}: data inválida '{$dataStr}'";
                continue;
            }

            $this->executarTransferencia([
                'conta_origem_id' => $origem->id,
                'conta_destino_id' => $destino->id,
                'valor' => $valor,
                'data' => $dataParsed,
                'descricao' => '',
            ]);

            $importados++;
        }

        @unlink($path);

        $msg = "{$importados} transferência(s) importada(s).";
        if (!empty($erros)) {
            $msg .= " " . count($erros) . " erro(s): " . implode(' | ', array_slice($erros, 0, 5));
        }

        Notification::make()
            ->title($msg)
            ->{$importados > 0 ? 'success' : 'warning'}()
            ->send();
    }
}
