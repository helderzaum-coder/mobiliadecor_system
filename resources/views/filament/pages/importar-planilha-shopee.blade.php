<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Faça upload da planilha de vendas da Shopee para atualizar os valores reais dos pedidos no staging.
                Os pedidos serão vinculados pelo <strong>ID do Pedido</strong> (coluna A) com o <strong>Nº Pedido Canal</strong>.
            </p>
            <p class="text-sm text-yellow-600 dark:text-yellow-400">
                ⚠ Pedidos Shopee só podem ser aprovados após o processamento da planilha.
            </p>
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Processar Planilha
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
