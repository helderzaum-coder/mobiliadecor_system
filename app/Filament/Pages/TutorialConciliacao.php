<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class TutorialConciliacao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Ajuda';
    protected static ?string $navigationLabel = 'Tutorial Conciliação';
    protected static ?string $title = 'Tutorial de Conciliação';
    protected static string $view = 'filament.pages.tutorial-conciliacao';
    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }
}
