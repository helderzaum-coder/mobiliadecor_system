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

        {{-- Cubagem ML (toggle) --}}
        <div>
            <button wire:click="$set('usar_cubagem', {{ $usar_cubagem ? 'false' : 'true' }})"
                style="padding:8px 14px;font-size:12px;border-radius:8px;border:1px solid {{ $usar_cubagem ? '#3b82f6' : '#374151' }};cursor:pointer;
                background:{{ $usar_cubagem ? 'rgba(59,130,246,.15)' : 'transparent' }};color:{{ $usar_cubagem ? '#3b82f6' : '#9ca3af' }};">
                📦 {{ $usar_cubagem ? 'Fechar Cubagem' : 'Calcular Cubagem (ML)' }}
            </button>

            @if($usar_cubagem)
            <div style="display:flex;gap:12px;margin-top:10px;padding:12px 16px;border-radius:8px;border:1px solid #3b82f6;background:#111827;">
                <div style="flex:1;">
                    <label style="font-size:11px;color:#9ca3af;display:block;margin-bottom:2px;">Altura (cm)</label>
                    <input type="number" step="0.1" wire:model="cubagem_altura" placeholder="0"
                        style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #374151;background:#1f2937;color:#fff;font-size:14px;">
                </div>
                <div style="flex:1;">
                    <label style="font-size:11px;color:#9ca3af;display:block;margin-bottom:2px;">Comprimento (cm)</label>
                    <input type="number" step="0.1" wire:model="cubagem_comprimento" placeholder="0"
                        style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #374151;background:#1f2937;color:#fff;font-size:14px;">
                </div>
                <div style="flex:1;">
                    <label style="font-size:11px;color:#9ca3af;display:block;margin-bottom:2px;">Largura (cm)</label>
                    <input type="number" step="0.1" wire:model="cubagem_largura" placeholder="0"
                        style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #374151;background:#1f2937;color:#fff;font-size:14px;">
                </div>
                <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;">
                    @if($cubagem_altura && $cubagem_comprimento && $cubagem_largura)
                        @php $pesoCubado = round(($cubagem_altura * $cubagem_comprimento * $cubagem_largura) / 6000, 3); @endphp
                        <div style="font-size:11px;color:#6b7280;">Peso Cubado:</div>
                        <div style="font-size:16px;font-weight:700;color:#3b82f6;">{{ number_format($pesoCubado, 3, ',', '.') }} kg</div>
                        @if($peso_unitario && $pesoCubado > $peso_unitario)
                            <div style="font-size:10px;color:#f59e0b;">⚠️ Cubado > Real (será usado)</div>
                        @endif
                    @else
                        <div style="font-size:11px;color:#6b7280;">A×C×L / 6000</div>
                    @endif
                </div>
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
            @if($peso_unitario || ($usar_cubagem && $cubagem_altura && $cubagem_comprimento && $cubagem_largura))
            <div style="flex:1;font-size:12px;">
                <span style="color:#6b7280;">Peso p/ Frete:</span>
                @php
                    $pesoReal = ($peso_unitario ?? 0) * $quantidade;
                    $pesoCubadoTotal = $usar_cubagem && $cubagem_altura && $cubagem_comprimento && $cubagem_largura
                        ? round(($cubagem_altura * $cubagem_comprimento * $cubagem_largura) / 6000, 3) * $quantidade
                        : 0;
                    $pesoUsado = max($pesoReal, $pesoCubadoTotal);
                @endphp
                <span style="color:#e5e7eb;font-weight:600;">{{ number_format($pesoUsado, 3, ',', '.') }} kg</span>
                @if($pesoCubadoTotal > $pesoReal)
                    <span style="font-size:10px;color:#3b82f6;">(cubado)</span>
                @endif
            </div>
            @endif
        </div>
        @endif

        {{-- Preço de Venda OU Margens De/Por --}}
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
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">% "Preço De" (margem vitrine)</label>
                <input type="number" step="0.1" wire:model="preco_de_pct" placeholder="30"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Preço cheio / "riscado"</div>
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">% "Preço Por" (margem promo)</label>
                <input type="number" step="0.1" wire:model="preco_por_pct" placeholder="20"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
                <div style="font-size:10px;color:#6b7280;margin-top:2px;">Preço promocional / final</div>
            </div>
            @endif
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
        @php $r = $resultados; @endphp

        @if($r['modo'] === 'margem')
        {{-- Modo Margem --}}
        @php
            $canaisCnpj2 = collect($r['canais'])->where('id_cnpj', 2)->values();
            $canaisCnpj1 = collect($r['canais'])->where('id_cnpj', 1)->values();
        @endphp

        {{-- HES Móveis --}}
        <div style="margin-top:20px;margin-bottom:8px;font-size:12px;font-weight:600;color:#9ca3af;">HES Móveis ({{ $canaisCnpj2->first()['imposto_pct'] ?? 0 }}%)</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            @foreach($canaisCnpj2 as $canal)
                @include('filament.pages.partials.calculadora-card-margem', ['canal' => $canal, 'r' => $r])
            @endforeach
        </div>

        {{-- HES Decor --}}
        <div style="margin-top:20px;margin-bottom:8px;font-size:12px;font-weight:600;color:#9ca3af;">HES Decor ({{ $canaisCnpj1->first()['imposto_pct'] ?? 0 }}%)</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            @foreach($canaisCnpj1 as $canal)
                @include('filament.pages.partials.calculadora-card-margem', ['canal' => $canal, 'r' => $r])
            @endforeach
        </div>

        @else
        {{-- Modo Preço Ideal --}}
        @php
            $canaisConfig = $r['canais_config'];
            $canaisIdeal2 = collect($r['canais'])->filter(fn($v, $k) => ($canaisConfig[$k]['id_cnpj'] ?? 0) === 2);
            $canaisIdeal1 = collect($r['canais'])->filter(fn($v, $k) => ($canaisConfig[$k]['id_cnpj'] ?? 0) === 1);
        @endphp

        {{-- HES Móveis --}}
        <div style="margin-top:20px;margin-bottom:8px;font-size:12px;font-weight:600;color:#9ca3af;">HES Móveis</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            @foreach($canaisIdeal2 as $key => $dados)
                @include('filament.pages.partials.calculadora-card-ideal', ['key' => $key, 'dados' => $dados, 'canaisConfig' => $canaisConfig, 'r' => $r])
            @endforeach
        </div>

        {{-- HES Decor --}}
        <div style="margin-top:20px;margin-bottom:8px;font-size:12px;font-weight:600;color:#9ca3af;">HES Decor</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            @foreach($canaisIdeal1 as $key => $dados)
                @include('filament.pages.partials.calculadora-card-ideal', ['key' => $key, 'dados' => $dados, 'canaisConfig' => $canaisConfig, 'r' => $r])
            @endforeach
        </div>
        @endif
    @endif
</x-filament-panels::page>
