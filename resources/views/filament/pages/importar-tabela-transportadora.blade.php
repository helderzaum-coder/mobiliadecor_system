<x-filament-panels::page>
    <div class="max-w-2xl">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                Importe tabelas de frete ou taxas especiais para uma transportadora a partir de uma planilha.
            </p>

            <div class="text-sm text-gray-400 dark:text-gray-500 space-y-2">
                <p><strong>Taxas Especiais</strong> — colunas na ordem:</p>
                <code class="block bg-gray-100 dark:bg-gray-900 p-2 rounded text-xs">
                    tipo_taxa | uf | cidade | cep_inicio | cep_fim | valor_fixo | percentual | observacao
                </code>
                <p class="text-xs">Tipos válidos: TDA, TRT, TAR, TAS, OUTROS</p>

                <p class="mt-3"><strong>Tabela de Frete</strong> — colunas na ordem:</p>
                <code class="block bg-gray-100 dark:bg-gray-900 p-2 rounded text-xs">
                    uf | cep_inicio | cep_fim | regiao | peso_min | peso_max | valor_kg* | valor_fixo* | frete_minimo* | despacho* | pedagio_valor* | pedagio_fracao_kg* | adv_%* | adv_minimo* | gris_%* | gris_minimo*
                </code>
                <p class="text-xs">Obrigatórias: uf, peso_min, peso_max. Demais com * são opcionais. Colunas J-P usam Generalidades como fallback.</p>
            </div>
        </div>

        <div class="flex gap-3 mb-6">
            <a href="{{ asset('storage/modelos/modelo_tabela_frete.xlsx') }}" download
               class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-500 transition">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Modelo Tabela de Frete
            </a>
            <a href="{{ asset('storage/modelos/modelo_taxas_especiais.xlsx') }}" download
               class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-500 transition">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Modelo Taxas Especiais
            </a>
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4 flex items-center gap-3">
                <x-filament::button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="processar">Importar Planilha</span>
                    <span wire:loading wire:target="processar">Processando...</span>
                </x-filament::button>
                <span wire:loading wire:target="processar" class="text-sm text-gray-400">
                    Aguarde, isso pode levar alguns minutos para planilhas grandes...
                </span>
            </div>
        </form>
    </div>
</x-filament-panels::page>
