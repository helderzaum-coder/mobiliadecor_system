<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Como funciona:</h3>
            <div class="text-sm text-gray-500 dark:text-gray-400 space-y-2">
                <p><strong>📊 Processar Planilha</strong> — Importa os valores financeiros da Shopee (comissão, subsídio pix, frete) e vincula aos pedidos no staging pelo ID do Pedido (coluna A).</p>
                <p><strong>📋 Corrigir Dados no Bling</strong> — Atualiza no Bling o cadastro do cliente (nome, CPF, telefone, endereço) e as observações internas do pedido com os dados da planilha. Pedidos já corrigidos são pulados automaticamente.</p>
                <p><strong>🔄 Reprocessar Todos</strong> — Força a correção de todos os pedidos da planilha no Bling, inclusive os já processados anteriormente.</p>
            </div>
            <p class="text-sm text-yellow-600 dark:text-yellow-400 mt-3">
                ⚠ Pedidos Shopee só podem ser aprovados após o processamento da planilha.
            </p>
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4 flex gap-3">
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

                <x-filament::button color="warning" wire:click="corrigirDadosBling" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="corrigirDadosBling">📋 Corrigir Dados no Bling</span>
                    <span wire:loading wire:target="corrigirDadosBling" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Corrigindo...
                    </span>
                </x-filament::button>

                <x-filament::button color="danger" wire:click="reprocessarDadosBling" wire:loading.attr="disabled"
                    wire:confirm="Isso vai reprocessar TODOS os pedidos da planilha, inclusive os já corrigidos. Continuar?">
                    <span wire:loading.remove wire:target="reprocessarDadosBling">🔄 Reprocessar Todos</span>
                    <span wire:loading wire:target="reprocessarDadosBling" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Reprocessando...
                    </span>
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
