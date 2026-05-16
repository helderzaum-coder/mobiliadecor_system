<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Faça upload do relatório de conversão de afiliados da Shopee (SellerConversionReport .csv).
                O sistema vai gravar o valor de "Despesas (R$)" no campo <strong>comissão afiliado</strong> de cada pedido e recalcular as margens.
            </p>
            <p class="mt-3">
                <a href="https://seller.shopee.com.br/portal/web-seller-affiliate/conversion_report" target="_blank"
                    style="color:#f59e0b;text-decoration:underline;">
                    📥 Baixar relatório: Central de Marketing › Afiliados do Vendedor › Conversão de Pedidos
                </a>
            </p>
        </div>

        {{-- Destaque: a partir de quando falta importar --}}
        @if($this->data_primeiro_pendente)
            <div class="bg-red-50 dark:bg-red-900/20 rounded-xl shadow p-4 border border-red-300 dark:border-red-700 mb-6 flex items-center gap-3">
                <span class="text-2xl">⚠️</span>
                <div>
                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">
                        Importar pedidos a partir do dia: {{ $this->data_primeiro_pendente }}
                    </p>
                    <p class="text-xs text-red-600 dark:text-red-400">
                        Existem pedidos Shopee sem comissão de afiliado definida desde esta data.
                    </p>
                </div>
            </div>
        @else
            <div class="bg-green-50 dark:bg-green-900/20 rounded-xl shadow p-4 border border-green-300 dark:border-green-700 mb-6 flex items-center gap-3">
                <span class="text-2xl">✅</span>
                <p class="text-sm font-semibold text-green-800 dark:text-green-200">
                    Todos os pedidos Shopee estão com comissão de afiliado processada.
                </p>
            </div>
        @endif

        {{-- Período da importação --}}
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl shadow p-6 border border-amber-300 dark:border-amber-700 mb-6">
            <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-200 mb-2">📅 Período da Importação</h3>
            <p class="text-xs text-amber-700 dark:text-amber-300 mb-4">
                Informe o período que a planilha cobre. Ao processar:
                <br>• Pedidos <strong>anteriores</strong> ao período serão travados automaticamente (sem afiliado).
                <br>• Pedidos <strong>do período</strong> que não aparecerem na planilha serão marcados como "sem afiliado".
            </p>
            <div class="flex gap-3 items-end flex-wrap">
                <div>
                    <label class="text-xs text-amber-700 dark:text-amber-300 font-medium">De</label>
                    <input type="date" wire:model.live="data_inicio_periodo" class="block w-full rounded-lg border-amber-300 dark:border-amber-600 dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="text-xs text-amber-700 dark:text-amber-300 font-medium">Até</label>
                    <input type="date" wire:model.live="data_fim_periodo" class="block w-full rounded-lg border-amber-300 dark:border-amber-600 dark:bg-gray-700 text-sm">
                </div>
            </div>

            @if($this->data_inicio_periodo && $this->data_fim_periodo)
                <div class="mt-4 flex gap-4 text-xs">
                    @if($this->pendentes_anteriores > 0)
                        <span class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 px-2 py-1 rounded">
                            🔒 {{ $this->pendentes_anteriores }} pedido(s) anterior(es) serão travados
                        </span>
                    @endif
                    <span class="bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-2 py-1 rounded">
                        📋 {{ $this->pedidos_pendentes }} pedido(s) pendente(s) no período
                    </span>
                </div>
            @endif
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="processar">📊 Processar Planilha de Afiliados</span>
                    <span wire:loading wire:target="processar">Processando...</span>
                </x-filament::button>
            </div>
        </form>

        <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Marcar período sem afiliado (manual)</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                Marca todas as vendas Shopee do período como "planilha afiliado processada" com comissão = 0.
                Use quando precisar fechar um período manualmente sem importar planilha.
            </p>
            <div class="flex gap-3 items-end">
                <div>
                    <label class="text-xs text-gray-500">De</label>
                    <input type="date" wire:model="data_inicio_marcar" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                </div>
                <div>
                    <label class="text-xs text-gray-500">Até</label>
                    <input type="date" wire:model="data_fim_marcar" class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm">
                </div>
                <button wire:click="marcarPeriodo" wire:loading.attr="disabled"
                    wire:confirm="Marcar todas as vendas Shopee do período como processadas (sem afiliado)?"
                    class="px-4 py-2 bg-gray-600 text-white text-sm rounded-lg hover:bg-gray-500 whitespace-nowrap">
                    ✅ Marcar período
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
