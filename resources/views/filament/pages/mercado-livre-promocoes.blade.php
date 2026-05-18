<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Seletor de Conta --}}
        <div class="flex items-center gap-4">
            <span class="text-sm font-semibold text-white">Conta:</span>
            @foreach($this->getAccounts() as $key => $name)
                <button
                    wire:click="switchAccount('{{ $key }}')"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition
                        {{ $accountKey === $key
                            ? 'bg-yellow-500 text-black shadow-lg'
                            : 'bg-gray-600 text-white hover:bg-gray-500' }}"
                >
                    {{ $name }}
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Painel Esquerdo: Lista de Promoções --}}
            <div class="lg:col-span-1">
                <div class="bg-gray-900 rounded-xl shadow-lg border border-gray-700 p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-yellow-400">
                            🏷️ Promoções ({{ count($promotions) }})
                        </h3>
                        <button wire:click="loadPromotions" class="text-sm text-yellow-400 hover:text-yellow-300 font-semibold">
                            ↻ Atualizar
                        </button>
                    </div>

                    <div class="space-y-2 max-h-[70vh] overflow-y-auto pr-1">
                        @forelse($promotions as $index => $promo)
                            <button
                                wire:click="selectPromotion({{ $index }})"
                                class="w-full text-left p-3 rounded-lg border-2 transition
                                    {{ ($selectedPromotion['id'] ?? '') === $promo['id']
                                        ? 'border-yellow-500 bg-yellow-500/10'
                                        : 'border-gray-700 hover:border-gray-500 hover:bg-gray-800' }}"
                            >
                                <div class="font-semibold text-sm text-white truncate">
                                    {{ $promo['name'] ?? 'Sem nome' }}
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold
                                        {{ match($promo['status'] ?? '') {
                                            'started' => 'bg-green-600 text-white',
                                            'candidate' => 'bg-orange-500 text-white',
                                            'scheduled' => 'bg-blue-600 text-white',
                                            default => 'bg-gray-600 text-white'
                                        } }}">
                                        {{ $promo['status'] ?? '-' }}
                                    </span>
                                    <span class="text-xs text-gray-400 font-medium">{{ $promo['type'] ?? '' }}</span>
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-gray-400 text-center py-8">Nenhuma promoção encontrada</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Painel Direito: Itens da Promoção --}}
            <div class="lg:col-span-2">
                <div class="bg-gray-900 rounded-xl shadow-lg border border-gray-700 p-4 relative min-h-[300px]">
                    {{-- Loading overlay --}}
                    <div wire:loading wire:target="selectPromotion,loadItems,loadAllItems,doLoadItems,aderirItem"
                        class="absolute inset-0 bg-gray-900/80 z-10 flex items-center justify-center rounded-xl">
                        <div class="flex items-center gap-3 text-yellow-400">
                            <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span class="text-base font-semibold">Carregando...</span>
                        </div>
                    </div>

                    @if($needsLoadItems)
                        <div wire:init="doLoadItems"></div>
                    @endif

                    @if($selectedPromotion)
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-white">
                                    {{ $selectedPromotion['name'] ?? 'Promoção' }}
                                </h3>
                                <p class="text-sm text-gray-400">
                                    <span class="text-green-400 font-semibold">{{ count($items) }}</span> de
                                    <span class="font-semibold">{{ $totalItems }}</span> itens carregados
                                    · Tipo: <span class="text-yellow-400">{{ $selectedPromotion['type'] ?? '-' }}</span>
                                </p>
                            </div>
                            <div class="flex gap-2">
                                @if($searchAfter)
                                    <button wire:click="loadItems"
                                        class="px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-500 rounded-lg text-white font-medium">
                                        + Carregar mais
                                    </button>
                                    <button wire:click="loadAllItems"
                                        wire:confirm="Carregar todos os itens pode demorar. Continuar?"
                                        class="px-3 py-1.5 text-sm bg-gray-700 hover:bg-gray-600 rounded-lg text-white font-medium">
                                        Carregar todos
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Tabela de Itens --}}
                        <div class="overflow-x-auto max-h-[65vh] overflow-y-auto rounded-lg border border-gray-700">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-gray-800 z-[5]">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left font-bold text-yellow-400 text-xs uppercase">MLB ID</th>
                                        <th class="px-3 py-2.5 text-left font-bold text-yellow-400 text-xs uppercase">Produto</th>
                                        <th class="px-3 py-2.5 text-left font-bold text-yellow-400 text-xs uppercase">Status</th>
                                        <th class="px-3 py-2.5 text-right font-bold text-yellow-400 text-xs uppercase">Preço</th>
                                        <th class="px-3 py-2.5 text-right font-bold text-yellow-400 text-xs uppercase">Preço Promo</th>
                                        <th class="px-3 py-2.5 text-center font-bold text-yellow-400 text-xs uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-800">
                                    @foreach($items as $item)
                                        <tr class="hover:bg-gray-800/70 transition">
                                            <td class="px-3 py-2.5 font-mono text-xs text-blue-300 font-semibold">
                                                {{ $item['id'] }}
                                            </td>
                                            <td class="px-3 py-2.5 text-white max-w-xs truncate font-medium" title="{{ $item['title'] }}">
                                                {{ $item['title'] }}
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold
                                                    {{ match($item['status']) {
                                                        'candidate' => 'bg-orange-500 text-white',
                                                        'active', 'started' => 'bg-green-600 text-white',
                                                        'finished' => 'bg-gray-600 text-gray-300',
                                                        default => 'bg-gray-600 text-white'
                                                    } }}">
                                                    {{ $item['status'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2.5 text-right text-white font-medium">
                                                @if($item['price'])
                                                    R$ {{ number_format($item['price'], 2, ',', '.') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-right">
                                                @if($editingItemId === $item['id'])
                                                    <div class="flex items-center gap-1 justify-end">
                                                        <input type="number" step="0.01"
                                                            wire:model="editingDealPrice"
                                                            class="w-24 px-2 py-1 text-xs border border-gray-600 rounded bg-gray-800 text-white"
                                                        >
                                                        <button wire:click="savePrice" class="text-green-400 hover:text-green-300 text-sm font-bold">✓</button>
                                                        <button wire:click="cancelEdit" class="text-red-400 hover:text-red-300 text-sm font-bold">✕</button>
                                                    </div>
                                                @else
                                                    <span class="cursor-pointer hover:underline"
                                                        wire:click="startEditPrice('{{ $item['id'] }}', {{ $item['deal_price'] ?? $item['price'] ?? 'null' }})"
                                                        title="Clique para editar preço">
                                                        @if($item['deal_price'])
                                                            <span class="font-bold text-green-400">
                                                                R$ {{ number_format($item['deal_price'], 2, ',', '.') }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-500">—</span>
                                                        @endif
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    @if($item['status'] === 'candidate')
                                                        <button
                                                            wire:click="aderirItem('{{ $item['id'] }}')"
                                                            wire:confirm="Aderir {{ $item['id'] }} à promoção com preço sugerido?"
                                                            class="text-green-400 hover:text-green-300 text-xs font-bold"
                                                            title="Aderir à promoção"
                                                        >
                                                            ✓ Aderir
                                                        </button>
                                                    @endif
                                                    <button
                                                        wire:click="removeItem('{{ $item['id'] }}')"
                                                        wire:confirm="Remover {{ $item['id'] }} da promoção?"
                                                        class="text-red-400 hover:text-red-300 text-xs font-bold"
                                                        title="Remover da promoção"
                                                    >
                                                        ✕ Remover
                                                    </button>
                                                </div>
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
                        <div class="flex items-center justify-center h-64 text-gray-500">
                            <div class="text-center">
                                <div class="text-4xl mb-3">🏷️</div>
                                <p class="text-base">Selecione uma promoção para ver os itens</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
