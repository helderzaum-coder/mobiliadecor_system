<x-filament-panels::page>
    <div class="mb-4 grid grid-cols-3 gap-4">
        <x-filament::section>
            <x-slot name="heading">Data Recebimento</x-slot>
            {{ $this->record->data_recebimento->format('d/m/Y') }}
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Quantidade</x-slot>
            {{ $this->record->quantidade_contas }} pedido(s)
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Valor Total</x-slot>
            R$ {{ number_format($this->record->valor_total, 2, ',', '.') }}
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
