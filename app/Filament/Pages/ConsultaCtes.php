<?php

namespace App\Filament\Pages;

use App\Models\Cte;
use Filament\Pages\Page;

class ConsultaCtes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Integrações';
    protected static ?string $navigationLabel = 'CT-es Importados';
    protected static ?string $title = 'CT-es Importados';
    protected static string $view = 'filament.pages.consulta-ctes';

    public string $filtro = 'nao_utilizados';
    public string $busca = '';
    public string $transportadora = '';
    public string $periodo = '';
    public ?string $data_inicio = null;
    public ?string $data_fim = null;

    public function getCtesProperty()
    {
        $query = Cte::orderByRaw('COALESCE(data_emissao, created_at) DESC');

        // Filtro status
        if ($this->filtro === 'nao_utilizados') {
            $query->where('utilizado', false);
        } elseif ($this->filtro === 'utilizados') {
            $query->where('utilizado', true);
        }

        // Busca por número CT-e, chave NF-e ou destinatário
        if ($this->busca) {
            $busca = $this->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('numero_cte', 'like', "%{$busca}%")
                  ->orWhere('chave_nfe', 'like', "%{$busca}%")
                  ->orWhere('chave_cte', 'like', "%{$busca}%")
                  ->orWhere('destinatario', 'like', "%{$busca}%")
                  ->orWhere('remetente', 'like', "%{$busca}%");
            });
        }

        // Filtro transportadora
        if ($this->transportadora) {
            $query->where('transportadora', $this->transportadora);
        }

        // Filtro período
        $this->aplicarFiltroPeriodo($query);

        return $query->limit(300)->get();
    }

    public function getTotaisProperty(): array
    {
        return [
            'total' => Cte::count(),
            'utilizados' => Cte::where('utilizado', true)->count(),
            'nao_utilizados' => Cte::where('utilizado', false)->count(),
            'valor_nao_utilizado' => Cte::where('utilizado', false)->sum('valor_frete'),
        ];
    }

    public function getTransportadorasProperty(): array
    {
        return Cte::distinct()->whereNotNull('transportadora')
            ->where('transportadora', '!=', '')
            ->orderBy('transportadora')
            ->pluck('transportadora')
            ->toArray();
    }

    private function aplicarFiltroPeriodo($query): void
    {
        $campo = 'data_emissao';

        match ($this->periodo) {
            'hoje' => $query->whereDate($campo, today()),
            'esta_semana' => $query->whereBetween($campo, [now()->startOfWeek(), now()->endOfWeek()]),
            'este_mes' => $query->whereBetween($campo, [now()->startOfMonth(), now()->endOfMonth()]),
            'mes_passado' => $query->whereBetween($campo, [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]),
            'customizado' => $query
                ->when($this->data_inicio, fn ($q) => $q->whereDate($campo, '>=', $this->data_inicio))
                ->when($this->data_fim, fn ($q) => $q->whereDate($campo, '<=', $this->data_fim)),
            default => null,
        };
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
