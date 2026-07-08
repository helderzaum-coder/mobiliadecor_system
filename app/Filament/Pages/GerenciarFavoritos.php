<?php

namespace App\Filament\Pages;

use App\Models\UserFavorite;
use Filament\Pages\Page;
use Filament\Navigation\NavigationItem;

class GerenciarFavoritos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Favoritos';
    protected static ?string $title = 'Gerenciar Favoritos';
    protected static ?string $navigationGroup = 'Favoritos';
    protected static ?int $navigationSort = -100;
    protected static string $view = 'filament.pages.gerenciar-favoritos';

    public array $allPages = [];
    public array $favoriteUrls = [];

    public function mount(): void
    {
        $this->loadFavorites();
        $this->loadAllPages();
    }

    private function loadFavorites(): void
    {
        $this->favoriteUrls = auth()->user()->favorites()->pluck('url')->toArray();
    }

    private function loadAllPages(): void
    {
        $navigation = filament()->getCurrentPanel()->getNavigation();
        $pages = [];

        foreach ($navigation as $group) {
            foreach ($group->getItems() as $item) {
                $url = $item->getUrl();
                if ($url && $url !== request()->url()) {
                    $pages[] = [
                        'label' => $item->getLabel(),
                        'url' => $url,
                        'icon' => $item->getIcon(),
                        'group' => $group->getLabel(),
                    ];
                }
            }
        }

        $this->allPages = $pages;
    }

    public function toggleFavorite(string $url, string $label, ?string $icon = null): void
    {
        $user = auth()->user();
        $existing = $user->favorites()->where('url', $url)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $user->favorites()->create([
                'url' => $url,
                'label' => $label,
                'icon' => $icon,
                'sort_order' => $user->favorites()->count(),
            ]);
        }

        $this->loadFavorites();
    }

    public function removeFavorite(string $url): void
    {
        auth()->user()->favorites()->where('url', $url)->delete();
        $this->loadFavorites();
    }
}
