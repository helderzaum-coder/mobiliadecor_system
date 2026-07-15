<x-filament-panels::page>
    <div class="mb-4 grid grid-cols-5 gap-4">
        <x-filament::section>
            <x-slot name="heading">Data Recebimento</x-slot>
            {{ $this->record->data_recebimento->format('d/m/Y') }}
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Quantidade</x-slot>
            {{ $this->record->quantidade_contas }} pedido(s)
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Valor Líquido</x-slot>
            R$ {{ number_format($this->record->valor_total, 2, ',', '.') }}
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Descontos</x-slot>
            @php $totalDescontos = $this->record->descontos->sum('valor_parcela'); @endphp
            @if($totalDescontos > 0)
                - R$ {{ number_format($totalDescontos, 2, ',', '.') }}
            @else
                -
            @endif
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Banco</x-slot>
            @php $banco = $this->record->contasReceber()->first()?->contaBancaria; @endphp
            {{ $banco?->nome ?? '-' }}
        </x-filament::section>
    </div>

    @if($this->record->descontos->count() > 0)
        <x-filament::section>
            <x-slot name="heading">Descontos / Abatimentos</x-slot>
            <div class="space-y-2">
                @foreach($this->record->descontos as $desconto)
                    <div class="flex justify-between items-center p-2 rounded bg-gray-800/50">
                        <span>{{ $desconto->observacoes }}</span>
                        <span class="text-red-400 font-semibold">- R$ {{ number_format($desconto->valor_parcela, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}

    <div class="mt-4">
        {{ $this->desfazerLoteAction }}
    </div>
</x-filament-panels::page>
