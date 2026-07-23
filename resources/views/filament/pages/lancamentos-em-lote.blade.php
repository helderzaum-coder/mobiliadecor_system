<x-filament-panels::page>
    <div class="space-y-6">

        <div class="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-xl p-4 text-sm text-blue-700 dark:text-blue-300">
            <strong>Como usar:</strong> Selecione o tipo (entrada ou saída), banco e categoria.
            Cole as colunas de data, descrição e valor diretamente do Excel — uma linha por lançamento.
            Use <strong>Visualizar</strong> para conferir antes de importar.
        </div>

        <form wire:submit="processar">
            {{ $this->form }}

            <div class="mt-4 flex gap-3">
                <x-filament::button type="button" wire:click="visualizar" color="gray">
                    Visualizar
                </x-filament::button>

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

        @if (!empty($this->preview))
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <span class="font-medium text-sm text-gray-700 dark:text-gray-300">
                        Preview — {{ count($this->preview) }} linha(s)
                    </span>
                    <span class="text-xs text-gray-400">
                        {{ collect($this->preview)->where('valor', '>', 0)->whereNotNull('data')->count() }} válidas
                        / {{ collect($this->preview)->filter(fn($l) => !$l['data'] || $l['valor'] <= 0)->count() }} com problema
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-xs text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">#</th>
                                <th class="px-4 py-2 text-left">Data</th>
                                <th class="px-4 py-2 text-left">Descrição</th>
                                <th class="px-4 py-2 text-right">Valor</th>
                                <th class="px-4 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($this->preview as $linha)
                                @php $ok = $linha['data'] && $linha['valor'] > 0; @endphp
                                <tr class="{{ $ok ? '' : 'bg-red-50 dark:bg-red-950' }}">
                                    <td class="px-4 py-2 text-gray-400">{{ $linha['linha'] }}</td>
                                    <td class="px-4 py-2 {{ $linha['data'] ? '' : 'text-red-500' }}">
                                        {{ $linha['data'] ? \Carbon\Carbon::parse($linha['data'])->format('d/m/Y') : '⚠ ' . $linha['data_raw'] }}
                                    </td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $linha['descricao'] }}</td>
                                    <td class="px-4 py-2 text-right {{ $ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                        {{ $linha['valor'] > 0 ? 'R$ ' . number_format($linha['valor'], 2, ',', '.') : '⚠ ' . $linha['valor_raw'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($ok)
                                            <span class="text-green-600 text-xs">✓ OK</span>
                                        @else
                                            <span class="text-red-500 text-xs">✗ Ignorar</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
