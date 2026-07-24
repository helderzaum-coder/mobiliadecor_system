<?php

namespace App\Filament\Resources\ContaReceberResource\Pages;

use App\Filament\Resources\ContaReceberResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListContasReceber extends ListRecords
{
    protected static string $resource = ContaReceberResource::class;
    protected static string $view = 'filament.resources.conta-receber.list';

    public string $filtro_periodo    = 'este_mes';
    public string $filtro_filtrar_por = 'data_vencimento';
    public ?string $filtro_mes       = null;
    public ?string $filtro_dia       = null;
    public ?string $filtro_inicio    = null;
    public ?string $filtro_fim       = null;
    public ?string $filtro_status    = null;
    public ?string $filtro_canal     = null;
    public ?string $filtro_conta     = null;
    public ?string $filtro_banco     = null;
    public ?string $filtro_lote      = null;

    protected $queryString = [
        'filtro_periodo'     => ['except' => 'este_mes'],
        'filtro_filtrar_por' => ['except' => 'data_vencimento'],
        'filtro_mes'         => ['except' => ''],
        'filtro_dia'         => ['except' => ''],
        'filtro_inicio'      => ['except' => ''],
        'filtro_fim'         => ['except' => ''],
        'filtro_status'      => ['except' => ''],
        'filtro_canal'       => ['except' => ''],
        'filtro_conta'       => ['except' => ''],
        'filtro_banco'       => ['except' => ''],
        'filtro_lote'        => ['except' => ''],
    ];

    public function mount(): void
    {
        parent::mount();
        $this->filtro_mes = $this->filtro_mes ?? now()->format('Y-m');
    }

    public function updatedFiltroPeriodo(): void    { $this->resetPage(); }
    public function updatedFiltroFiltrarPor(): void { $this->resetPage(); }
    public function updatedFiltroMes(): void        { $this->resetPage(); }
    public function updatedFiltroDia(): void        { $this->resetPage(); }
    public function updatedFiltroInicio(): void     { $this->resetPage(); }
    public function updatedFiltroFim(): void        { $this->resetPage(); }
    public function updatedFiltroStatus(): void     { $this->resetPage(); }
    public function updatedFiltroCanal(): void      { $this->resetPage(); }
    public function updatedFiltroConta(): void      { $this->resetPage(); }
    public function updatedFiltroBanco(): void      { $this->resetPage(); }
    public function updatedFiltroLote(): void       { $this->resetPage(); }

    protected function applyFiltersToTableQuery(Builder $query): Builder
    {
        $campo = $this->filtro_filtrar_por;

        // Período
        match ($this->filtro_periodo) {
            'hoje'           => $query->whereDate($campo === 'data_venda'
                ? 'data_vencimento' : $campo, today()),
            'dia_especifico' => $this->filtro_dia
                ? $query->whereDate($campo === 'data_venda' ? 'data_vencimento' : $campo, $this->filtro_dia)
                : null,
            'esta_semana'    => $query->whereBetween(
                $campo === 'data_venda' ? 'data_vencimento' : $campo,
                [now()->startOfWeek(), now()->endOfWeek()]
            ),
            'este_mes'       => $this->aplicarPeriodoQuery($query, $campo, now()->startOfMonth(), now()->endOfMonth()),
            'mes_passado'    => $this->aplicarPeriodoQuery($query, $campo, now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()),
            'selecionar_mes' => $this->filtro_mes
                ? $this->aplicarPeriodoQuery(
                    $query, $campo,
                    Carbon::createFromFormat('Y-m', $this->filtro_mes)->startOfMonth(),
                    Carbon::createFromFormat('Y-m', $this->filtro_mes)->endOfMonth()
                )
                : null,
            'customizado'    => $this->aplicarPeriodoQuery(
                $query, $campo,
                $this->filtro_inicio ? Carbon::parse($this->filtro_inicio) : null,
                $this->filtro_fim    ? Carbon::parse($this->filtro_fim)    : null
            ),
            default => null,
        };

        if ($this->filtro_status) {
            $query->where('status', $this->filtro_status);
        }
        if ($this->filtro_canal) {
            $query->where('forma_pagamento', $this->filtro_canal);
        }
        if ($this->filtro_conta) {
            $query->whereHas('venda', fn ($q) => $q->where('bling_account', $this->filtro_conta));
        }
        if ($this->filtro_banco) {
            $query->where('conta_bancaria_id', $this->filtro_banco);
        }
        if ($this->filtro_lote) {
            $query->where('lote_recebimento_id', $this->filtro_lote);
        }

        return $query;
    }

    private function aplicarPeriodoQuery(Builder $query, string $campo, $inicio, $fim): Builder
    {
        if ($campo === 'data_venda') {
            return $query->whereHas('venda', function ($q) use ($inicio, $fim) {
                if ($inicio) $q->whereDate('data_venda', '>=', $inicio);
                if ($fim)    $q->whereDate('data_venda', '<=', $fim);
            });
        }
        if ($inicio) $query->whereDate($campo, '>=', $inicio);
        if ($fim)    $query->whereDate($campo, '<=', $fim);
        return $query;
    }

    public function getCanaisOptions(): array
    {
        return \App\Models\ContaReceber::distinct()
            ->whereNotNull('forma_pagamento')
            ->orderBy('forma_pagamento')
            ->pluck('forma_pagamento', 'forma_pagamento')
            ->toArray();
    }

    public function getBancosOptions(): array
    {
        return \App\Models\ContaBancaria::where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->toArray();
    }

    public function getMesOptions(): array
    {
        $options = [];
        for ($i = 0; $i < 24; $i++) {
            $d = now()->subMonths($i)->startOfMonth();
            $options[$d->format('Y-m')] = ucfirst($d->locale('pt_BR')->isoFormat('MMMM [de] YYYY'));
        }
        return $options;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
