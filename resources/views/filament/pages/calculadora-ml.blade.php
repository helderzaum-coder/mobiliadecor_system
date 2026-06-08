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
        <div style="display:flex;gap:16px;margin-top:20px;flex-wrap:wrap;">
            @foreach($r['canais'] as $canal)
                @php
                    $mc = $canal['margem'] >= 0 ? '#10b981' : '#ef4444';
                    $statusMsg = $canal['margem_pct'] >= 25 ? '🎉 Excelente' : ($canal['margem_pct'] >= 15 ? '✅ Saudável' : ($canal['margem_pct'] >= 5 ? '⚠️ Baixa' : '🚨 Crítica'));
                    $tipoNotaLabel = match($canal['tipo_nota']) { 'meia_nota' => '½ nota', 'produto' => 's/ frete', default => 'cheia' };
                @endphp
                <div style="flex:1;min-width:260px;border-radius:12px;border:2px solid {{ $canal['cor'] }};background:rgba(0,0,0,.2);padding:16px;">
                    {{-- Header --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <div style="font-size:14px;font-weight:700;color:{{ $canal['cor'] }};">{{ $canal['icone'] }} {{ $canal['canal'] }}</div>
                        <div style="font-size:10px;padding:3px 6px;border-radius:4px;background:{{ $mc }}22;color:{{ $mc }};font-weight:600;">{{ $statusMsg }}</div>
                    </div>
                    <div style="font-size:10px;color:#6b7280;margin-bottom:10px;">
                        {{ $canal['cnpj_label'] }} • {{ $canal['imposto_pct'] }}% ({{ $tipoNotaLabel }})
                    </div>

                    {{-- Margem --}}
                    <div style="text-align:center;padding:8px;margin-bottom:10px;border-radius:8px;border:1px solid {{ $mc }};background:{{ $mc }}11;">
                        <div style="font-size:10px;color:#9ca3af;">Margem</div>
                        <div style="font-size:20px;font-weight:800;color:{{ $mc }};">R$ {{ number_format($canal['margem'], 2, ',', '.') }} <span style="font-size:12px;">({{ $canal['margem_pct'] }}%)</span></div>
                    </div>

                    {{-- Detalhes --}}
                    <div style="font-size:11px;">
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Comissão ({{ $canal['comissao_pct'] }}%{{ $canal['comissao_fixa'] ? ' + R$'.$canal['comissao_fixa'] : '' }})</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($canal['comissao'], 2, ',', '.') }}</span>
                        </div>
                        @if($canal['frete'] > 0)
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Frete ({{ $r['faixa_peso'] ?? 'N/A' }})</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($canal['frete'], 2, ',', '.') }}</span>
                        </div>
                        @endif
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Recebe</span>
                            <span style="color:#f59e0b;font-weight:600;">R$ {{ number_format($canal['recebe'], 2, ',', '.') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Custo</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($r['custo_total'], 2, ',', '.') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Imposto ({{ $canal['imposto_pct'] }}% {{ $tipoNotaLabel }})</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($canal['imposto'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @else
        {{-- Modo Preço Ideal --}}
        @php $canaisConfig = $r['canais_config']; @endphp
        <div style="display:flex;gap:16px;margin-top:20px;flex-wrap:wrap;">
            @foreach($r['canais'] as $key => $dados)
                @php
                    $cfg = $canaisConfig[$key];
                    $tipoNotaLabel = match($cfg['tipo_nota']) { 'meia_nota' => '½ nota', 'produto' => 's/ frete', default => 'cheia' };
                    $det = $dados['preco_por'] ?? $dados['preco_de'] ?? null;
                    $mc = $det ? ($det['margem'] >= 0 ? '#10b981' : '#ef4444') : '#6b7280';
                @endphp
                <div style="flex:1;min-width:260px;border-radius:12px;border:2px solid {{ $cfg['cor'] }};background:rgba(0,0,0,.2);padding:16px;">
                    {{-- Header --}}
                    <div style="font-size:14px;font-weight:700;color:{{ $cfg['cor'] }};margin-bottom:4px;">{{ $cfg['icone'] }} {{ $cfg['label'] }}</div>
                    <div style="font-size:10px;color:#6b7280;margin-bottom:12px;">
                        {{ $cfg['cnpj_label'] }} • {{ $det['imposto_pct'] ?? 0 }}% ({{ $tipoNotaLabel }})
                    </div>

                    {{-- Preços De / Por --}}
                    <div style="text-align:center;margin-bottom:12px;">
                        @if(isset($dados['preco_de']))
                        <div style="margin-bottom:6px;">
                            <span style="font-size:10px;color:#9ca3af;">De:</span>
                            <span style="font-size:18px;font-weight:700;color:#9ca3af;text-decoration:line-through;margin-left:4px;">R$ {{ number_format($dados['preco_de']['preco_venda'], 2, ',', '.') }}</span>
                            <span style="font-size:9px;color:#6b7280;">({{ $r['preco_de_pct'] }}%)</span>
                        </div>
                        @endif
                        @if(isset($dados['preco_por']))
                        <div>
                            <span style="font-size:10px;color:#10b981;">Por:</span>
                            <span style="font-size:24px;font-weight:800;color:{{ $cfg['cor'] }};margin-left:4px;">R$ {{ number_format($dados['preco_por']['preco_venda'], 2, ',', '.') }}</span>
                            <span style="font-size:9px;color:#6b7280;">({{ $r['preco_por_pct'] }}%)</span>
                        </div>
                        @endif
                    </div>

                    {{-- Detalhes --}}
                    @if($det)
                    <div style="text-align:center;padding:6px;margin-bottom:10px;border-radius:8px;border:1px solid {{ $mc }};background:{{ $mc }}11;">
                        <div style="font-size:9px;color:#9ca3af;">Margem (preço final)</div>
                        <div style="font-size:16px;font-weight:700;color:{{ $mc }};">R$ {{ number_format($det['margem'], 2, ',', '.') }} ({{ $det['margem_pct'] }}%)</div>
                    </div>
                    <div style="font-size:11px;">
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Comissão ({{ $det['comissao_pct'] }}%{{ $det['comissao_fixa'] ? ' + R$'.$det['comissao_fixa'] : '' }})</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($det['comissao'], 2, ',', '.') }}</span>
                        </div>
                        @if($det['frete'] > 0)
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Frete ({{ $r['faixa_peso'] ?? 'N/A' }})</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($det['frete'], 2, ',', '.') }}</span>
                        </div>
                        @endif
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Recebe</span>
                            <span style="color:#f59e0b;font-weight:600;">R$ {{ number_format($det['recebe'], 2, ',', '.') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
                            <span style="color:#6b7280;">Custo</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($r['custo_total'], 2, ',', '.') }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:3px 0;">
                            <span style="color:#6b7280;">Imposto ({{ $det['imposto_pct'] }}% {{ $tipoNotaLabel }})</span>
                            <span style="color:#ef4444;">- R$ {{ number_format($det['imposto'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            @endforeach
        </div>
        @endif
    @endif
</x-filament-panels::page>
