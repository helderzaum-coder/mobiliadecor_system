<?php

namespace App\Filament\Resources\ContaPagarResource\Pages;

use App\Filament\Resources\ContaPagarResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListContasPagar extends ListRecords
{
    protected static string $resource = ContaPagarResource::class;

    protected $queryString = [
        'periodo'         => ['except' => 'este_mes', 'as' => 'periodo'],
        'dia_selecionado' => ['except' => '', 'as' => 'dia'],
        'mes_selecionado' => ['except' => '', 'as' => 'mes'],
        'data_inicio'     => ['except' => '', 'as' => 'de'],
        'data_fim'        => ['except' => '', 'as' => 'ate'],
    ];

    public string $periodo = 'este_mes';
    public ?string $dia_selecionado = null;
    public ?string $mes_selecionado = null;
    public ?string $data_inicio = null;
    public ?string $data_fim = null;

    public function mount(): void
    {
        parent::mount();

        if (empty($this->mes_selecionado)) {
            $this->mes_selecionado = now()->format('Y-m');
        }
    }

    public function updatedPeriodo(): void { $this->resetPage(); }
    public function updatedDiaSelecionado(): void { $this->resetPage(); }
    public function updatedMesSelecionado(): void { $this->resetPage(); }
    public function updatedDataInicio(): void { $this->resetPage(); }
    public function updatedDataFim(): void { $this->resetPage(); }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    protected function getTableQuery(): Builder
    {
        return $this->aplicarFiltroPeriodo(
            parent::getTableQuery()
        );
    }

    public function aplicarFiltroPeriodo(Builder $query): Builder
    {
        return match ($this->periodo) {
            'hoje'           => $query->whereDate('data_vencimento', today()),
            'dia_especifico' => $this->dia_selecionado
                ? $query->whereDate('data_vencimento', $this->dia_selecionado)
                : $query->whereDate('data_vencimento', today()),
            'esta_semana'    => $query->whereBetween('data_vencimento', [now()->startOfWeek(), now()->endOfWeek()]),
            'este_mes'       => $query->whereBetween('data_vencimento', [now()->startOfMonth(), now()->endOfMonth()]),
            'mes_passado'    => $query->whereBetween('data_vencimento', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
            'selecionar_mes' => $this->mes_selecionado
                ? $query->whereBetween('data_vencimento', [
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->startOfMonth(),
                    now()->createFromFormat('Y-m', $this->mes_selecionado)->endOfMonth(),
                ])
                : $query,
            'customizado'    => $query
                ->when($this->data_inicio, fn ($q) => $q->whereDate('data_vencimento', '>=', $this->data_inicio))
                ->when($this->data_fim, fn ($q) => $q->whereDate('data_vencimento', '<=', $this->data_fim)),
            default => $query,
        };
    }
}
