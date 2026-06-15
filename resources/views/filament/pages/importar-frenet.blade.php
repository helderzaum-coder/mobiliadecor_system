<x-filament-panels::page>
    @php $totais = $this->totais; @endphp

    {{-- Resumo --}}
    <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:120px;background:var(--kpi-bg,#1f2937);border-radius:10px;padding:10px 16px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:var(--kpi-text,#f9fafb);">{{ $totais['total'] }}</div>
            <div style="font-size:10px;color:#9ca3af;">Total Fretes</div>
        </div>
        <div style="flex:1;min-width:120px;background:var(--kpi-bg,#1f2937);border-radius:10px;padding:10px 16px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:#10b981;">{{ $totais['utilizados'] }}</div>
            <div style="font-size:10px;color:#9ca3af;">Vinculados</div>
        </div>
        <div style="flex:1;min-width:120px;background:var(--kpi-bg,#1f2937);border-radius:10px;padding:10px 16px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:#f59e0b;">{{ $totais['nao_utilizados'] }}</div>
            <div style="font-size:10px;color:#9ca3af;">Pendentes</div>
        </div>
        <div style="flex:1;min-width:120px;background:var(--kpi-bg,#1f2937);border-radius:10px;padding:10px 16px;text-align:center;">
            <div style="font-size:20px;font-weight:800;color:#f59e0b;">R$ {{ number_format($totais['valor_nao_utilizado'], 2, ',', '.') }}</div>
            <div style="font-size:10px;color:#9ca3af;">Valor Pendente</div>
        </div>
    </div>

    {{-- Upload CSV --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-4"
         x-data="{ dragging: false }"
         @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         @drop.prevent="
            dragging = false;
            const file = $event.dataTransfer.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => $wire.importarCsv(e.target.result);
            reader.readAsText(file, 'UTF-8');
         ">
        <div style="font-size:13px;font-weight:600;color:#f9fafb;margin-bottom:8px;">📥 Importar CSV da Frenet</div>
        <div :style="dragging ? 'border-color:#3b82f6;background:rgba(59,130,246,.08);' : ''"
             style="border:2px dashed #4b5563;border-radius:8px;padding:20px;text-align:center;transition:all .2s;">
            <div style="font-size:12px;color:#9ca3af;margin-bottom:8px;">Arraste o CSV aqui ou clique para selecionar</div>
            <input type="file" accept=".csv,.txt,.tsv" style="display:none;" id="frenetCsvInput"
                onchange="
                    const file = this.files[0];
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = e => @this.importarCsv(e.target.result);
                    reader.readAsText(file, 'UTF-8');
                    this.value = '';
                ">
            <label for="frenetCsvInput"
                style="background:#2563eb;color:#fff;padding:6px 16px;font-size:12px;border-radius:6px;cursor:pointer;display:inline-block;">
                Selecionar arquivo
            </label>
        </div>
        <div style="font-size:10px;color:#6b7280;margin-top:6px;">
            Formato esperado: <code>ID | Data | Etiqueta | Destinatario | Cidade/UF | Modalidade | Preco | Status</code> (separado por tab ou ponto-e-vírgula)
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 mb-4 space-y-3">
        <div class="flex gap-2 flex-wrap">
            <button wire:click="$set('filtro', 'nao_utilizados')"
                style="{{ $filtro === 'nao_utilizados' ? 'background:#d97706;color:#fff;' : 'background:#374151;color:#d1d5db;' }}padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                Pendentes
            </button>
            <button wire:click="$set('filtro', 'utilizados')"
                style="{{ $filtro === 'utilizados' ? 'background:#059669;color:#fff;' : 'background:#374151;color:#d1d5db;' }}padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                Vinculados
            </button>
            <button wire:click="$set('filtro', 'todos')"
                style="{{ $filtro === 'todos' ? 'background:#2563eb;color:#fff;' : 'background:#374151;color:#d1d5db;' }}padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                Todos
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Buscar (destinatário, ID, cidade, modalidade)</label>
                <input type="text" wire:model.live.debounce.500ms="busca" placeholder="Buscar..."
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Período</label>
                <select wire:model.live="periodo"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
                    <option value="">Todos</option>
                    <option value="hoje">Hoje</option>
                    <option value="esta_semana">Esta semana</option>
                    <option value="este_mes">Este mês</option>
                    <option value="mes_passado">Mês passado</option>
                    <option value="customizado">Customizado</option>
                </select>
            </div>
            @if($periodo === 'customizado')
            <div class="flex gap-2">
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">De</label>
                    <input type="date" wire:model.live="data_inicio"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Até</label>
                    <input type="date" wire:model.live="data_fim"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-gray-800 dark:text-white">
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="text-xs text-gray-500 mb-2">{{ $this->fretes->count() }} frete(s) encontrado(s)</div>

    {{-- Tabela --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b border-gray-300 dark:border-gray-600 text-gray-500 text-xs">
                    <th class="text-left p-2">ID Frenet</th>
                    <th class="text-left p-2">Data</th>
                    <th class="text-left p-2">Destinatário</th>
                    <th class="text-left p-2">Cidade/UF</th>
                    <th class="text-left p-2">Modalidade</th>
                    <th class="text-right p-2">Valor</th>
                    <th class="text-left p-2">Status Envio</th>
                    <th class="text-center p-2">Tipo</th>
                    <th class="text-center p-2">Situação</th>
                    <th class="text-center p-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->fretes as $frete)
                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="p-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $frete->frenet_id }}</td>
                        <td class="p-2 text-xs text-gray-500">{{ $frete->data_envio?->format('d/m/Y') }}</td>
                        <td class="p-2 text-gray-800 dark:text-white font-medium">{{ $frete->destinatario }}</td>
                        <td class="p-2 text-xs text-gray-500">{{ $frete->cidade_uf }}</td>
                        <td class="p-2 text-xs text-gray-500">{{ $frete->modalidade }}</td>
                        <td class="p-2 text-right font-semibold text-gray-800 dark:text-white">R$ {{ number_format($frete->valor_frete, 2, ',', '.') }}</td>
                        <td class="p-2 text-xs text-gray-500">{{ $frete->status }}</td>
                        <td class="p-2 text-center">
                            <select wire:change="alterarTipo({{ $frete->id }}, $event.target.value)"
                                class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs px-2 py-1 text-gray-800 dark:text-white"
                                style="font-size:11px;">
                                <option value="entrega" {{ ($frete->tipo ?? 'entrega') === 'entrega' ? 'selected' : '' }}>✅ Entrega</option>
                                <option value="assistencia" {{ ($frete->tipo ?? '') === 'assistencia' ? 'selected' : '' }}>🔧 Assistência</option>
                                <option value="devolucao" {{ ($frete->tipo ?? '') === 'devolucao' ? 'selected' : '' }}>↩️ Devolução</option>
                            </select>
                        </td>
                        <td class="p-2 text-center">
                            @if($frete->utilizado)
                                <span style="background:#059669;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">Vinculado</span>
                            @else
                                <span style="background:#d97706;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">Pendente</span>
                            @endif
                        </td>
                        <td class="p-2 text-center">
                            @if(!$frete->utilizado)
                                <div class="flex items-center gap-1 justify-center" x-data="{ open: false, pedido: '' }">
                                    <button wire:click="vincularAuto({{ $frete->id }})"
                                        style="background:#2563eb;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;"
                                        title="Vincular automaticamente pelo nome do destinatário">
                                        Auto
                                    </button>
                                    <button @click="open = !open"
                                        style="background:#7c3aed;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;"
                                        title="Vincular por número do pedido">
                                        Pedido
                                    </button>
                                    <div x-show="open" x-cloak class="flex items-center gap-1">
                                        <input type="text" x-model="pedido" placeholder="Nº pedido"
                                            class="rounded border border-gray-400 dark:border-gray-600 bg-white dark:bg-gray-900 text-xs px-2 py-1 w-32 text-gray-800 dark:text-white">
                                        <button @click="$wire.buscarPedidoParaVincular({{ $frete->id }}, pedido); open = false"
                                            style="background:#059669;color:#fff;padding:2px 8px;font-size:10px;border-radius:4px;border:none;cursor:pointer;">
                                            OK
                                        </button>
                                    </div>
                                </div>
                            @else
                                @php
                                    $vendaVinculada = $frete->venda_id
                                        ? \App\Models\Venda::find($frete->venda_id)
                                        : null;
                                @endphp
                                @if($vendaVinculada)
                                    <span style="font-size:10px;color:#9ca3af;">Pedido #{{ $vendaVinculada->numero_pedido_canal }}</span>
                                    <button wire:click="desvincular({{ $frete->id }})" wire:confirm="Desvincular este frete do pedido #{{ $vendaVinculada->numero_pedido_canal }}?"
                                        style="background:#dc2626;color:#fff;padding:2px 6px;font-size:9px;border-radius:3px;border:none;cursor:pointer;margin-left:4px;"
                                        title="Desvincular para vincular ao pedido correto">
                                        ✖
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="p-4 text-center text-gray-500">Nenhum frete encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal de Confirmação --}}
    @if($modalAberto)
    <div style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;display:flex;align-items:center;justify-content:center;" wire:click.self="fecharModal">
        <div style="background:#1f2937;border-radius:12px;padding:24px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4);">

            @if($modalVendaDados)
                {{-- Dados encontrados - confirmar --}}
                <h3 style="font-size:16px;font-weight:700;color:#f9fafb;margin-bottom:16px;">✅ Confirmar Vinculação</h3>

                <div style="background:#111827;border-radius:8px;padding:12px;margin-bottom:12px;">
                    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;margin-bottom:6px;">Frete Frenet</div>
                    <div style="font-size:13px;color:#f9fafb;">{{ $modalVendaDados['frete_destinatario'] }} — <b>R$ {{ $modalVendaDados['frete_valor'] }}</b></div>
                    <div style="font-size:12px;color:#9ca3af;">{{ $modalVendaDados['frete_modalidade'] }}</div>
                </div>

                <div style="background:#111827;border-radius:8px;padding:12px;margin-bottom:16px;">
                    <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;margin-bottom:6px;">Pedido</div>
                    <div style="font-size:13px;color:#f9fafb;"><b>#{{ $modalVendaDados['numero_pedido_canal'] }}</b></div>
                    <div style="font-size:12px;color:#d1d5db;margin-top:4px;">Cliente: {{ $modalVendaDados['cliente_nome'] }}</div>
                    <div style="font-size:12px;color:#d1d5db;">Canal: {{ $modalVendaDados['canal'] }}</div>
                    <div style="font-size:12px;color:#d1d5db;">NF-e: {{ $modalVendaDados['nota_fiscal'] }}</div>
                    <div style="font-size:12px;color:#d1d5db;">Total: R$ {{ $modalVendaDados['valor_total'] }}</div>
                    <div style="font-size:12px;color:#d1d5db;">Data: {{ $modalVendaDados['data_venda'] }}</div>
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button wire:click="fecharModal"
                        style="background:#374151;color:#d1d5db;padding:8px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                        Cancelar
                    </button>
                    <button wire:click="confirmarVinculacao"
                        style="background:#059669;color:#fff;padding:8px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;font-weight:600;">
                        Confirmar Vinculação
                    </button>
                </div>

            @else
                {{-- Precisa informar o pedido (assistência/devolução) --}}
                <h3 style="font-size:16px;font-weight:700;color:#f9fafb;margin-bottom:8px;">🔗 Vincular a um Pedido</h3>
                <p style="font-size:12px;color:#9ca3af;margin-bottom:16px;">
                    Este frete foi marcado como <b style="color:#f59e0b;">{{ match($modalTipoPendente) { 'assistencia' => 'Assistência', 'devolucao' => 'Devolução', default => $modalTipoPendente } }}</b>. Informe o número do pedido relacionado.
                </p>

                <div x-data="{ pedido: '' }" style="display:flex;gap:8px;margin-bottom:16px;">
                    <input type="text" x-model="pedido" placeholder="Nº do pedido (canal ou interno)"
                        class="flex-1 rounded-lg border border-gray-600 bg-gray-900 text-sm px-3 py-2 text-white">
                    <button @click="$wire.buscarPedidoModal(pedido)"
                        style="background:#2563eb;color:#fff;padding:8px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                        Buscar
                    </button>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <button wire:click="fecharModal"
                        style="background:#374151;color:#d1d5db;padding:8px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;">
                        Cancelar
                    </button>
                </div>
            @endif

        </div>
    </div>
    @endif
</x-filament-panels::page>
