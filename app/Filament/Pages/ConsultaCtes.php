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

    public function getCtesProperty()
    {
        $query = Cte::orderBy('created_at', 'desc');

        return match ($this->filtro) {
            'nao_utilizados' => $query->where('utilizado', false)->get(),
            'utilizados' => $query->where('utilizado', true)->get(),
            default => $query->limit(200)->get(),
        };
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

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
