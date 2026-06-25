@php
    $cfg = $canaisConfig[$key];
    $tipoNotaLabel = match($cfg['tipo_nota']) { 'meia_nota' => '½ nota', 'produto' => 's/ frete', default => 'cheia' };
    $det = $dados['preco_por'] ?? $dados['preco_de'] ?? null;
    $mc = $det ? ($det['margem'] >= 0 ? '#10b981' : '#ef4444') : '#6b7280';
@endphp
<div style="flex:1;min-width:260px;border-radius:12px;border:2px solid {{ $cfg['cor'] }};background:rgba(0,0,0,.2);padding:16px;">
    <div style="font-size:14px;font-weight:700;color:{{ $cfg['cor'] }};margin-bottom:4px;">{{ $cfg['icone'] }} {{ $cfg['label'] }}</div>
    <div style="font-size:10px;color:#6b7280;margin-bottom:12px;">
        {{ $det['imposto_pct'] ?? 0 }}% ({{ $tipoNotaLabel }})
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
            <span style="color:#6b7280;">Comissão ({{ $det['comissao_pct'] }}%{{ $det['comissao_fixa'] ? ' + R$'.$det['comissao_fixa'] : '' }}{{ ($det['comissao_cumulativa'] ?? 0) > 0 ? ' + 1,5%' : '' }})</span>
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
