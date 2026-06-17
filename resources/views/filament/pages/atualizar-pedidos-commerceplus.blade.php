<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Progress Steps --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between text-sm">
                @foreach([1 => 'Lançar NF-e', 2 => 'Importar Planilha CP', 3 => 'Vincular & Transportadora', 4 => 'Gerar Planilha Final'] as $step => $label)
                    <div class="flex items-center gap-2 {{ $etapaAtual >= $step ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400' }}">
                        <span class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold {{ $etapaAtual >= $step ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }}">
                            {{ $step }}
                        </span>
                        <span class="hidden sm:inline">{{ $label }}</span>
                    </div>
                    @if($step < 4)
                        <div class="flex-1 h-px mx-2 {{ $etapaAtual > $step ? 'bg-primary-400' : 'bg-gray-200 dark:bg-gray-700' }}"></div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Conta Bling --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Conta Bling:</label>
                <select wire:model.live="blingAccount" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                    <option value="primary">Mobilia Decor</option>
                    <option value="secondary">HES Móveis</option>
                </select>
                @if($etapaAtual > 1)
                    <x-filament::button size="sm" color="gray" wire:click="voltarEtapa">
                        ← Voltar
                    </x-filament::button>
                @endif
            </div>
        </div>

        {{-- ETAPA 1: Lançar NF-e --}}
        @if($etapaAtual === 1)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Etapa 1 — Lançar NF-e do Lote de Envio</h3>
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-4 text-sm text-blue-800 dark:text-blue-200">
                    <strong>📋 Tutorial:</strong> Cole abaixo os números das NF-e que foram coletadas pela transportadora neste lote.
                    O sistema vai buscar no Bling os dados da nota (chave, série, transportadora).
                </div>

                <textarea
                    wire:model="numerosNfe"
                    rows="6"
                    placeholder="056528&#10;056529&#10;056530"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm font-mono"
                ></textarea>

                <div class="mt-4">
                    <x-filament::button wire:click="salvarNfes" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="salvarNfes">Buscar NF-e no Bling →</span>
                        <span wire:loading wire:target="salvarNfes">Buscando...</span>
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- ETAPA 2: Importar Planilha CP --}}
        @if($etapaAtual === 2)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">Etapa 2 — Importar Planilha do CommercePlus</h3>
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-4 text-sm text-blue-800 dark:text-blue-200">
                    <strong>📋 Tutorial:</strong> Baixe a planilha do CommercePlus em:<br>
                    <a href="https://commerceplus.com.br/control#pedido-all=like-;order-dt%20desc;limit-0,25;filter-(canal:)|(marketplace_idmarketplace:)|(configmarketplace_idconfigmarketplace:)|(mlconta_idmlconta:)|(admin_idadmin:)|(idstatusfinanceiro:3)|(idsituacaoentrega:1)|(statusgeral:)|(etiquetamelhorenviogerada:)|(tiderp:)|(mlfull:)|(statusetiquetameli:)|(statuscheckout:)|(nfpendente:)|(tipo:)|(uf:)|(mldtenvio:)|(itemsemvinculo:)|(atacado:);page-1"
                       target="_blank" class="text-blue-600 dark:text-blue-300 underline break-all">
                        CommercePlus → Pedidos (financeiro: pago, entrega: pendente)
                    </a><br>
                    Faça o download como XLS e importe aqui.
                </div>

                {{-- Resumo NF-e da etapa anterior --}}
                @if(!empty($nfesLancadas))
                    <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200 mb-1">✓ {{ count($nfesLancadas) }} NF-e carregadas:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($nfesLancadas as $nfe)
                                <span class="text-xs px-2 py-0.5 rounded {{ $nfe['encontrada'] ? 'bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-200' : 'bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-200' }}">
                                    {{ $nfe['numero'] }}{{ $nfe['transportadora'] ? ' ('.$nfe['transportadora'].')' : '' }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mb-4">
                    <input type="file" wire:model="planilhaCp" accept=".xls,.xlsx,.csv" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900/40 dark:file:text-primary-300">
                    <div wire:loading wire:target="planilhaCp" class="text-xs text-gray-500 mt-1">Carregando arquivo...</div>
                </div>

                <x-filament::button wire:click="importarPlanilhaCp" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="importarPlanilhaCp">Importar e Vincular →</span>
                    <span wire:loading wire:target="importarPlanilhaCp">Processando...</span>
                </x-filament::button>
            </div>
        @endif

        {{-- ETAPA 3: Vinculação --}}
        @if($etapaAtual === 3)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Etapa 3 — Vincular Pedido CP a cada NF-e</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Para cada NF-e lançada, o sistema buscou o pedido CP correspondente. Corrija manualmente se necessário.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="text-left px-3 py-2 text-gray-600 dark:text-gray-300">NF-e</th>
                                <th class="text-left px-3 py-2 text-gray-600 dark:text-gray-300">Pedido CP</th>
                                <th class="text-left px-3 py-2 text-gray-600 dark:text-gray-300">Transportadora</th>
                                <th class="text-left px-3 py-2 text-gray-600 dark:text-gray-300">URL Rastreio</th>
                                <th class="text-left px-3 py-2 text-gray-600 dark:text-gray-300">Cód. Rastreio</th>
                                <th class="text-center px-3 py-2 text-gray-600 dark:text-gray-300">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($vinculacoes as $idx => $vinc)
                                <tr class="{{ $vinc['vinculado'] ? '' : 'bg-yellow-50 dark:bg-yellow-900/10' }}">
                                    <td class="px-3 py-2 font-mono text-gray-700 dark:text-gray-200">{{ $vinc['numero_nfe'] }}</td>
                                    <td class="px-3 py-2">
                                        <input type="text" wire:model.blur="vinculacoes.{{ $idx }}.id_pedido_cp"
                                            class="w-32 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                            placeholder="ID pedido CP">
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $vinc['transportadora'] }}</td>
                                    <td class="px-3 py-2">
                                        <input type="text" wire:model.blur="vinculacoes.{{ $idx }}.url_rastreio"
                                            class="w-44 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white {{ !empty($vinc['url_rastreio']) ? 'bg-green-50 dark:bg-green-900/20' : '' }}"
                                            placeholder="{{ !empty($vinc['url_rastreio']) ? '' : 'Informar URL' }}">
                                    </td>
                                    <td class="px-3 py-2">
                                        <input type="text" wire:model.blur="vinculacoes.{{ $idx }}.codigo_rastreio"
                                            class="w-28 text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if($vinc['vinculado'])
                                            <span class="text-xs bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 px-2 py-0.5 rounded">✓</span>
                                        @else
                                            <span class="text-xs bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300 px-2 py-0.5 rounded">Manual</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button wire:click="gerarPlanilha">
                        Gerar Planilha Final →
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- ETAPA 4: Resultado / Download --}}
        @if($etapaAtual === 4)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Etapa 4 — Planilha Pronta</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Confira os dados abaixo e faça o download. Importe o arquivo no CommercePlus em:<br>
                        <strong>Pedidos → Importar → Situação dos Pedidos</strong>
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="text-left px-3 py-2">ID Pedido CP</th>
                                <th class="text-left px-3 py-2">Situação</th>
                                <th class="text-left px-3 py-2">Cód. Rastreio</th>
                                <th class="text-left px-3 py-2">Nº NF-e</th>
                                <th class="text-left px-3 py-2">Série</th>
                                <th class="text-left px-3 py-2">Chave NF-e</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($planilhaFinal as $row)
                                <tr>
                                    <td class="px-3 py-1.5 font-mono">{{ $row['id_pedido_cp'] }}</td>
                                    <td class="px-3 py-1.5">{{ $row['situacao'] }}</td>
                                    <td class="px-3 py-1.5 font-mono">{{ $row['codigo_rastreio'] ?: '-' }}</td>
                                    <td class="px-3 py-1.5 font-mono">{{ $row['numero_nfe'] }}</td>
                                    <td class="px-3 py-1.5">{{ $row['serie_nfe'] }}</td>
                                    <td class="px-3 py-1.5 font-mono text-[10px]">{{ $row['chave_nfe'] ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button wire:click="downloadPlanilha" color="success">
                        📥 Baixar Planilha (.xls)
                    </x-filament::button>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
