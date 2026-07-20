<x-filament-panels::page>

    {{-- Listagem de faturas --}}
    @if(!$criando)
    <div class="space-y-4">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div class="flex gap-2">
                @foreach(['aberta' => 'Abertas', 'confirmada' => 'Confirmadas', 'cancelada' => 'Canceladas'] as $val => $label)
                    <button wire:click="$set('filtro_status', '{{ $val }}')"
                        style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;
                            background:{{ $filtro_status === $val ? '#3b82f6' : '#374151' }};
                            color:{{ $filtro_status === $val ? '#fff' : '#9ca3af' }};">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <button wire:click="iniciarCriacao"
                style="background:#10b981;color:#fff;padding:8px 18px;font-size:13px;border-radius:8px;border:none;cursor:pointer;font-weight:600;">
                + Nova Fatura
            </button>
        </div>

        {{-- Lista --}}
        @forelse($this->faturas as $fatura)
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-5">
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-gray-800 dark:text-white text-sm">
                            {{ $fatura->descricao ?: ($fatura->canal?->nome_canal ?? 'Fatura #' . $fatura->id) }}
                        </span>
                        <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;
                            background:{{ $fatura->status === 'aberta' ? '#1d4ed8' : ($fatura->status === 'confirmada' ? '#065f46' : '#374151') }};
                            color:#fff;">
                            {{ ucfirst($fatura->status) }}
                        </span>
                    </div>
                    <div class="flex gap-4 mt-1 text-xs text-gray-500 flex-wrap">
                        <span>📅 Previsto: <strong class="text-gray-300">{{ $fatura->data_prevista->format('d/m/Y') }}</strong></span>
                        <span>📦 {{ $fatura->contas_receber_count }} pedido(s)</span>
                        @if($fatura->canal)
                            <span>🏪 {{ $fatura->canal->nome_canal }}</span>
                        @endif
                        @if($fatura->contaBancaria)
                            <span>🏦 {{ $fatura->contaBancaria->nome }}</span>
                        @endif
                        @if($fatura->lote_recebimento_id)
                            <span>📋 Lote #{{ $fatura->lote_recebimento_id }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xl font-bold text-green-500">R$ {{ number_format($fatura->valor_total, 2, ',', '.') }}</span>
                    @if($fatura->status === 'aberta')
                        <button wire:click="editarFatura({{ $fatura->id }})"
                            style="background:#374151;color:#e5e7eb;padding:5px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;">
                            ✏️ Editar
                        </button>
                        <button wire:click="confirmarFatura({{ $fatura->id }})"
                            wire:confirm="Confirmar recebimento desta fatura de R$ {{ number_format($fatura->valor_total, 2, ',', '.') }}?"
                            style="background:#10b981;color:#fff;padding:5px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;font-weight:600;">
                            ✅ Confirmar
                        </button>
                        <button wire:click="cancelarFatura({{ $fatura->id }})"
                            wire:confirm="Cancelar esta fatura? Os pedidos serão liberados."
                            style="background:#7f1d1d;color:#fca5a5;padding:5px 12px;font-size:11px;border-radius:6px;border:none;cursor:pointer;">
                            ✖ Cancelar
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
            <div class="text-center text-gray-500 py-12">Nenhuma fatura {{ $filtro_status === 'aberta' ? 'aberta' : ($filtro_status === 'confirmada' ? 'confirmada' : 'cancelada') }}.</div>
        @endforelse
    </div>

    {{-- Formulário de criação/edição --}}
    @else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Painel Esquerdo: Dados + Busca --}}
        <div class="space-y-4">

            {{-- Dados da fatura --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-5 space-y-3">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ $editando_id ? '✏️ Editar Fatura' : '📄 Nova Fatura de Recebimento' }}
                </h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Canal</label>
                        <select wire:model.live="form_canal_id"
                            style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
                            <option value="">Todos os canais</option>
                            @foreach($this->canais as $canal)
                                <option value="{{ $canal->id_canal }}">{{ $canal->nome_canal }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Data Prevista</label>
                        <input type="date" wire:model="form_data_prevista"
                            style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Banco</label>
                        <select wire:model="form_conta_bancaria_id"
                            style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
                            <option value="">Selecione...</option>
                            @foreach($this->contasBancarias as $banco)
                                <option value="{{ $banco->id }}">{{ $banco->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 block mb-1">Descrição (opcional)</label>
                        <input type="text" wire:model="form_descricao" placeholder="Ex: Repasse Webcontinental Jul"
                            style="width:100%;padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
                    </div>
                </div>
            </div>

            {{-- Busca de pedidos --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">🔍 Buscar Pedido</h3>
                <input type="text" wire:model.live.debounce.300ms="busca" placeholder="Digite o número do pedido..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-white px-4 py-2">

                @if($this->resultadosBusca->isNotEmpty())
                <div class="mt-3 space-y-2">
                    @foreach($this->resultadosBusca as $conta)
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-sm">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-xs font-semibold text-gray-800 dark:text-gray-100">{{ $conta->venda?->numero_pedido_canal }}</span>
                                @if($conta->total_parcelas > 1)
                                    <span style="background:#7c3aed;color:#fff;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:700;">{{ $conta->numero_parcela }}/{{ $conta->total_parcelas }}</span>
                                @endif
                                <span class="text-gray-400">·</span>
                                <span class="text-xs text-gray-500">{{ $conta->forma_pagamento }}</span>
                                <span class="font-semibold text-green-500">R$ {{ number_format($conta->valor_parcela, 2, ',', '.') }}</span>
                            </div>
                            <button wire:click="adicionarConta({{ $conta->id_conta_receber }})"
                                style="background:#10b981;color:#fff;padding:4px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;font-weight:600;">
                                + Add
                            </button>
                        </div>
                    @endforeach
                </div>
                @elseif(strlen($busca) >= 3)
                    <p class="mt-2 text-xs text-gray-500">Nenhum pedido pendente encontrado.</p>
                @endif
            </div>
        </div>

        {{-- Painel Direito: Pedidos selecionados + Ajustes --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">
                📋 Pedidos na Fatura ({{ count($contas_selecionadas) }})
            </h3>

            @if(empty($contas_selecionadas))
                <p class="text-center text-gray-500 py-6 text-sm">Nenhum pedido adicionado.</p>
            @else
                <div class="space-y-2 max-h-64 overflow-y-auto mb-4">
                    @foreach($this->contasSelecionadasItens as $conta)
                        <div class="flex items-center justify-between p-2 rounded-lg bg-gray-100 dark:bg-gray-900 border border-gray-700 text-sm">
                            <div>
                                <span class="font-mono text-xs font-semibold text-gray-100">{{ $conta->venda?->numero_pedido_canal }}</span>
                                @if($conta->total_parcelas > 1)
                                    <span style="background:#7c3aed;color:#fff;padding:1px 5px;border-radius:4px;font-size:10px;margin-left:4px;">{{ $conta->numero_parcela }}/{{ $conta->total_parcelas }}</span>
                                @endif
                                <span class="text-gray-400 mx-1">·</span>
                                <span class="text-xs text-gray-400">{{ $conta->venda?->data_venda?->format('d/m') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-green-500 font-semibold text-xs">R$ {{ number_format($conta->valor_parcela, 2, ',', '.') }}</span>
                                <button wire:click="removerConta({{ $conta->id_conta_receber }})"
                                    style="color:#ef4444;font-size:14px;cursor:pointer;background:none;border:none;">✖</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Descontos --}}
            <div class="mb-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Descontos / Abatimentos</h4>
                @foreach($descontos as $index => $desconto)
                    <div class="flex items-center justify-between p-2 rounded-lg {{ !empty($desconto['conta_pagar_id']) ? 'bg-orange-900/20 border-orange-800' : 'bg-red-900/20 border-red-800' }} border text-sm mb-2">
                        <span class="{{ !empty($desconto['conta_pagar_id']) ? 'text-orange-300' : 'text-red-300' }}">{{ $desconto['descricao'] }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-red-400">- R$ {{ number_format($desconto['valor'], 2, ',', '.') }}</span>
                            <button wire:click="removerDesconto({{ $index }})" style="color:#ef4444;background:none;border:none;cursor:pointer;">✖</button>
                        </div>
                    </div>
                @endforeach
                <div class="flex gap-2 mb-2">
                    <input type="text" wire:model="busca_reembolso" placeholder="Nº pedido com reembolso..."
                        wire:keydown.enter="buscarReembolso"
                        style="flex:1;padding:7px 10px;border-radius:7px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                    <button wire:click="buscarReembolso"
                        style="padding:7px 12px;border-radius:7px;border:none;cursor:pointer;background:#f59e0b;color:#fff;font-size:11px;font-weight:600;">
                        🔄 Reembolso
                    </button>
                </div>
                <div class="flex gap-2">
                    <input type="text" wire:model="desconto_descricao" placeholder="Descrição"
                        style="flex:1;padding:7px 10px;border-radius:7px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                    <input type="number" wire:model="desconto_valor" placeholder="Valor" step="0.01" min="0"
                        style="width:100px;padding:7px 10px;border-radius:7px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                    <button wire:click="adicionarDesconto"
                        style="padding:7px 12px;border-radius:7px;border:none;cursor:pointer;background:#ef4444;color:#fff;font-size:11px;font-weight:600;">
                        + Desc.
                    </button>
                </div>
            </div>

            {{-- Entradas Avulsas --}}
            <div class="mb-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Entradas Avulsas</h4>
                @foreach($entradas_avulsas as $index => $entrada)
                    <div class="flex items-center justify-between p-2 rounded-lg bg-green-900/20 border border-green-800 text-sm mb-2">
                        <span class="text-green-300">{{ $entrada['descricao'] }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-green-400">+ R$ {{ number_format($entrada['valor'], 2, ',', '.') }}</span>
                            <button wire:click="removerEntradaAvulsa({{ $index }})" style="color:#ef4444;background:none;border:none;cursor:pointer;">✖</button>
                        </div>
                    </div>
                @endforeach
                <div class="flex gap-2">
                    <input type="text" wire:model="entrada_descricao" placeholder="Descrição"
                        style="flex:1;padding:7px 10px;border-radius:7px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                    <select wire:model="entrada_canal"
                        style="width:130px;padding:7px 10px;border-radius:7px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                        <option value="">Canal...</option>
                        @foreach($this->canais as $c)
                            <option value="{{ $c->nome_canal }}">{{ $c->nome_canal }}</option>
                        @endforeach
                        <option value="Entrada Avulsa">Avulsa</option>
                    </select>
                    <input type="number" wire:model="entrada_valor" placeholder="Valor" step="0.01" min="0"
                        style="width:100px;padding:7px 10px;border-radius:7px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                    <button wire:click="adicionarEntradaAvulsa"
                        style="padding:7px 12px;border-radius:7px;border:none;cursor:pointer;background:#10b981;color:#fff;font-size:11px;font-weight:600;">
                        + Ent.
                    </button>
                </div>
            </div>

            {{-- Totais --}}
            <div class="space-y-1 mb-4 pt-3 border-t border-gray-700">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Pedidos:</span>
                    <span class="text-green-400 font-semibold">R$ {{ number_format($this->totalContas, 2, ',', '.') }}</span>
                </div>
                @if($this->totalEntradasAvulsas > 0)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Entradas avulsas:</span>
                    <span class="text-green-400">+ R$ {{ number_format($this->totalEntradasAvulsas, 2, ',', '.') }}</span>
                </div>
                @endif
                @if($this->totalDescontos > 0)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Descontos:</span>
                    <span class="text-red-400">- R$ {{ number_format($this->totalDescontos, 2, ',', '.') }}</span>
                </div>
                @endif
                <div class="flex justify-between pt-2 border-t border-gray-700">
                    <span class="text-gray-300 font-semibold">Total Fatura:</span>
                    <span class="text-xl font-bold text-blue-400">R$ {{ number_format($this->totalFatura, 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- Botões --}}
            <div class="flex gap-3">
                <button wire:click="cancelarFormulario"
                    style="flex:1;padding:10px;font-size:13px;border-radius:8px;border:1px solid #374151;cursor:pointer;background:transparent;color:#9ca3af;">
                    Cancelar
                </button>
                <button wire:click="salvarFatura"
                    style="flex:2;padding:10px;font-size:13px;font-weight:700;border-radius:8px;border:none;cursor:pointer;background:#3b82f6;color:#fff;">
                    💾 Salvar Fatura
                </button>
            </div>
        </div>
    </div>
    @endif

</x-filament-panels::page>
