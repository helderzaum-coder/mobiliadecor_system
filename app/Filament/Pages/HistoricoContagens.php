<?php

namespace App\Filament\Pages;

use App\Models\ContagemEstoque as ContagemEstoqueModel;
use Filament\Pages\Page;

class HistoricoContagens extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $navigationLabel = 'Histórico de Contagens';
    protected static ?string $title = 'Histórico de Contagens';
    protected static string $view = 'filament.pages.historico-contagens';

    public ?int $contagemAberta = null;

    public function abrirContagem(int $id): void
    {
        $this->contagemAberta = $this->contagemAberta === $id ? null : $id;
    }

    public function getContagens()
    {
        return ContagemEstoqueModel::with('user')
            ->latest()
            ->get();
    }

    public function getItens()
    {
        if (!$this->contagemAberta) return collect();
        return ContagemEstoqueModel::find($this->contagemAberta)?->itens ?? collect();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
