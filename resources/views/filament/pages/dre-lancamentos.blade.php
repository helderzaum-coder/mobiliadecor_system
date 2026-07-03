<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    {{-- Resumo --}}
    @php $totais = $this->totais; @endphp
    <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap;align-items:center;">
        <div style="flex:1;min-width:150px;padding:12px;border-radius:10px;background:#1f2937;border-top:3px solid #9ca3af;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Total</div>
            <div style="font-size:20px;font-weight:700;color:#e5e7eb;">{{ $totais['total'] }}</div>
        </div>
        <div style="flex:1;min-width:150px;padding:12px;border-radius:10px;background:#1f2937;border-top:3px solid #f59e0b;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Pendentes</div>
            <div style="font-size:20px;font-weight:700;color:#f59e0b;">{{ $totais['pendentes'] }}</div>
        </div>
        <div style="flex:1;min-width:150px;padding:12px;border-radius:10px;background:#1f2937;border-top:3px solid #10b981;">
            <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Lançados</div>
            <div style="font-size:20px;font-weight:700;color:#10b981;">{{ $totais['lancados'] }}</div>
        </div>
        <div style="min-width:200px;">
            <button wire:click="lancarTodos" wire:confirm="Lançar TODAS as vendas pendentes do período no DRE? Isso vai travar para edição." style="padding:10px 20px;border-radius:8px;background:#10b981;color:#fff;font-weight:600;font-size:13px;border:none;cursor:pointer;">
                🔒 Lançar Todos no DRE
            </button>
        </div>
    </div>

    {{-- Lista de vendas em formato DRE --}}
    <div style="margin-top:20px;display:flex;flex-direction:column;gap:12px;">
        @forelse($this->vendas as $venda)
            @php
                $lancado = (bool) $venda->dre_lancado;
                $editando = $this->editandoId === $venda->id_venda;
                $canal = $venda->canal_nome ?? $venda->canal?->nome_canal ?? '-';
                $totalVenda = (float) $venda->total_produtos + (float) $venda->valor_frete_cliente;
                $imposto = (float) $venda->valor_imposto;
                if ($imposto <= 0 && !$editando) {
                    $percentual = \App\Services\DreService::getAliquotaMesAnterior($venda->data_venda, $venda->id_cnpj);
                    $base = (float) $venda->nfe_valor ?: (float) $venda->valor_total_venda;
                    $imposto = round($base * ($percentual / 100), 2);
                }
                $frete = (float) $venda->valor_frete_transportadora;
                $comissao = (float) $venda->comissao + (float) $venda->comissao_afiliado;
                $cmv = (float) $venda->custo_produtos;
                $resultado = $totalVenda - $imposto - $frete - $comissao - $cmv;
            @endphp

            <div style="border-radius:10px;background:#1f2937;overflow:hidden;border:1px solid {{ $editando ? '#2563eb' : ($lancado ? '#065f46' : '#374151') }};{{ $lancado && !$editando ? 'opacity:0.85;' : '' }}">
                {{-- Header do pedido --}}
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#111827;border-bottom:1px solid #374151;">
                    <div style="display:flex;gap:16px;align-items:center;">
                        <span style="color:#e5e7eb;font-weight:600;font-size:13px;">#{{ $venda->numero_pedido_canal }}</span>
                        <span style="color:#6b7280;font-size:12px;">{{ $venda->data_venda?->format('d/m/Y') }}</span>
                        <span style="padding:2px 8px;border-radius:4px;background:#374151;color:#d1d5db;font-size:11px;">{{ $canal }}</span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;">
                        @if($editando)
                            <button wire:click="salvarEdicao" style="padding:4px 12px;border-radius:6px;background:#2563eb;color:#fff;font-size:11px;font-weight:600;border:none;cursor:pointer;">💾 Salvar</button>
                            <button wire:click="cancelarEdicao" style="padding:4px 10px;border-radius:6px;background:#374151;color:#9ca3af;font-size:11px;border:none;cursor:pointer;">Cancelar</button>
                        @elseif($lancado)
                            <span style="color:#10b981;font-size:11px;font-weight:600;">🔒 LANÇADO</span>
                            <button wire:click="destravarDre({{ $venda->id_venda }})" style="padding:4px 10px;border-radius:6px;background:#7f1d1d;color:#fca5a5;font-size:11px;border:none;cursor:pointer;">Destravar</button>
                        @else
                            <button wire:click="editarVenda({{ $venda->id_venda }})" style="padding:4px 10px;border-radius:6px;background:#1e3a5f;color:#93c5fd;font-size:11px;border:none;cursor:pointer;">✏️ Editar</button>
                            <button wire:click="lancarDre({{ $venda->id_venda }})" style="padding:4px 12px;border-radius:6px;background:#065f46;color:#6ee7b7;font-size:11px;font-weight:600;border:none;cursor:pointer;">Lançar DRE</button>
                        @endif
                    </div>
                </div>

                {{-- Corpo DRE da venda --}}
                @if($editando)
                    {{-- MODO EDIÇÃO --}}
                    <table style="width:100%;font-size:12px;border-collapse:collapse;">
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#10b981;font-weight:500;width:40%;">📥 Subtotal Produtos</td>
                            <td style="padding:6px 16px;width:30%;"><input type="text" wire:model.defer="edit_total_produtos" style="width:120px;padding:4px 8px;border-radius:4px;background:#111827;border:1px solid #374151;color:#e5e7eb;font-size:12px;text-align:right;"></td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;"></td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#10b981;font-weight:500;">📥 Frete Cobrado</td>
                            <td style="padding:6px 16px;"><input type="text" wire:model.defer="edit_valor_frete_cliente" style="width:120px;padding:4px 8px;border-radius:4px;background:#111827;border:1px solid #374151;color:#e5e7eb;font-size:12px;text-align:right;"></td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;"></td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#f59e0b;">📋 DAS (Simples) — Imposto</td>
                            <td style="padding:6px 16px;"><input type="text" wire:model.defer="edit_valor_imposto" style="width:120px;padding:4px 8px;border-radius:4px;background:#111827;border:1px solid #374151;color:#e5e7eb;font-size:12px;text-align:right;"></td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;">{{ (float)$venda->percentual_imposto > 0 ? $venda->percentual_imposto . '%' : '' }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#ef4444;">🚚 Frete Transportadora</td>
                            <td style="padding:6px 16px;"><input type="text" wire:model.defer="edit_valor_frete_transportadora" style="width:120px;padding:4px 8px;border-radius:4px;background:#111827;border:1px solid #374151;color:#e5e7eb;font-size:12px;text-align:right;"></td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;">{{ $venda->transportadora_manual ?? '' }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#a78bfa;">💰 Comissão Marketplace</td>
                            <td style="padding:6px 16px;"><input type="text" wire:model.defer="edit_comissao" style="width:120px;padding:4px 8px;border-radius:4px;background:#111827;border:1px solid #374151;color:#e5e7eb;font-size:12px;text-align:right;"></td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;"></td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#a78bfa;">💰 Comissão Afiliado</td>
                            <td style="padding:6px 16px;"><input type="text" wire:model.defer="edit_comissao_afiliado" style="width:120px;padding:4px 8px;border-radius:4px;background:#111827;border:1px solid #374151;color:#e5e7eb;font-size:12px;text-align:right;"></td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;"></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 16px;color:#fb923c;">📦 CMV (Custo Produto)</td>
                            <td style="padding:6px 16px;"><input type="text" wire:model.defer="edit_custo_produtos" style="width:120px;padding:4px 8px;border-radius:4px;background:#111827;border:1px solid #374151;color:#e5e7eb;font-size:12px;text-align:right;"></td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;"></td>
                        </tr>
                    </table>
                @else
                    {{-- MODO VISUALIZAÇÃO --}}
                    <table style="width:100%;font-size:12px;border-collapse:collapse;">
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#10b981;font-weight:500;width:40%;">📥 Entrada (Subtotal + Frete)</td>
                            <td style="padding:6px 16px;text-align:right;color:#10b981;font-weight:600;width:25%;">R$ {{ number_format($totalVenda, 2, ',', '.') }}</td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;">Prod: {{ number_format((float)$venda->total_produtos, 2, ',', '.') }} | Frete: {{ number_format((float)$venda->valor_frete_cliente, 2, ',', '.') }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#f59e0b;">📋 DAS (Simples) — Imposto</td>
                            <td style="padding:6px 16px;text-align:right;color:#f59e0b;">-R$ {{ number_format($imposto, 2, ',', '.') }}</td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;">{{ (float)$venda->percentual_imposto > 0 ? $venda->percentual_imposto . '%' : 'provisório' }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#ef4444;">🚚 Frete Transportadora</td>
                            <td style="padding:6px 16px;text-align:right;color:#ef4444;">-R$ {{ number_format($frete, 2, ',', '.') }}</td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;">{{ $venda->transportadora_manual ?? '' }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#a78bfa;">💰 Comissão de Vendas</td>
                            <td style="padding:6px 16px;text-align:right;color:#a78bfa;">-R$ {{ number_format($comissao, 2, ',', '.') }}</td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;">{{ (float)$venda->comissao_afiliado > 0 ? 'Mktp: ' . number_format((float)$venda->comissao, 2, ',', '.') . ' + Afil: ' . number_format((float)$venda->comissao_afiliado, 2, ',', '.') : '' }}</td>
                        </tr>
                        <tr style="border-bottom:1px solid #374151;">
                            <td style="padding:6px 16px;color:#fb923c;">📦 CMV (Custo Produto)</td>
                            <td style="padding:6px 16px;text-align:right;color:#fb923c;">-R$ {{ number_format($cmv, 2, ',', '.') }}</td>
                            <td style="padding:6px 16px;color:#6b7280;font-size:11px;"></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 16px;font-weight:700;color:{{ $resultado >= 0 ? '#10b981' : '#ef4444' }};">= Resultado</td>
                            <td style="padding:8px 16px;text-align:right;font-weight:700;font-size:14px;color:{{ $resultado >= 0 ? '#10b981' : '#ef4444' }};">R$ {{ number_format($resultado, 2, ',', '.') }}</td>
                            <td style="padding:8px 16px;color:#6b7280;font-size:11px;">{{ $totalVenda > 0 ? round(($resultado / $totalVenda) * 100, 1) . '% margem' : '' }}</td>
                        </tr>
                    </table>
                @endif
            </div>
        @empty
            <div style="padding:40px;text-align:center;color:#6b7280;">Nenhuma venda no período.</div>
        @endforelse
    </div>

    {{-- Paginação --}}
    @if($this->totalPaginas > 1)
    <div style="display:flex;justify-content:center;align-items:center;gap:16px;margin-top:16px;">
        <button wire:click="paginaAnterior" @if($this->pagina <= 1) disabled @endif style="padding:6px 14px;border-radius:6px;background:#374151;color:#e5e7eb;border:none;cursor:pointer;{{ $this->pagina <= 1 ? 'opacity:0.4;' : '' }}">← Anterior</button>
        <span style="color:#9ca3af;font-size:13px;">{{ $this->pagina }} / {{ $this->totalPaginas }}</span>
        <button wire:click="proximaPagina" @if($this->pagina >= $this->totalPaginas) disabled @endif style="padding:6px 14px;border-radius:6px;background:#374151;color:#e5e7eb;border:none;cursor:pointer;{{ $this->pagina >= $this->totalPaginas ? 'opacity:0.4;' : '' }}">Próxima →</button>
    </div>
    @endif
</x-filament-panels::page>
