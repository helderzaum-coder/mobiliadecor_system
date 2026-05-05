<x-filament-panels::page>
    <form wire:submit="processar">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Processar Planilha de Afiliados
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
        <p>Faça upload do relatório de conversão de afiliados da Shopee (SellerConversionReport .csv).</p>
        <p class="mt-1">O sistema vai somar o valor de "Despesas (R$)" na comissão de cada pedido e recalcular as margens.</p>
        <p class="mt-3">
            <a href="https://seller.shopee.com.br/portal/web-seller-affiliate/conversion_report" target="_blank"
                style="color:#f59e0b;text-decoration:underline;">
                📥 Baixar relatório: Central de Marketing › Afiliados do Vendedor › Conversão de Pedidos
            </a>
        </p>
    </div>
</x-filament-panels::page>
