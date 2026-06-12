@php
    $mc = $canal['margem'] >= 0 ? '#10b981' : '#ef4444';
    $statusMsg = $canal['margem_pct'] >= 25 ? '🎉 Excelente' : ($canal['margem_pct'] >= 15 ? '✅ Saudável' : ($canal['margem_pct'] >= 5 ? '⚠️ Baixa' : '🚨 Crítica'));
    $tipoNotaLabel = match($canal['tipo_nota']) { 'meia_nota' => '½ nota', 'produto' => 's/ frete', default => 'cheia' };
    $precoExibido = $canal['preco_pix'] ?? $r['preco_venda'];
@endphp
<div style="flex:1;min-width:260px;border-radius:12px;border:2px solid {{ $canal['cor'] }};background:rgba(0,0,0,.2);padding:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <div style="font-size:14px;font-weight:700;color:{{ $canal['cor'] }};">{{ $canal['icone'] }} {{ $canal['canal'] }}</div>
        <div style="font-size:10px;padding:3px 6px;border-radius:4px;background:{{ $mc }}22;color:{{ $mc }};font-weight:600;">{{ $statusMsg }}</div>
    </div>
    <div style="font-size:10px;color:#6b7280;margin-bottom:10px;">
        {{ $canal['imposto_pct'] }}% ({{ $tipoNotaLabel }})
    </div>

    @if(isset($canal['preco_pix']))
    <div style="text-align:center;padding:6px;margin-bottom:10px;border-radius:6px;background:rgba(5,150,105,.1);border:1px solid #059669;">
        <div style="font-size:10px;color:#9ca3af;">Preço Pix (-15%)</div>
        <div style="font-size:16px;font-weight:700;color:#059669;">R$ {{ number_format($canal['preco_pix'], 2, ',', '.') }}</div>
    </div>
    @endif

    <div style="text-align:center;padding:8px;margin-bottom:10px;border-radius:8px;border:1px solid {{ $mc }};background:{{ $mc }}11;">
        <div style="font-size:10px;color:#9ca3af;">Margem</div>
        <div style="font-size:20px;font-weight:800;color:{{ $mc }};">R$ {{ number_format($canal['margem'], 2, ',', '.') }} <span style="font-size:12px;">({{ $canal['margem_pct'] }}%)</span></div>
    </div>

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
        @if(($canal['antecipacao'] ?? 0) > 0)
        <div style="display:flex;justify-content:space-between;padding:3px 0;border-bottom:1px solid #1f2937;">
            <span style="color:#6b7280;">Antecipação ({{ $canal['antecipacao_pct'] }}%)</span>
            <span style="color:#ef4444;">- R$ {{ number_format($canal['antecipacao'], 2, ',', '.') }}</span>
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
        <div style="display:flex;justify-content:space-between;padding:3px 0;">
            <span style="color:#6b7280;">Imposto ({{ $canal['imposto_pct'] }}% {{ $tipoNotaLabel }})</span>
            <span style="color:#ef4444;">- R$ {{ number_format($canal['imposto'], 2, ',', '.') }}</span>
        </div>
    </div>
</div>
