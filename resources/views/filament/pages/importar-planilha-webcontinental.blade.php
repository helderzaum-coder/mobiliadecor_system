<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Como baixar a planilha:</h3>
            <div class="text-sm text-gray-500 dark:text-gray-400 space-y-1 mb-4">
                <p>1. Acesse <a href="https://portal.webcontinental.com.br/#/listar/pedidos" target="_blank" class="text-blue-500 hover:underline">portal.webcontinental.com.br/#/listar/pedidos</a></p>
                <p>2. Filtre por <strong>Data de aprovação</strong> e selecione o período desejado</p>
                <p>3. Exporte a planilha e faça o upload abaixo</p>
            </div>

            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">O que o processamento faz:</h3>
            <div class="text-sm text-gray-500 dark:text-gray-400 space-y-2">
                <p><strong>📊 Processar Planilha</strong> — Vincula os pedidos pelo Pedido ERP (coluna F) e atualiza: total do pedido, frete, valor dos produtos e comissão retida. Pedidos já processados são pulados.</p>
            </div>

            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mt-4 mb-2">🗺️ Colunas Mapeadas:</h3>
            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono bg-gray-50 dark:bg-gray-900 rounded-lg p-3 space-y-0.5">
                <p><span class="text-gray-300">A</span> = Parceiro Portal</p>
                <p><span class="text-gray-300">B</span> = Parceiro</p>
                <p><span class="text-gray-300">C</span> = ERP Parceiro</p>
                <p><span class="text-white font-semibold">D</span> = Pedido Parceiro <span class="text-blue-400">(vinculação)</span></p>
                <p><span class="text-white font-semibold">E</span> = Pedido Site <span class="text-blue-400">(vinculação)</span></p>
                <p><span class="text-white font-semibold">F</span> = Pedido ERP <span class="text-green-400">(chave principal)</span></p>
                <p><span class="text-gray-300">G</span> = Cliente</p>
                <p><span class="text-gray-300">H</span> = CPF</p>
                <p><span class="text-yellow-400 font-semibold">I</span> = Total do Pedido → <span class="text-yellow-400">valor_total_venda</span></p>
                <p><span class="text-yellow-400 font-semibold">J</span> = Valor do Frete → <span class="text-yellow-400">valor_frete_cliente</span></p>
                <p><span class="text-yellow-400 font-semibold">K</span> = Valor dos Produtos → <span class="text-yellow-400">total_produtos</span></p>
                <p><span class="text-yellow-400 font-semibold">L</span> = Desconto Total do Pedido <span class="text-gray-400">(validação)</span></p>
                <p><span class="text-gray-300">M</span> = Valor Repasse <span class="text-gray-400">(referência)</span></p>
                <p><span class="text-yellow-400 font-semibold">N</span> = Valor Comissão Retido → <span class="text-yellow-400">comissao</span></p>
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
