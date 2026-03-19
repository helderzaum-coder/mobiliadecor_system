<x-filament-panels::page>
    <div class="max-w-4xl space-y-6">

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
            <form wire:submit="executar">
                {{ $this->form }}
                <div class="mt-5 flex items-center gap-3">
                    <x-filament::button type="submit" wire:loading.attr="disabled" color="warning">
                        <span wire:loading.remove wire:target="executar">Executar Troca</span>
                        <span wire:loading wire:target="executar">Processando...</span>
                    </x-filament::button>
                    @if($this->executado)
                        <x-filament::button type="button" wire:click="limpar" color="gray">
                            Nova Troca
                        </x-filament::button>
                    @endif
                </div>
            </form>
        </div>

        @if($this->executado && !empty($this->resultado['movimentacoes']))
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Movimentações Realizadas
                        @if($this->resultado['success'])
                            <span class="ml-2 text-xs bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 px-2 py-0.5 rounded">OK</span>
                        @else
                            <span class="ml-2 text-xs bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300 px-2 py-0.5 rounded">COM ERROS</span>
                        @endif
                    </h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Ação</th>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">SKU</th>
                            <th class="text-left px-4 py-2 text-gray-600 dark:text-gray-300">Produto</th>
                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Qtd</th>
                            <th class="text-center px-4 py-2 text-gray-600 dark:text-gray-300">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($this->resultado['movimentacoes'] as $mov)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-2">
                                    @if($mov['acao'] === 'SAÍDA')
                                        <span class="text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300 px-2 py-0.5 rounded">SAÍDA</span>
                                    @else
                                        <span class="text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 px-2 py-0.5 rounded">ENTRADA</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-200">{{ $mov['sku'] }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $mov['nome'] }}</td>
                                <td class="px-4 py-2 text-center font-bold {{ $mov['qtd'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ $mov['qtd'] > 0 ? '+' : '' }}{{ $mov['qtd'] }}
                                </td>
                                <td class="px-4 py-2 text-center">
                                    @if($mov['ok'])
                                        <span class="text-green-500">✓</span>
                                    @else
                                        <span class="text-red-500">✗</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(!empty($this->resultado['erros']))
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-xl p-4">
                    <p class="text-sm font-medium text-red-800 dark:text-red-300 mb-2">Erros:</p>
                    @foreach($this->resultado['erros'] as $erro)
                        <p class="text-xs text-red-700 dark:text-red-400">• {{ $erro }}</p>
                    @endforeach
                </div>
            @endif
        @endif

        {{-- Tabela de configuração --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wide">Configuração de Produtos / Tampos</p>
            </div>
            @php $configs = \App\Models\TrocaTampoConfig::orderBy('grupo')->orderBy('cor')->orderBy('tipo_tampo')->get(); @endphp
            @if($configs->isEmpty())
                <p class="text-sm text-gray-400">Nenhuma configuração cadastrada. Cadastre os produtos e tampos para começar.</p>
            @else
                <div style="max-height:400px;overflow-y:auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-gray-600 text-gray-400">
                                <th class="text-left p-1">Grupo</th>
                                <th class="text-left p-1">Cor</th>
                                <th class="text-left p-1">Tipo Tampo</th>
                                <th class="text-left p-1">SKU Produto</th>
                                <th class="text-left p-1">Produto</th>
                                <th class="text-left p-1">SKU Tampo</th>
                                <th class="text-left p-1">Tampo</th>
                                <th class="text-left p-1">Cor Tampo</th>
                                <th class="text-left p-1">Família</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($configs as $c)
                                <tr class="border-b border-gray-700 hover:bg-gray-700/30">
                                    <td class="p-1 font-medium text-gray-200">{{ $c->grupo }}</td>
                                    <td class="p-1 text-gray-300">{{ $c->cor }}</td>
                                    <td class="p-1">
                                        <span class="text-xs px-1.5 py-0.5 rounded {{ match($c->tipo_tampo) {
                                            '4bocas' => 'bg-blue-900/40 text-blue-300',
                                            '5bocas' => 'bg-purple-900/40 text-purple-300',
                                            'liso' => 'bg-gray-700 text-gray-300',
                                            default => 'bg-gray-700 text-gray-300',
                                        } }}">{{ $c->tipo_tampo }}</span>
                                    </td>
                                    <td class="p-1 font-mono text-gray-300">{{ $c->sku_produto }}</td>
                                    <td class="p-1 text-gray-200">{{ $c->nome_produto }}</td>
                                    <td class="p-1 font-mono text-gray-300">{{ $c->sku_tampo }}</td>
                                    <td class="p-1 text-gray-200">{{ $c->nome_tampo }}</td>
                                    <td class="p-1 text-gray-300">{{ $c->cor_tampo }}</td>
                                    <td class="p-1"><span class="text-xs bg-indigo-900/40 text-indigo-300 px-1.5 py-0.5 rounded">{{ $c->familia_tampo }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
