<x-filament-panels::page>
    {{-- Tabs Modo --}}
    <div style="display:flex;gap:0;margin-bottom:24px;">
        <button wire:click="$set('modo','margem')"
            style="flex:1;padding:12px;font-size:13px;font-weight:700;border:2px solid {{ $modo === 'margem' ? '#10b981' : '#374151' }};border-radius:12px 0 0 12px;cursor:pointer;
            background:{{ $modo === 'margem' ? '#10b981' : 'transparent' }};color:{{ $modo === 'margem' ? '#fff' : '#9ca3af' }};">
            📊 Calcular Margem
            <div style="font-size:10px;font-weight:400;margin-top:2px;">Já sei o preço, quanto sobra?</div>
        </button>
        <button wire:click="$set('modo','preco_ideal')"
            style="flex:1;padding:12px;font-size:13px;font-weight:700;border:2px solid {{ $modo === 'preco_ideal' ? '#10b981' : '#374151' }};border-radius:0 12px 12px 0;cursor:pointer;
            background:{{ $modo === 'preco_ideal' ? '#10b981' : 'transparent' }};color:{{ $modo === 'preco_ideal' ? '#fff' : '#9ca3af' }};">
            💲 Calcular Preço Ideal
            <div style="font-size:10px;font-weight:400;margin-top:2px;">Qual preço devo vender?</div>
        </button>
    </div>

    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6 space-y-5">

        {{-- Custo + Quantidade + Peso --}}
        <div style="display:flex;gap:16px;">
            <div style="flex:2;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Custo Unitário (R$) *</label>
                <input type="number" step="0.01" wire:model="custo_produto" placeholder="0,00"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Quantidade *</label>
                <input type="number" step="1" min="1" wire:model="quantidade" placeholder="1"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Peso/Unidade (kg)</label>
                <input type="number" step="0.001" wire:model="peso_unitario" placeholder="0,000"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
            </div>
        </div>

        {{-- Info calculada --}}
        @if($custo_produto && $quantidade > 0)
        <div style="display:flex;gap:16px;padding:10px 16px;border-radius:8px;background:#1f2937;">
            <div style="flex:1;font-size:12px;">
                <span style="color:#6b7280;">Custo Total:</span>
                <span style="color:#e5e7eb;font-weight:600;">R$ {{ number_format(($custo_produto ?? 0) * $quantidade, 2, ',', '.') }}</span>
            </div>
            @if($peso_unitario)
            <div style="flex:1;font-size:12px;">
                <span style="color:#6b7280;">Peso Total:</span>
                <span style="color:#e5e7eb;font-weight:600;">{{ number_format(($peso_unitario ?? 0) * $quantidade, 3, ',', '.') }} kg</span>
            </div>
            @endif
        </div>
        @endif

        {{-- Preço de Venda OU Margem Desejada --}}
        <div style="display:flex;gap:16px;">
            @if($modo === 'margem')
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Preço de Venda (R$) *</label>
                <input type="number" step="0.01" wire:model="preco_venda" placeholder="0,00"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Preço total do anúncio ({{ $quantidade }} un.)</div>
            </div>
            @else
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Margem Desejada (%) *</label>
                <input type="number" step="0.1" wire:model="margem_desejada" placeholder="20"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
            </div>
            @endif
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Imposto (%)</label>
                <input type="number" step="0.01" wire:model="percentual_imposto" placeholder="0"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
            </div>
        </div>

        {{-- Frete ML --}}
        <div style="display:flex;gap:16px;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Tipo de Frete (ML)</label>
                <select wire:model="tipo_frete"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                    <option value="ME2">ME2 / FULL (frete ML automático)</option>
                    <option value="ME1">ME1 (frete manual)</option>
                </select>
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">
                    Custo de Envio ML (R$)
                    @if(!$frete_manual_override)
                        <span style="font-size:10px;color:#10b981;">automático</span>
                    @else
                        <span style="font-size:10px;color:#f59e0b;">manual</span>
                    @endif
                </label>
                <div style="display:flex;gap:8px;">
                    <input type="number" step="0.01" wire:model="custo_frete_manual" placeholder="0,00"
                        {{ !$frete_manual_override && $tipo_frete === 'ME2' ? 'readonly' : '' }}
                        style="flex:1;padding:10px 14px;border-radius:8px;border:1px solid {{ $frete_manual_override ? '#f59e0b' : '#374151' }};background:{{ $frete_manual_override ? '#1a1a2e' : '#111827' }};color:#fff;font-size:14px;">
                    @if($tipo_frete === 'ME2')
                    <button wire:click="$set('frete_manual_override', {{ $frete_manual_override ? 'false' : 'true' }})"
                        style="padding:10px 12px;font-size:11px;border-radius:8px;border:1px solid {{ $frete_manual_override ? '#f59e0b' : '#374151' }};cursor:pointer;
                        background:{{ $frete_manual_override ? 'rgba(245,158,11,.15)' : 'transparent' }};color:{{ $frete_manual_override ? '#f59e0b' : '#9ca3af' }};white-space:nowrap;">
                        {{ $frete_manual_override ? '🔓' : '✏️' }}
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Botões --}}
        <div style="display:flex;gap:12px;margin-top:8px;">
            <button wire:click="calcular"
                style="flex:1;padding:14px;font-size:15px;font-weight:700;border-radius:10px;border:none;cursor:pointer;background:#10b981;color:#fff;">
                {{ $modo === 'margem' ? '📊 Calcular Margem em Todos os Canais' : '💲 Calcular Preço Ideal por Canal' }}
            </button>
            <button wire:click="limpar"
                style="padding:14px 24px;font-size:14px;border-radius:10px;border:1px solid #374151;cursor:pointer;background:transparent;color:#9ca3af;">
                🔄 Limpar
            </button>
        </div>
    </div>

    {{-- Resultados --}}
    @if($resultados)
        @if(isset($resultados['erro']))
            <div style="margin-top:20px;padding:14px;border-radius:10px;border:2px solid #ef4444;background:rgba(239,68,68,.1);color:#fca5a5;font-size:14px;">
                ⚠️ {{ $resultados['erro'] }}
            </div>
        @else
            @php $r = $resultados; @endphp

            {{-- Cards por canal --}}
            <div style="display:flex;gap:16px;margin-top:20px;flex-wrap:wrap;">
                @foreach($r['canais'] as $canal)
                    @php
                        $mc = $canal['margem'] >= 0 ? '#10b981' : '#ef4444';
                        $statusMsg = $canal['margem_pct'] >= 25 ? '🎉 Excelente' : ($canal['margem_pct'] >= 15 ? '✅ Saudável' : ($canal['margem_pct'] >= 5 ? '⚠️ Baixa' : '🚨 Crítica'));
                    @endphp
                    <div style="flex:1;min-width:280px;border-radius:12px;border:2px solid {{ $canal['cor'] }};background:rgba(0,0,0,.2);padding:20px;">
                        {{-- Header canal --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <div style="font-size:16px;font-weight:700;color:{{ $canal['cor'] }};">
                                {{ $canal['icone'] }} {{ $canal['canal'] }}
                            </div>
                            <div style="font-size:11px;padding:4px 8px;border-radius:6px;background:{{ $mc }}22;color:{{ $mc }};font-weight:600;">
                                {{ $statusMsg }}
                            </div>
                        </div>

                        {{-- Preço ideal (modo preco_ideal) --}}
                        @if($r['modo'] === 'preco_ideal' && isset($canal['preco_venda']))
                        <div style="text-align:center;padding:12px;margin-bottom:12px;border-radius:8px;background:rgba(255,255,255,.05);">
                            <div style="font-size:11px;color:#9ca3af;">Preço de Venda</div>
                            <div style="font-size:28px;font-weight:800;color:{{ $canal['cor'] }};">R$ {{ number_format($canal['preco_venda'], 2, ',', '.') }}</div>
                        </div>
                        @endif

                        {{-- Margem --}}
                        <div style="text-align:center;padding:10px;margin-bottom:12px;border-radius:8px;border:1px solid {{ $mc }};background:{{ $mc }}11;">
                            <div style="font-size:11px;color:#9ca3af;">Margem</div>
                            <div style="font-size:22px;font-weight:800;color:{{ $mc }};">R$ {{ number_format($canal['margem'], 2, ',', '.') }} <span style="font-size:14px;">({{ $canal['margem_pct'] }}%)</span></div>
                        </div>

                        {{-- Detalhes --}}
                        <div style="font-size:12px;space-y:4px;">
                            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #1f2937;">
                                <span style="color:#6b7280;">Comissão ({{ $canal['comissao_pct'] }}%{{ isset($canal['comissao_fixa']) ? ' + R$'.$canal['comissao_fixa'] : '' }})</span>
                                <span style="color:#ef4444;">- R$ {{ number_format($canal['comissao'], 2, ',', '.') }}</span>
                            </div>
                            @if($canal['frete'] > 0)
                            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #1f2937;">
                                <span style="color:#6b7280;">Frete ({{ $r['faixa_peso'] ?? 'N/A' }})</span>
                                <span style="color:#ef4444;">- R$ {{ number_format($canal['frete'], 2, ',', '.') }}</span>
                            </div>
                            @endif
                            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #1f2937;">
                                <span style="color:#6b7280;">Recebe</span>
                                <span style="color:#f59e0b;font-weight:600;">R$ {{ number_format($canal['recebe'], 2, ',', '.') }}</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #1f2937;">
                                <span style="color:#6b7280;">Custo ({{ $r['quantidade'] }} × R$ {{ number_format($r['custo_unitario'], 2, ',', '.') }})</span>
                                <span style="color:#ef4444;">- R$ {{ number_format($r['custo_total'], 2, ',', '.') }}</span>
                            </div>
                            @if(($r['imposto_pct'] ?? 0) > 0)
                            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #1f2937;">
                                <span style="color:#6b7280;">Imposto ({{ $r['imposto_pct'] }}%)</span>
                                <span style="color:#ef4444;">- R$ {{ number_format(($canal['preco_venda'] ?? $r['preco_venda'] ?? 0) * $r['imposto_pct'] / 100, 2, ',', '.') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
