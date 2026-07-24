<?php

namespace App\Filament\Resources\LoteRecebimentoResource\Pages;

use App\Filament\Resources\LoteRecebimentoResource;
use Carbon\Carbon;
use Filament\Resources\Pages\ListRecords;

class ListLotesRecebimento extends ListRecords
{
    protected static string $resource = LoteRecebimentoResource::class;
    protected static string $view = 'filament.resources.lote-recebimento.list';

    public string $periodo = 'este_mes';
    public ?string $mes_selecionado = null;
    public ?string $dia_especifico = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;
    public ?string $busca = null;

    protected $queryString = [
        'periodo'         => ['except' => 'este_mes'],
        'mes_selecionado' => ['except' => ''],
        'dia_especifico'  => ['except' => ''],
        'data_inicio'     => ['except' => ''],
        'data_fim'        => ['except' => ''],
        'busca'           => ['except' => ''],
    ];

    public function mount(): void
    {
        parent::mount();
        $this->mes_selecionado = $this->mes_selecionado ?? now()->format('Y-m');
    }

    public function updatedPeriodo(): void { $this->resetPage(); }
    public function updatedMesSelecionado(): void { $this->resetPage(); }
    public function updatedDiaEspecifico(): void { $this->resetPage(); }
    public function updatedDataInicio(): void { $this->resetPage(); }
    public function updatedDataFim(): void { $this->resetPage(); }
    public function updatedBusca(): void { $this->resetPage(); }

    protected function applyFiltersToTableQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (!empty($this->busca)) {
            $query->where('descricao', 'like', '%' . $this->busca . '%');
        }

        return match ($this->periodo) {
            'hoje'           => $query->whereDate('data_recebimento', today()),
            'dia_especifico' => $this->dia_especifico
                ? $query->whereDate('data_recebimento', $this->dia_especifico)
                : $query,
            'esta_semana'    => $query->whereBetween('data_recebimento', [now()->startOfWeek(), now()->endOfWeek()]),
            'este_mes'       => $query->whereBetween('data_recebimento', [now()->startOfMonth(), now()->endOfMonth()]),
            'mes_passado'    => $query->whereBetween('data_recebimento', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
            'selecionar_mes' => $this->mes_selecionado
                ? $query->whereBetween('data_recebimento', [
                    Carbon::createFromFormat('Y-m', $this->mes_selecionado)->startOfMonth(),
                    Carbon::createFromFormat('Y-m', $this->mes_selecionado)->endOfMonth(),
                ])
                : $query,
            'customizado'    => $query
                ->when($this->data_inicio, fn ($q) => $q->whereDate('data_recebimento', '>=', $this->data_inicio))
                ->when($this->data_fim, fn ($q) => $q->whereDate('data_recebimento', '<=', $this->data_fim)),
            default => $query,
        };
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
}
