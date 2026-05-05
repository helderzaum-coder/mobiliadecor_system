<x-filament-panels::page>
    <div style="display:flex;align-items:flex-end;gap:12px;">
        <div style="flex:1;">
            <form wire:submit.prevent="">
                {{ $this->form }}
            </form>
        </div>
        <button wire:click="$refresh" style="background:#2563eb;color:#fff;padding:8px 14px;font-size:12px;border-radius:8px;border:none;cursor:pointer;margin-bottom:2px;">
            🔄 Atualizar
        </button>
    </div>

    {{-- Resumo --}}
    @php $totais = $this->totais; @endphp
    <div style="display:flex;gap:16px;margin-top:16px;">
        <div style="flex:1;padding:16px;border-radius:12px;background:var(--kpi-bg,#1f2937);border-top:4px solid #6366f1;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Total Repasse</div>
            <div style="font-size:24px;font-weight:800;color:#e5e7eb;">R$ {{ number_format($totais['total_repasse'], 2, ',', '.') }}</div>
            <div style="font-size:11px;color:#6b7280;">{{ $totais['qtd_total'] }} pedidos</div>
        </div>
        <div style="flex:1;padding:16px;border-radius:12px;background:var(--kpi-bg,#1f2937);border-top:4px solid #10b981;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Recebido</div>
            <div style="font-size:24px;font-weight:800;color:#10b981;">R$ {{ number_format($totais['recebido'], 2, ',', '.') }}</div>
            <div style="font-size:11px;color:#6b7280;">{{ $totais['qtd_recebido'] }} pedidos</div>
        </div>
        <div style="flex:1;padding:16px;border-radius:12px;background:var(--kpi-bg,#1f2937);border-top:4px solid #f59e0b;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Pendente</div>
            <div style="font-size:24px;font-weight:800;color:#f59e0b;">R$ {{ number_format($totais['pendente'], 2, ',', '.') }}</div>
            <div style="font-size:11px;color:#6b7280;">{{ $totais['qtd_pendente'] }} pedidos</div>
        </div>
    </div>

    {{-- Lista de vendas --}}
    <div class="mt-4 rounded-xl bg-white dark:bg-gray-800 shadow-md overflow-hidden">
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:2px solid #374151;background:#111827;">
                    <th style="padding:10px;text-align:left;color:#9ca3af;">Data</th>
                    <th style="padding:10px;text-align:left;color:#9ca3af;">Pedido</th>
                    <th style="padding:10px;text-align:left;color:#9ca3af;">Canal</th>
                    <th style="padding:10px;text-align:left;color:#9ca3af;">Conta</th>
                    <th style="padding:10px;text-align:left;color:#9ca3af;">Cliente</th>
                    <th style="padding:10px;text-align:right;color:#9ca3af;">Total</th>
                    <th style="padding:10px;text-align:right;color:#9ca3af;">Comissão</th>
                    <th style="padding:10px;text-align:right;color:#9ca3af;font-weight:700;">Repasse</th>
                    <th style="padding:10px;text-align:center;color:#9ca3af;">Status</th>
                    <th style="padding:10px;text-align:center;color:#9ca3af;">Ação</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->vendas as $venda)
                    @php
                        $isMagalu = str_contains(strtolower($venda->canal?->nome_canal ?? ''), 'magalu');
                        $repasse = $isMagalu
                            ? (float) $venda->valor_total_venda - (float) $venda->comissao + (float) $venda->subsidio_pix
                            : (float) $venda->total_produtos + (float) $venda->valor_frete_cliente - (float) $venda->comissao;
                        $conta = $venda->bling_account === 'primary' ? 'Mobilia' : 'HES';
                    @endphp
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 10px;color:#e5e7eb;">{{ $venda->data_venda?->format('d/m') }}</td>
                        <td style="padding:8px 10px;color:#e5e7eb;font-family:monospace;font-size:11px;">{{ $venda->numero_pedido_canal }}</td>
                        <td style="padding:8px 10px;color:#9ca3af;">{{ $venda->canal?->nome_canal ?? '-' }}</td>
                        <td style="padding:8px 10px;">
                            <span style="background:#4b5563;color:#e5e7eb;padding:2px 6px;border-radius:4px;font-size:10px;">{{ $conta }}</span>
                        </td>
                        <td style="padding:8px 10px;color:#9ca3af;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $venda->cliente_nome }}</td>
                        <td style="padding:8px 10px;text-align:right;color:#e5e7eb;">R$ {{ number_format($venda->valor_total_venda, 2, ',', '.') }}</td>
                        <td style="padding:8px 10px;text-align:right;color:#ef4444;">R$ {{ number_format($venda->comissao, 2, ',', '.') }}</td>
                        <td style="padding:8px 10px;text-align:right;font-weight:700;color:#10b981;">R$ {{ number_format($repasse, 2, ',', '.') }}</td>
                        <td style="padding:8px 10px;text-align:center;">
                            @if($venda->repasse_recebido)
                                <span style="background:#059669;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">✅ {{ $venda->data_recebimento?->format('d/m') }}</span>
                            @else
                                <span style="background:#d97706;color:#fff;padding:2px 8px;border-radius:4px;font-size:10px;">⏳ Pendente</span>
                            @endif
                        </td>
                        <td style="padding:8px 10px;text-align:center;">
                            @if(!$venda->repasse_recebido)
                                <button wire:click="confirmarRecebimento({{ $venda->id_venda }})"
                                    style="background:#10b981;color:#fff;padding:4px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                                    ✅ Recebido
                                </button>
                            @else
                                <button wire:click="desfazerRecebimento({{ $venda->id_venda }})"
                                    style="background:#374151;color:#9ca3af;padding:4px 10px;font-size:11px;border-radius:5px;border:none;cursor:pointer;">
                                    ↩ Desfazer
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" style="padding:20px;text-align:center;color:#6b7280;">Nenhuma venda encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginação --}}
    @php $totalPaginas = $this->totalPaginas; @endphp
    @if($totalPaginas > 1)
    <div class="flex items-center justify-center gap-4 mt-4">
        <button wire:click="paginaAnterior" @if($pagina <= 1) disabled @endif
            style="background:{{ $pagina <= 1 ? '#374151' : '#2563eb' }};color:#fff;padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;opacity:{{ $pagina <= 1 ? '0.5' : '1' }};">
            ← Anterior
        </button>
        <span class="text-sm text-gray-500">Página {{ $pagina }} de {{ $totalPaginas }}</span>
        <button wire:click="proximaPagina" @if($pagina >= $totalPaginas) disabled @endif
            style="background:{{ $pagina >= $totalPaginas ? '#374151' : '#2563eb' }};color:#fff;padding:6px 16px;font-size:13px;border-radius:6px;border:none;cursor:pointer;opacity:{{ $pagina >= $totalPaginas ? '0.5' : '1' }};">
            Próxima →
        </button>
    </div>
    @endif
</x-filament-panels::page>
