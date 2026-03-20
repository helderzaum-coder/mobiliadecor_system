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
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="processar">Processar Planilha</span>
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
