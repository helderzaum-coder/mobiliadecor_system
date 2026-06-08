<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Configuração --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Conta Bling</label>
                    <select wire:model.live="blingAccount" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="primary">Mobilia Decor</option>
                        <option value="secondary">HES Móveis</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nova Situação</label>
                    <select wire:model="situacaoId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="">-- Selecione --</option>
                        @foreach($situacoes as $sit)
                            <option value="{{ $sit['id'] }}">{{ $sit['nome'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-filament::button wire:click="processar" wire:loading.attr="disabled" class="w-full">
                        <span wire:loading.remove wire:target="processar">🚀 Atualizar em Lote</span>
                        <span wire:loading wire:target="processar">Processando...</span>
                    </x-filament::button>
                </div>
            </div>

            @if(empty($situacoes))
                <p class="text-xs text-red-500 mt-2">⚠️ Não foi possível carregar as situações. Verifique se a conta está autorizada.</p>
            @endif
        </div>

        {{-- Números das NF-e --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Números das NF-e (um por linha ou separados por vírgula)</label>
            <textarea
                wire:model="numerosNfe"
                rows="8"
                placeholder="056528&#10;056529&#10;056530"
                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm font-mono"
            ></textarea>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Cole os números das notas fiscais que a transportadora coletou. O sistema vai localizar o pedido correspondente e alterar a situação no Bling.
            </p>
        </div>

        {{-- Resultados --}}
        @if($processado && !empty($resultados))
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Resultado do Processamento</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">NF-e</th>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Pedido</th>
                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Status</th>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Detalhe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($resultados as $r)
                            <tr class="{{ $r['success'] ? '' : 'bg-red-50 dark:bg-red-900/10' }}">
                                <td class="px-4 py-2 font-mono text-gray-700 dark:text-gray-200">{{ $r['nfe'] }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $r['pedido'] }}</td>
                                <td class="px-4 py-2 text-center">
                                    @if($r['success'])
                                        <span class="text-xs bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 px-2 py-0.5 rounded">✓ OK</span>
                                    @else
                                        <span class="text-xs bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300 px-2 py-0.5 rounded">✗ ERRO</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-gray-500 dark:text-gray-400 text-xs">{{ $r['msg'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</x-filament-panels::page>
