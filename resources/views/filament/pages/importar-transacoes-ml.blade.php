<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                Importe um CSV com as taxas do Mercado Livre (ex: antecipação) para lançar em lote no <strong>Contas a Pagar</strong>.
            </p>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Formato do CSV:</p>
            <code class="block text-xs bg-gray-100 dark:bg-gray-900 rounded p-3 text-gray-600 dark:text-gray-400">
                data;valor;descricao<br>
                01/07/2026;25.94;Antecipação ML - fee_release_in_advance<br>
                04/07/2026;22.58;Antecipação ML - fee_release_in_advance
            </code>
            <ul class="mt-3 text-xs text-gray-400 dark:text-gray-500 list-disc list-inside space-y-1">
                <li>Separador: ponto-e-vírgula (<code>;</code>)</li>
                <li>Data: <code>dd/mm/aaaa</code> ou <code>aaaa-mm-dd</code></li>
                <li>Valor: positivo, sem R$ (ex: <code>25.94</code> ou <code>25,94</code>)</li>
                <li>Cabeçalho é ignorado automaticamente</li>
            </ul>
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="processar">Importar Lançamentos</span>
                    <span wire:loading wire:target="processar" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Importando...
                    </span>
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
