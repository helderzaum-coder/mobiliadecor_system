<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Como baixar a planilha:</h3>
            <div class="text-sm text-gray-500 dark:text-gray-400 space-y-1 mb-4">
                <p>1. Acesse o portal da Webcontinental</p>
                <p>2. Exporte a planilha de pedidos do período desejado</p>
                <p>3. Faça o upload abaixo</p>
            </div>

            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">O que o processamento faz:</h3>
            <div class="text-sm text-gray-500 dark:text-gray-400 space-y-2">
                <p><strong>📊 Processar Planilha</strong> — Vincula os pedidos pelo Pedido ERP (coluna F) e atualiza: total do pedido, frete, valor dos produtos e comissão retida. Pedidos já processados são pulados.</p>
            </div>
            <p class="text-sm text-yellow-600 dark:text-yellow-400 mt-3">
                ⚠ A comissão utilizada é o "Valor de Comissão Retido" informado pela Webcontinental na planilha (já inclui ajustes de desconto).
            </p>
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="processar">📊 Processar Planilha</span>
                    <span wire:loading wire:target="processar" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Processando...
                    </span>
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
