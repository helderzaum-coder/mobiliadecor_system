<x-filament-panels::page>
    {{-- Seletor Marketplace --}}
    <div style="display:flex;gap:0;margin-bottom:16px;">
        <button wire:click="$set('marketplace','ml')"
            style="flex:1;padding:12px;font-size:14px;font-weight:700;border:2px solid {{ $marketplace === 'ml' ? '#3b82f6' : '#374151' }};border-radius:12px 0 0 12px;cursor:pointer;
            background:{{ $marketplace === 'ml' ? '#3b82f6' : 'transparent' }};color:{{ $marketplace === 'ml' ? '#fff' : '#9ca3af' }};">
            🟡 Mercado Livre
        </button>
        <button wire:click="$set('marketplace','shopee')"
            style="flex:1;padding:12px;font-size:14px;font-weight:700;border:2px solid {{ $marketplace === 'shopee' ? '#ea580c' : '#374151' }};border-radius:0 12px 12px 0;cursor:pointer;
            background:{{ $marketplace === 'shopee' ? '#ea580c' : 'transparent' }};color:{{ $marketplace === 'shopee' ? '#fff' : '#9ca3af' }};">
            🟠 Shopee
        </button>
    </div>

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
            @if($marketplace === 'ml')
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Peso/Unidade (kg)</label>
                <input type="number" step="0.001" wire:model="peso_unitario" placeholder="0,000"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
            </div>
            @endif
        </div>

        {{-- Info calculada --}}
        @if($custo_produto && $quantidade > 0)
        <div style="display:flex;gap:16px;padding:10px 16px;border-radius:8px;background:#1f2937;">
            <div style="flex:1;font-size:12px;">
                <span style="color:#6b7280;">Custo Total:</span>
                <span style="color:#e5e7eb;font-weight:600;">R$ {{ number_format(($custo_produto ?? 0) * $quantidade, 2, ',', '.') }}</span>
            </div>
            @if($marketplace === 'ml' && $peso_unitario)
            <div style="flex:1;font-size:12px;">
                <span style="color:#6b7280;">Peso Total:</span>
                <span style="color:#e5e7eb;font-weight:600;">{{ number_format(($peso_unitario ?? 0) * $quantidade, 3, ',', '.') }} kg</span>
            </div>
            @endif
        </div>
        @endif

        {{-- Preço/Margem + Tipo Anúncio --}}
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
            @if($marketplace === 'ml')
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Tipo de Anúncio</label>
                <select wire:model="tipo_anuncio"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                    <option value="classico">Clássico (11,5%)</option>
                    <option value="premium">Premium (16,5%)</option>
                </select>
            </div>
            @else
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Comissão Shopee</label>
                <div style="padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#6b7280;font-size:13px;">
                    Automática por faixa de preço
                </div>
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">20%+R$4 (até R$79) | 14%+R$16~26 (acima)</div>
            </div>
            @endif
        </div>

        {{-- Imposto + Frete --}}
        <div style="display:flex;gap:16px;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Imposto (%)</label>
                <input type="number" step="0.01" wire:model="percentual_imposto" placeholder="0"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
            </div>
            @if($marketplace === 'ml')
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Tipo de Frete</label>
                <select wire:model="tipo_frete"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
                    <option value="ME2">ME2 / FULL (frete ML automático)</option>
                    <option value="ME1">ME1 (frete manual)</option>
                </select>
            </div>
            @endif
        </div>

        @if($marketplace === 'ml' && $tipo_frete === 'ME2')
        <div style="display:flex;gap:16px;align-items:flex-end;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">
                    Custo de Envio ML (R$)
                    @if(!$frete_manual_override)
                        <span style="font-size:10px;color:#10b981;">automático pela tabela</span>
                    @else
                        <span style="font-size:10px;color:#f59e0b;">editado manualmente</span>
                    @endif
                </label>
                <input type="number" step="0.01" wire:model="custo_frete_manual" placeholder="0,00"
                    {{ !$frete_manual_override ? 'readonly' : '' }}
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid {{ $frete_manual_override ? '#f59e0b' : '#374151' }};background:{{ $frete_manual_override ? '#1a1a2e' : '#111827' }};color:#fff;font-size:14px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Valor que o ML cobra de envio. Ative a edição para corrigir manualmente.</div>
            </div>
            <div style="margin-bottom:8px;">
                <button wire:click="$toggle('frete_manual_override')"
                    style="padding:10px 14px;font-size:12px;border-radius:8px;border:1px solid {{ $frete_manual_override ? '#f59e0b' : '#374151' }};cursor:pointer;
                    background:{{ $frete_manual_override ? 'rgba(245,158,11,.15)' : 'transparent' }};color:{{ $frete_manual_override ? '#f59e0b' : '#9ca3af' }};">
                    {{ $frete_manual_override ? '🔓 Editando' : '✏️ Editar' }}
                </button>
            </div>
        </div>
        @endif

        @if($marketplace === 'ml' && $tipo_frete === 'ME1')
        <div style="display:flex;gap:16px;">
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Custo de Frete Manual (R$)</label>
                <input type="number" step="0.01" wire:model="custo_frete_manual" placeholder="0,00"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:14px;">
            </div>
            <div style="flex:1;"></div>
        </div>
        @endif

        {{-- Botões --}}
        <div style="display:flex;gap:12px;margin-top:8px;">
            <button wire:click="calcular"
                style="flex:1;padding:14px;font-size:15px;font-weight:700;border-radius:10px;border:none;cursor:pointer;
                background:{{ $marketplace === 'ml' ? '#3b82f6' : '#ea580c' }};color:#fff;">
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
                $mc = $r['margem'] >= 0 ? '#10b981' : '#ef4444';
                $msg = $r['margem_pct'] >= 25 ? '🎉 Margem excelente!' : ($r['margem_pct'] >= 15 ? '✅ Margem saudável' : ($r['margem_pct'] >= 5 ? '⚠️ Margem baixa' : '🚨 Margem crítica!'));
                $sc = $r['margem_pct'] >= 15 ? '#10b981' : ($r['margem_pct'] >= 5 ? '#f59e0b' : '#ef4444');
                $isShopeeR = ($r['marketplace'] ?? 'ml') === 'shopee';
            @endphp

            <div style="margin-top:20px;padding:12px 16px;border-radius:10px;border:2px solid {{ $sc }};background:rgba(0,0,0,.1);color:{{ $sc }};font-size:14px;font-weight:600;">
                {{ $msg }}
            </div>

            <div style="display:flex;gap:16px;margin-top:16px;">
                @if($r['modo'] === 'preco_ideal')
                <div style="flex:1;padding:20px;border-radius:12px;background:rgba(16,185,129,.1);border:2px solid #10b981;text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Preço Ideal de Venda</div>
                    <div style="font-size:32px;font-weight:800;color:#10b981;">R$ {{ number_format($r['preco_venda'], 2, ',', '.') }}</div>
                    <div style="font-size:11px;color:#6b7280;">para {{ $r['quantidade'] }} un.</div>
                </div>
                @endif
                <div style="flex:1;padding:20px;border-radius:12px;background:rgba(245,158,11,.1);border:2px solid #f59e0b;text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Quanto Você Recebe</div>
                    <div style="font-size:28px;font-weight:800;color:#f59e0b;">R$ {{ number_format($r['recebe'], 2, ',', '.') }}</div>
                </div>
                <div style="flex:1;padding:20px;border-radius:12px;border:2px solid {{ $mc }};text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Margem</div>
                    <div style="font-size:28px;font-weight:800;color:{{ $mc }};">R$ {{ number_format($r['margem'], 2, ',', '.') }}</div>
                </div>
                <div style="flex:1;padding:20px;border-radius:12px;border:2px solid {{ $mc }};text-align:center;">
                    <div style="font-size:12px;color:#9ca3af;">Margem %</div>
                    <div style="font-size:28px;font-weight:800;color:{{ $mc }};">{{ $r['margem_pct'] }}%</div>
                </div>
            </div>

            <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6 mt-4">
                <div style="font-size:14px;font-weight:700;color:#e5e7eb;margin-bottom:12px;">Detalhamento:</div>
                <table style="width:100%;font-size:13px;border-collapse:collapse;">
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Preço de Venda ({{ $r['quantidade'] }} un.):</td>
                        <td style="padding:8px 0;text-align:right;font-weight:600;color:#e5e7eb;">R$ {{ number_format($r['preco_venda'], 2, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">
                            Comissão {{ $isShopeeR ? 'Shopee' : 'ML' }}
                            ({{ $r['comissao_pct'] }}%{{ $isShopeeR ? ' + R$ ' . number_format($r['comissao_fixa'] ?? 0, 0) : '' }}):
                        </td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['comissao'], 2, ',', '.') }}</td>
                    </tr>
                    @if(($r['custo_frete'] ?? 0) > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Custo de Envio ({{ $r['faixa_peso'] ?? '' }} — {{ number_format($r['peso_total'] ?? 0, 3, ',', '.') }}kg):</td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['custo_frete'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr style="border-bottom:2px solid #374151;">
                        <td style="padding:10px 0;font-weight:700;color:#e5e7eb;">Quanto Você Recebe:</td>
                        <td style="padding:10px 0;text-align:right;font-weight:700;color:#f59e0b;">R$ {{ number_format($r['recebe'], 2, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Custo Produto ({{ $r['quantidade'] }} × R$ {{ number_format($r['custo_unitario'], 2, ',', '.') }}):</td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['custo_total'], 2, ',', '.') }}</td>
                    </tr>
                    @if(($r['imposto'] ?? 0) > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 0;color:#9ca3af;">Imposto ({{ $r['imposto_pct'] }}%):</td>
                        <td style="padding:8px 0;text-align:right;color:#ef4444;">- R$ {{ number_format($r['imposto'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:10px 0;font-weight:700;color:#e5e7eb;">Margem Final ({{ $r['margem_pct'] }}%):</td>
                        <td style="padding:10px 0;text-align:right;font-weight:700;font-size:16px;color:{{ $mc }};">R$ {{ number_format($r['margem'], 2, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
