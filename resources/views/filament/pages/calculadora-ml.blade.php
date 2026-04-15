<x-filament-panels::page>
    {{-- Tabs Modo --}}
    <div style="display:flex;gap:0;margin-bottom:24px;">
        <button wire:click="$set('modo','margem')"
            style="flex:1;padding:14px;font-size:14px;font-weight:700;border:2px solid {{ $modo === 'margem' ? '#10b981' : '#374151' }};border-radius:12px 0 0 12px;cursor:pointer;
            background:{{ $modo === 'margem' ? '#10b981' : 'transparent' }};color:{{ $modo === 'margem' ? '#fff' : '#9ca3af' }};">
            📊 Calcular Margem
            <div style="font-size:11px;font-weight:400;margin-top:2px;">Já sei o preço, quanto sobra?</div>
        </button>
        <button wire:click="$set('modo','preco_ideal')"
            style="flex:1;padding:14px;font-size:14px;font-weight:700;border:2px solid {{ $modo === 'preco_ideal' ? '#10b981' : '#374151' }};border-radius:0 12px 12px 0;cursor:pointer;
            background:{{ $modo === 'preco_ideal' ? '#10b981' : 'transparent' }};color:{{ $modo === 'preco_ideal' ? '#fff' : '#9ca3af' }};">
            💲 Calcular Preço Ideal
            <div style="font-size:11px;font-weight:400;margin-top:2px;">Qual preço devo vender?</div>
        </button>
    </div>

    {{-- Formulário --}}
    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6 space-y-5">

        {{-- Linha 1: Custo + Preço/Margem --}}
        <div style="display:flex;gap:16px;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Custo do Produto (R$) *</label>
                <input type="number" step="0.01" wire:model="custo_produto" placeholder="0,00"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Quanto você pagou pelo produto</div>
            </div>
            @if($modo === 'margem')
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Preço de Venda (R$) *</label>
                <input type="number" step="0.01" wire:model="preco_venda" placeholder="0,00"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Por quanto você está vendendo</div>
            </div>
            @else
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Margem Desejada (%) *</label>
                <input type="number" step="0.1" wire:model="margem_desejada" placeholder="20"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Ex: 20% de lucro sobre o preço final</div>
            </div>
            @endif
        </div>

        {{-- Linha 2: Tipo Anúncio + Imposto --}}
        <div style="display:flex;gap:16px;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Tipo de Anúncio *</label>
                <select wire:model="tipo_anuncio"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                    <option value="classico">Clássico (11,5%)</option>
                    <option value="premium">Premium (16,5%)</option>
                </select>
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Imposto (%)</label>
                <input type="number" step="0.01" wire:model="percentual_imposto" placeholder="0"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Percentual de imposto sobre o preço</div>
            </div>
        </div>

        {{-- Linha 3: Tipo Frete + Custo Frete --}}
        <div style="display:flex;gap:16px;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Tipo de Frete</label>
                <select wire:model="tipo_frete"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                    <option value="ME2">ME2 / FULL (frete por conta do ML)</option>
                    <option value="ME1">ME1 (frete por conta do vendedor)</option>
                </select>
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Custo de Frete/Envio (R$)</label>
                <input type="number" step="0.01" wire:model="custo_frete" placeholder="0,00"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Custo de envio cobrado pelo ML (ME2) ou transportadora (ME1)</div>
            </div>
        </div>

        {{-- Botões --}}
        <div style="display:flex;gap:12px;margin-top:8px;">
            <button wire:click="calcular"
                style="flex:1;padding:14px;font-size:15px;font-weight:700;border-radius:10px;border:none;cursor:pointer;background:#10b981;color:#fff;">
                {{ $modo === 'margem' ? '📊 Calcular Minha Margem' : '💲 Calcular Preço Ideal' }}
            </button>
            <button wire:click="limpar"
                style="padding:14px 24px;font-size:14px;border-radius:10px;border:1px solid #374151;cursor:pointer;background:transparent;color:#9ca3af;">
                🔄 Limpar
            </button>
        </div>
    </div>

    {{-- Resultado --}}
    @if($resultado)
        @if(isset($resultado['erro']))
            <div style="margin-top:20px;padding:14px;border-radius:10px;border:2px solid #ef4444;background:rgba(239,68,68,.1);color:#fca5a5;font-size:14px;">
                ⚠️ {{ $resultado['erro'] }}
            </div>
        @else
            @php
                $r = $resultado;
                $margemColor = $r['margem'] >= 0 ? '#10b981' : '#ef4444';
            @endphp

            {{-- Cards KPI --}}
            <div style="display:flex;gap:16px;margin-top:20px;">
                @if($r['modo'] === 'preco_ideal')
                <div style="flex:1;padding:20px;border-radius:12px;background:rgba(16,185,129,.1);border:2px solid #10b981;text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Preço Ideal de Venda</div>
                    <div style="font-size:32px;font-weight:800;color:#10b981;">R$ {{ number_format($r['preco_venda'], 2, ',', '.') }}</div>
                </div>
                @endif
                <div style="flex:1;padding:20px;border-radius:12px;background:rgba(245,158,11,.1);border:2px solid #f59e0b;text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Quanto Você Recebe</div>
                    <div style="font-size:28px;font-weight:800;color:#f59e0b;">R$ {{ number_format($r['recebe'], 2, ',', '.') }}</div>
                    <div style="font-size:11px;color:#6b7280;">do marketplace</div>
                </div>
                <div style="flex:1;padding:20px;border-radius:12px;background:rgba(16,185,129,.05);border:2px solid {{ $margemColor }};text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Margem de Contribuição</div>
                    <div style="font-size:28px;font-weight:800;color:{{ $margemColor }};">R$ {{ number_format($r['margem'], 2, ',', '.') }}</div>
                </div>
                <div style="flex:1;padding:20px;border-radius:12px;background:rgba(16,185,129,.05);border:2px solid {{ $margemColor }};text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Margem %</div>
                    <div style="font-size:28px;font-weight:800;color:{{ $margemColor }};">{{ $r['margem_pct'] }}%</div>
                </div>
            </div>

            {{-- Detalhamento --}}
            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6 mt-4">
                <div style="font-size:14px;font-weight:700;color:#e5e7eb;margin-bottom:12px;">Detalhamento:</div>
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Preço de Venda:</td>
                        <td style="padding:8px 0;text-align:right;font-weight:600;color:#e5e7eb;">R$ {{ number_format($r['preco_venda'], 2, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Comissão ML ({{ $r['comissao_pct'] }}%):</td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['comissao'], 2, ',', '.') }}</td>
                    </tr>
                    @if($r['custo_frete'] > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Custo de Envio:</td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['custo_frete'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr style="border-bottom:2px solid #374151;">
                        <td style="padding:10px 0;font-weight:700;color:#e5e7eb;">Quanto Você Recebe (ML):</td>
                        <td style="padding:10px 0;text-align:right;font-weight:700;color:#f59e0b;">R$ {{ number_format($r['recebe'], 2, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Custo do Produto:</td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['custo_produto'], 2, ',', '.') }}</td>
                    </tr>
                    @if($r['imposto'] > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Imposto ({{ $r['imposto_pct'] }}%):</td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['imposto'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:10px 0;font-weight:700;color:#e5e7eb;">Margem Final ({{ $r['margem_pct'] }}%):</td>
                        <td style="padding:10px 0;text-align:right;font-weight:700;font-size:16px;color:{{ $margemColor }};">R$ {{ number_format($r['margem'], 2, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
