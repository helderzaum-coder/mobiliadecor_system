<x-filament-panels::page>
    <div class="max-w-5xl space-y-6">

        {{-- Formulário de lançamento --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">Registrar Recebimento</h3>
            <form wire:submit="lancar">
                {{ $this->form }}
                <div class="mt-4">
                    <x-filament::button type="submit" wire:loading.attr="disabled" color="success">
                        <span wire:loading.remove wire:target="lancar">+ Lançar Carcaças</span>
                        <span wire:loading wire:target="lancar">Processando...</span>
                    </x-filament::button>
                </div>
            </form>
        </div>

        {{-- Painel de grupos --}}
        <div class="space-y-4">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                Situação atual por Grupo / Cor
            </p>

            @foreach($this->grupos as $grupo)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Cabeçalho do grupo --}}
                    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <span class="font-semibold text-sm text-gray-800 dark:text-gray-100">
                                {{ $grupo['grupo'] }} — {{ $grupo['cor'] }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Total carcaças:</span>
                            <span class="text-sm font-bold {{ $grupo['total_carcacas'] === 0 ? 'text-red-500' : 'text-blue-500' }}">
                                {{ $grupo['total_carcacas'] }}
                            </span>
                        </div>
                    </div>

                    {{-- Itens do grupo --}}
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                <th class="text-left px-4 py-2">SKU</th>
                                <th class="text-left px-4 py-2">Produto</th>
                                <th class="text-center px-4 py-2">Tipo Tampo</th>
                                <th class="text-center px-4 py-2">Físico</th>
                                <th class="text-center px-4 py-2 font-semibold text-blue-500">Carcaças</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($grupo['itens'] as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-4 py-2 font-mono text-gray-600 dark:text-gray-300">{{ $item['sku'] }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $item['nome'] }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-1.5 py-0.5 rounded text-xs
                                            {{ match($item['tipo_tampo']) {
                                                '4bocas' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
                                                '5bocas' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
                                                'liso'   => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
                                                default  => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
                                            } }}">
                                            {{ $item['tipo_tampo'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-center text-blue-500 font-medium">{{ $item['saldo_fisico'] }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if($item['saldo_carcaca'] === null)
                                            <span class="text-gray-400 italic">—</span>
                                        @elseif($item['saldo_carcaca'] > 0)
                                            <span class="font-bold text-blue-500">{{ $item['saldo_carcaca'] }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

    </div>
</x-filament-panels::page>
