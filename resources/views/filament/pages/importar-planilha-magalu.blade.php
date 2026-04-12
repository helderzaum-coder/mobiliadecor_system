<x-filament-panels::page>
    <form wire:submit="processar">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Processar Planilha
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p>Faça upload da planilha financeira da Magalu (.xlsx).</p>
        <p class="mt-1">Colunas utilizadas: Número do pedido (E), Serviços marketplace (AB), Tarifa fixa (AF), Descontos à vista (AN/AO), Preço Promocional (AP/AQ), Cupom (AR/AS), Valor líquido (AT).</p>
    </div>
</x-filament-panels::page>
