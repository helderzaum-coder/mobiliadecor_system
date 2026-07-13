<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    {{-- Totais --}}
    @php $totais = $this->totais; @endphp
    <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap;">
        @if($this->exibir_saldo_anterior)
        <div style="flex:1;min-width:200px;padding:16px;border-radius:12px;background:#1f2937;border-top:4px solid #06b6d4;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Saldo Anterior</div>
            <div style="font-size:24px;font-weight:800;color:#e5e7eb;">R$ {{ number_format($this->saldoAnterior, 2, ',', '.') }}</div>
        </div>
        @endif
        <div style="flex:1;min-width:200px;padding:16px;border-radius:12px;background:#1f2937;border-top:4px solid #10b981;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Entradas</div>
            <div style="font-size:24px;font-weight:800;color:#10b981;">R$ {{ number_format($totais['entradas'], 2, ',', '.') }}</div>
        </div>
        <div style="flex:1;min-width:200px;padding:16px;border-radius:12px;background:#1f2937;border-top:4px solid #ef4444;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Saídas</div>
            <div style="font-size:24px;font-weight:800;color:#ef4444;">R$ {{ number_format($totais['saidas'], 2, ',', '.') }}</div>
        </div>
        <div style="flex:1;min-width:200px;padding:16px;border-radius:12px;background:#1f2937;border-top:4px solid #f59e0b;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">{{ $this->exibir_saldo_anterior ? 'Saldo Final' : 'Resultado' }}</div>
            <div style="font-size:24px;font-weight:800;color:#f59e0b;">R$ {{ number_format($this->exibir_saldo_anterior ? $totais['saldo_final'] : $totais['resultado'], 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Movimentações --}}
    <div class="mt-4 rounded-xl bg-white dark:bg-gray-800 shadow-md overflow-hidden">
        @if($this->visao === 'diaria')
            @forelse($this->movimentacoes as $dia)
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid #374151;background:#111827;">
                            <th colspan="3" style="padding:10px;text-align:left;">
                                <span style="color:#e5e7eb;font-weight:600;">{{ \Carbon\Carbon::parse($dia['data'])->format('d/m/Y') }}</span>
                                <span style="color:#6b7280;font-size:11px;margin-left:8px;">{{ \Carbon\Carbon::parse($dia['data'])->locale('pt_BR')->isoFormat('dddd') }}</span>
                            </th>
                            <th style="padding:10px;text-align:right;color:#10b981;font-size:12px;font-weight:400;">+R$ {{ number_format($dia['entradas'], 2, ',', '.') }}</th>
                            <th style="padding:10px;text-align:right;color:#ef4444;font-size:12px;font-weight:400;">-R$ {{ number_format($dia['saidas'], 2, ',', '.') }}</th>
                            <th style="padding:10px;text-align:right;color:#9ca3af;font-size:12px;font-weight:400;">
                                @if($this->exibir_saldo_anterior)
                                    Saldo: R$ {{ number_format($dia['saldo_inicio_dia'], 2, ',', '.') }}
                                @endif
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dia['itens'] as $item)
                            <tr style="border-bottom:1px solid #1f2937;">
                                <td style="padding:8px 10px;width:30px;text-align:center;">
                                    @if($item['tipo'] === 'entrada')
                                        <span style="color:#10b981;">▲</span>
                                    @else
                                        <span style="color:#ef4444;">▼</span>
                                    @endif
                                </td>
                                <td style="padding:8px 10px;color:#e5e7eb;">{{ $item['descricao'] }}</td>
                                <td style="padding:8px 10px;color:#6b7280;font-size:11px;">{{ $item['categoria'] }}</td>
                                <td style="padding:8px 10px;color:#6b7280;font-size:11px;">{{ $item['banco'] !== '-' ? $item['banco'] : '' }}</td>
                                <td style="padding:8px 10px;text-align:right;font-weight:600;{{ $item['tipo'] === 'entrada' ? 'color:#10b981;' : 'color:#ef4444;' }}">
                                    {{ $item['tipo'] === 'entrada' ? '+' : '-' }}R$ {{ number_format($item['valor'], 2, ',', '.') }}
                                </td>
                                <td style="padding:4px 8px;text-align:right;white-space:nowrap;width:80px;">
                                    @if(!empty($item['id']))
                                        <button
                                            wire:click="abrirEditModal('{{ $item['model'] }}', {{ $item['id'] }})"
                                            style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:14px;padding:2px 4px;"
                                            title="Editar"
                                        >✏️</button>
                                        <button
                                            x-data=""
                                            x-on:click="if(confirm('Excluir esta movimentação?')) $wire.excluirMovimentacao('{{ $item['model'] }}', {{ $item['id'] }})"
                                            style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:14px;padding:2px 4px;"
                                            title="Excluir"
                                        >🗑️</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @empty
                <div style="padding:40px;text-align:center;color:#6b7280;">Nenhuma movimentação no período.</div>
            @endforelse
        @else
            {{-- Visão por Categoria --}}
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:2px solid #374151;background:#111827;">
                        <th style="padding:10px;text-align:left;color:#9ca3af;">Categoria</th>
                        <th style="padding:10px;text-align:center;color:#9ca3af;">Qtd</th>
                        <th style="padding:10px;text-align:right;color:#9ca3af;">Entradas</th>
                        <th style="padding:10px;text-align:right;color:#9ca3af;">Saídas</th>
                        <th style="padding:10px;text-align:right;color:#9ca3af;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->movimentacoes as $cat)
                        <tr style="border-bottom:1px solid #1f2937;">
                            <td style="padding:8px 10px;color:#e5e7eb;font-weight:500;">{{ $cat['categoria'] }}</td>
                            <td style="padding:8px 10px;text-align:center;color:#6b7280;">{{ $cat['qtd'] }}</td>
                            <td style="padding:8px 10px;text-align:right;color:#10b981;">
                                {{ $cat['entradas'] > 0 ? 'R$ ' . number_format($cat['entradas'], 2, ',', '.') : '-' }}
                            </td>
                            <td style="padding:8px 10px;text-align:right;color:#ef4444;">
                                {{ $cat['saidas'] > 0 ? 'R$ ' . number_format($cat['saidas'], 2, ',', '.') : '-' }}
                            </td>
                            <td style="padding:8px 10px;text-align:right;font-weight:600;{{ $cat['saldo'] >= 0 ? 'color:#10b981;' : 'color:#ef4444;' }}">
                                R$ {{ number_format($cat['saldo'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:40px;text-align:center;color:#6b7280;">Nenhuma movimentação no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</x-filament-panels::page>

{{-- Modal Editar Movimentação --}}
@if($this->showEditModal)
<div
    x-data="{ open: @entangle('showEditModal') }"
    x-show="open"
    x-cloak
    style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);"
>
    <div style="background:#1f2937;border-radius:12px;padding:24px;width:100%;max-width:480px;">
        <h3 style="color:#e5e7eb;font-size:16px;font-weight:600;margin-bottom:16px;">Editar Movimentação</h3>

        <div style="display:flex;flex-direction:column;gap:12px;">
            <div>
                <label style="color:#9ca3af;font-size:12px;">Descrição</label>
                <input type="text" wire:model="editDescricao" style="width:100%;padding:8px 12px;border-radius:8px;background:#111827;border:1px solid #374151;color:#e5e7eb;">
            </div>
            <div style="display:flex;gap:12px;">
                <div style="flex:1;">
                    <label style="color:#9ca3af;font-size:12px;">Valor</label>
                    <input type="text" wire:model="editValor" style="width:100%;padding:8px 12px;border-radius:8px;background:#111827;border:1px solid #374151;color:#e5e7eb;">
                </div>
                <div style="flex:1;">
                    <label style="color:#9ca3af;font-size:12px;">Data</label>
                    <input type="date" wire:model="editData" style="width:100%;padding:8px 12px;border-radius:8px;background:#111827;border:1px solid #374151;color:#e5e7eb;">
                </div>
            </div>
            @if($this->editTransferenciaId)
            <div style="display:flex;gap:12px;">
                <div style="flex:1;">
                    <label style="color:#9ca3af;font-size:12px;">Banco Origem (saída)</label>
                    <select wire:model="editContaOrigemId" style="width:100%;padding:8px 12px;border-radius:8px;background:#111827;border:1px solid #374151;color:#e5e7eb;">
                        <option value="">Selecione</option>
                        @foreach(\App\Models\ContaBancaria::where('ativo', true)->orderBy('nome')->get() as $banco)
                            <option value="{{ $banco->id }}">{{ $banco->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="color:#9ca3af;font-size:12px;">Banco Destino (entrada)</label>
                    <select wire:model="editContaDestinoId" style="width:100%;padding:8px 12px;border-radius:8px;background:#111827;border:1px solid #374151;color:#e5e7eb;">
                        <option value="">Selecione</option>
                        @foreach(\App\Models\ContaBancaria::where('ativo', true)->orderBy('nome')->get() as $banco)
                            <option value="{{ $banco->id }}">{{ $banco->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @endif
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:20px;">
            <button wire:click="$set('showEditModal', false)" style="padding:8px 16px;border-radius:8px;background:#374151;color:#e5e7eb;border:none;cursor:pointer;">Cancelar</button>
            <button wire:click="salvarEdicao" style="padding:8px 16px;border-radius:8px;background:#10b981;color:#fff;border:none;cursor:pointer;font-weight:600;">Salvar</button>
        </div>
    </div>
</div>
@endif
