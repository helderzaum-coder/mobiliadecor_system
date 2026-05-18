<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Seletor de Conta --}}
        <div class="flex items-center gap-4">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Conta:</span>
            @foreach($this->getAccounts() as $key => $name)
                <button
                    wire:click="switchAccount('{{ $key }}')"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition
                        {{ $accountKey === $key
                            ? 'bg-primary-600 text-white shadow'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300' }}"
                >
                    {{ $name }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Painel Esquerdo: Lista de Promoções --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Promoções ({{ count($promotions) }})
                        </h3>
                        <button wire:click="loadPromotions" class="text-sm text-primary-600 hover:text-primary-800">
                            ↻ Atualizar
                        </button>
                    </div>

                    <div class="space-y-2 max-h-[70vh] overflow-y-auto">
                        @forelse($promotions as $index => $promo)
                            <button
                                wire:click="selectPromotion({{ $index }})"
                                class="w-full text-left p-3 rounded-lg border transition
                                    {{ ($selectedPromotion['id'] ?? '') === $promo['id']
                                        ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                        : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                            >
                                <div class="font-medium text-sm text-gray-900 dark:text-white truncate">
                                    {{ $promo['name'] ?? 'Sem nome' }}
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ match($promo['status'] ?? '') {
                                            'started' => 'bg-green-100 text-green-800',
                                            'candidate' => 'bg-yellow-100 text-yellow-800',
                                            'scheduled' => 'bg-blue-100 text-blue-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        } }}">
                                        {{ $promo['status'] ?? '-' }}
                                    </span>
                                    <span class="text-xs text-gray-500">{{ $promo['type'] ?? '' }}</span>
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-gray-500 text-center py-8">Nenhuma promoção encontrada</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Painel Direito: Itens da Promoção --}}
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 relative">
                    {{-- Loading overlay --}}
                    <div wire:loading wire:target="selectPromotion,loadItems,loadAllItems,doLoadItems"
                        class="absolute inset-0 bg-white/70 dark:bg-gray-800/70 z-10 flex items-center justify-center rounded-xl">
                        <div class="flex items-center gap-2 text-primary-600">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span class="text-sm font-medium">Carregando...</span>
                        </div>
                    </div>

                    @if($needsLoadItems)
                        <div wire:init="doLoadItems"></div>
                    @endif

                    @if($selectedPromotion)
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ $selectedPromotion['name'] ?? 'Promoção' }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    {{ count($items) }} de {{ $totalItems }} itens carregados
                                    · Tipo: {{ $selectedPromotion['type'] ?? '-' }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                @if($searchAfter)
                                    <button wire:click="loadItems"
                                        class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                        + Carregar mais
                                    </button>
                                    <button wire:click="loadAllItems"
                                        wire:confirm="Carregar todos os itens pode demorar. Continuar?"
                                        class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                        Carregar todos
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Tabela de Itens --}}
                        <div class="overflow-x-auto max-h-[65vh] overflow-y-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">MLB ID</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Produto</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Preço</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Preço Promo</th>
                                        <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-300">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($items as $item)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">
                                                {{ $item['id'] }}
                                            </td>
                                            <td class="px-3 py-2 text-gray-900 dark:text-white max-w-xs truncate" title="{{ $item['title'] }}">
                                                {{ $item['title'] }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                                    {{ match($item['status']) {
                                                        'candidate' => 'bg-yellow-100 text-yellow-800',
                                                        'active', 'started' => 'bg-green-100 text-green-800',
                                                        'finished' => 'bg-gray-100 text-gray-600',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    } }}">
                                                    {{ $item['status'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                                                @if($item['price'])
                                                    R$ {{ number_format($item['price'], 2, ',', '.') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                @if($editingItemId === $item['id'])
                                                    <div class="flex items-center gap-1 justify-end">
                                                        <input type="number" step="0.01"
                                                            wire:model="editingDealPrice"
                                                            class="w-24 px-2 py-1 text-xs border rounded dark:bg-gray-700 dark:border-gray-600"
                                                        >
                                                        <button wire:click="savePrice" class="text-green-600 hover:text-green-800 text-xs font-bold">✓</button>
                                                        <button wire:click="cancelEdit" class="text-red-600 hover:text-red-800 text-xs font-bold">✕</button>
                                                    </div>
                                                @else
                                                    <span class="text-gray-700 dark:text-gray-300 cursor-pointer hover:text-primary-600"
                                                        wire:click="startEditPrice('{{ $item['id'] }}', {{ $item['deal_price'] ?? $item['price'] ?? 'null' }})"
                                                        title="Clique para editar">
                                                        @if($item['deal_price'])
                                                            <span class="font-semibold text-green-700 dark:text-green-400">
                                                                R$ {{ number_format($item['deal_price'], 2, ',', '.') }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button
                                                    wire:click="removeItem('{{ $item['id'] }}')"
                                                    wire:confirm="Remover {{ $item['id'] }} da promoção?"
                                                    class="text-red-500 hover:text-red-700 text-xs font-medium"
                                                    title="Remover da promoção"
                                                >
                                                    ✕ Remover
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if(empty($items))
                                <p class="text-center text-gray-500 py-8">Nenhum item carregado</p>
                            @endif
                        </div>
                    @else
                        <div class="flex items-center justify-center h-64 text-gray-400">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                <p>Selecione uma promoção para ver os itens</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
