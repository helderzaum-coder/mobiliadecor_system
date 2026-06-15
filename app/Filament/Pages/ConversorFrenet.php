<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ConversorFrenet extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Ferramentas';
    protected static ?string $navigationLabel = 'Conversor Frenet';
    protected static ?string $title = 'Conversor Frenet - Etiquetas';
    protected static string $view = 'filament.pages.conversor-frenet';
    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'operador']) ?? false;
    }
}
