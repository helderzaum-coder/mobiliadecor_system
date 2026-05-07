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
            <p class="text-sm text-yellow-600 dark:text-yellow-400 mt-3">
                ⚠ Pedidos Shopee só ficam "Completos" após passar pela planilha de afiliados.
            </p>
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
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Marcar período sem afiliado</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                Marca todas as vendas Shopee do período como "planilha afiliado processada" com comissão = 0.
                Use após confirmar que nenhum pedido do período teve afiliado, ou após importar a planilha (para fechar os que não apareceram).
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
