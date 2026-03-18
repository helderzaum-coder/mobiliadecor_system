<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Faça upload da planilha de vendas do Mercado Livre para calcular o rebate dos pedidos no staging.
                Os pedidos serão vinculados pelo <strong>N.º de venda</strong> (coluna A) com o <strong>Pedido Canal</strong>.
            </p>
            <p class="text-sm text-gray-400 dark:text-gray-500">
                Fórmula: Rebate = (Total - Tarifa - Receita Produtos - Receita Envio) - Tarifa
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
