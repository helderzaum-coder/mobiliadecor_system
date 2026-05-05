<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Painel Esquerdo: Busca e Adição --}}
        <div class="space-y-4">
            {{-- Busca individual --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">🔍 Buscar Pedido</h3>
                <input type="text" wire:model.live.debounce.300ms="busca" placeholder="Digite o número do pedido..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-white px-4 py-2">

                @if($this->resultadosBusca->isNotEmpty())
                <div class="mt-3 space-y-2">
                    @foreach($this->resultadosBusca as $conta)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-sm">
                            <div>
                                <span class="font-mono text-xs text-gray-600 dark:text-gray-300">{{ $conta->venda?->numero_pedido_canal }}</span>
                                <span class="text-gray-400 mx-1">·</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $conta->forma_pagamento }}</span>
                                <span class="text-gray-400 mx-1">·</span>
                                <span class="font-semibold text-green-600">R$ {{ number_format($conta->valor_parcela, 2, ',', '.') }}</span>
                            </div>
                            <button wire:click="adicionarAoLote({{ $conta->id_conta_receber }})"
                                style="background:#10b981;color:#fff;padding:4px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                                + Adicionar
                            </button>
                        </div>
                    @endforeach
                </div>
                @elseif(strlen($busca) >= 3)
                    <p class="mt-2 text-xs text-gray-500">Nenhum pedido pendente encontrado.</p>
                @endif
            </div>

            {{-- Busca múltipla --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">📋 Adicionar Múltiplos</h3>
                <textarea wire:model="busca_multipla" rows="4" placeholder="Cole os números dos pedidos (um por linha, separados por vírgula ou espaço)..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-white px-4 py-2 font-mono"></textarea>
                <button wire:click="adicionarMultiplos"
                    style="margin-top:8px;background:#3b82f6;color:#fff;padding:8px 16px;font-size:13px;border-radius:8px;border:none;cursor:pointer;width:100%;">
                    📋 Adicionar ao Lote
                </button>
            </div>
        </div>

        {{-- Painel Direito: Lote (Carrinho) --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    🛒 Lote de Recebimentos ({{ count($lote) }} pedidos)
                </h3>
                @if(!empty($lote))
                <button wire:click="limparLote" style="color:#9ca3af;font-size:11px;cursor:pointer;background:none;border:none;text-decoration:underline;">
                    Limpar tudo
                </button>
                @endif
            </div>

            @if(empty($lote))
                <p class="text-center text-gray-500 dark:text-gray-400 py-8 text-sm">
                    Nenhum pedido no lote. Use a busca para adicionar.
                </p>
            @else
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($this->loteItens as $conta)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-700/30 text-sm">
                            <div>
                                <span class="font-mono text-xs text-gray-800 dark:text-gray-200">{{ $conta->venda?->numero_pedido_canal }}</span>
                                <span class="text-gray-400 mx-1">·</span>
                                <span class="text-xs text-gray-500">{{ $conta->forma_pagamento }}</span>
                                <span class="text-gray-400 mx-1">·</span>
                                <span class="text-xs text-gray-500">{{ $conta->venda?->data_venda?->format('d/m') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-green-600 text-sm">R$ {{ number_format($conta->valor_parcela, 2, ',', '.') }}</span>
                                <button wire:click="removerDoLote({{ $conta->id_conta_receber }})"
                                    style="color:#ef4444;font-size:14px;cursor:pointer;background:none;border:none;">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Total e Confirmação --}}
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-gray-500">Total do Lote:</span>
                        <span class="text-xl font-bold text-green-600">R$ {{ number_format($this->totalLote, 2, ',', '.') }}</span>
                    </div>

                    <div class="mb-3">
                        <label class="text-xs text-gray-500 block mb-1">Data do Recebimento:</label>
                        <input type="date" wire:model="data_recebimento" value="{{ $data_recebimento }}"
                            style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                    </div>

                    <button wire:click="confirmarLote" wire:confirm="Confirmar recebimento de {{ count($lote) }} pedido(s) totalizando R$ {{ number_format($this->totalLote, 2, ',', '.') }}?"
                        style="width:100%;padding:12px;font-size:14px;font-weight:700;border-radius:10px;border:none;cursor:pointer;background:#10b981;color:#fff;">
                        ✅ Confirmar Recebimento do Lote
                    </button>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
