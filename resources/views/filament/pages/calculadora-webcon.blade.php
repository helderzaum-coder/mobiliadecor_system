<x-filament-panels::page>
    <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6 space-y-5">

        <div style="display:flex;gap:16px;">
            <div style="flex:2;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Custo do Produto (R$)</label>
                <input type="number" step="0.01" wire:model.lazy="custo" placeholder="0,00"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
            </div>
            <div style="flex:1;">
                <label style="font-size:12px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px;">Margem Desejada (%)</label>
                <input type="number" step="0.1" wire:model.lazy="margem" placeholder="20"
                    style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:16px;">
            </div>
        </div>

        <div style="font-size:11px;color:#6b7280;padding:8px 12px;border-radius:6px;background:#1f2937;">
            Imposto fixo: <strong style="color:#e5e7eb;">13%</strong> (nota cheia) &nbsp;|&nbsp; Comissões: 17%, 15%, 13%, 10%
        </div>

        <button wire:click="calcular"
            style="width:100%;padding:14px;font-size:15px;font-weight:700;border-radius:10px;border:none;cursor:pointer;background:#0891b2;color:#fff;">
            🌐 Calcular Preços
        </button>
    </div>

    @if($resultados)
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:20px;">
        @foreach($resultados as $r)
        <div style="flex:1;min-width:200px;padding:20px;border-radius:12px;border:1px solid #374151;background:#1f2937;">
            <div style="font-size:12px;color:#9ca3af;margin-bottom:4px;">Comissão</div>
            <div style="font-size:22px;font-weight:800;color:#0891b2;">{{ $r['comissao'] }}%</div>

            @if($r['preco'])
            <div style="margin-top:12px;font-size:12px;color:#9ca3af;">Preço de Venda</div>
            <div style="font-size:20px;font-weight:700;color:#fff;">R$ {{ number_format($r['preco'], 2, ',', '.') }}</div>

            <div style="margin-top:10px;font-size:11px;color:#6b7280;line-height:1.8;">
                Comissão: <span style="color:#f59e0b;">R$ {{ number_format($r['valor_comissao'], 2, ',', '.') }}</span><br>
                Imposto: <span style="color:#ef4444;">R$ {{ number_format($r['valor_imposto'], 2, ',', '.') }}</span><br>
                Lucro: <span style="color:#10b981;font-weight:600;">R$ {{ number_format($r['lucro'], 2, ',', '.') }}</span>
                <span style="color:#6b7280;">({{ $r['margem_real'] }}%)</span>
            </div>
            @else
            <div style="margin-top:12px;font-size:13px;color:#ef4444;">Margem inviável</div>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</x-filament-panels::page>
