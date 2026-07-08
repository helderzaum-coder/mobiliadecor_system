<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Favoritos atuais --}}
        @php $favorites = auth()->user()->favorites; @endphp
        @if($favorites->isNotEmpty())
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6">
            <h3 style="font-size:14px;font-weight:700;color:#f59e0b;margin-bottom:12px;">⭐ Meus Favoritos</h3>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                @foreach($favorites as $fav)
                <div style="display:flex;align-items:center;gap:6px;padding:8px 12px;border-radius:8px;background:#1f2937;border:1px solid #374151;">
                    <a href="{{ $fav->url }}" style="color:#e5e7eb;font-size:13px;text-decoration:none;font-weight:500;">{{ $fav->label }}</a>
                    <button wire:click="removeFavorite('{{ $fav->url }}')" style="color:#ef4444;background:none;border:none;cursor:pointer;font-size:14px;padding:0 4px;" title="Remover">✕</button>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Todas as páginas --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6">
            <h3 style="font-size:14px;font-weight:700;color:#9ca3af;margin-bottom:12px;">Todas as Páginas</h3>
            @php
                $grouped = collect($allPages)->groupBy('group');
            @endphp
            @foreach($grouped as $group => $pages)
            <div style="margin-bottom:16px;">
                <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:6px;text-transform:uppercase;">{{ $group ?: 'Sem grupo' }}</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    @foreach($pages as $page)
                    @php $isFav = in_array($page['url'], $favoriteUrls); @endphp
                    <button wire:click="toggleFavorite('{{ $page['url'] }}', '{{ $page['label'] }}', '{{ $page['icon'] }}')"
                        style="display:flex;align-items:center;gap:4px;padding:6px 12px;border-radius:6px;font-size:12px;cursor:pointer;border:1px solid {{ $isFav ? '#f59e0b' : '#374151' }};background:{{ $isFav ? 'rgba(245,158,11,.1)' : 'transparent' }};color:{{ $isFav ? '#f59e0b' : '#9ca3af' }};">
                        {{ $isFav ? '⭐' : '☆' }} {{ $page['label'] }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
