<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    {{-- Info imposto --}}
    <div style="margin-top:12px;padding:10px 16px;border-radius:8px;background:#1e293b;border-left:4px solid #f59e0b;font-size:12px;color:#fbbf24;">
        {{ $this->infoImposto }}
    </div>

    @if($this->canal)
    <div style="margin-top:8px;padding:8px 16px;border-radius:8px;background:#1e293b;border-left:4px solid #06b6d4;font-size:12px;color:#67e8f9;">
        📊 Filtrando por canal: <strong>{{ $this->canal }}</strong> — Despesas fixas não são exibidas (pertencem à empresa toda).
    </div>
    @endif

    @if($this->visao === 'mensal')
        {{-- VISÃO MENSAL CONSOLIDADA --}}
        @php $dre = $this->dre; @endphp

        {{-- Cards resumo --}}
        <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:180px;padding:16px;border-radius:12px;background:#1f2937;border-top:4px solid #10b981;">
                <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Receita Bruta</div>
                <div style="font-size:22px;font-weight:800;color:#10b981;">R$ {{ number_format($dre['receita_bruta'], 2, ',', '.') }}</div>
                <div style="font-size:11px;color:#6b7280;">{{ $dre['qtd_vendas'] }} vendas</div>
            </div>
            <div style="flex:1;min-width:180px;padding:16px;border-radius:12px;background:#1f2937;border-top:4px solid #06b6d4;">
                <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">Margem Contribuição</div>
                <div style="font-size:22px;font-weight:800;color:#06b6d4;">R$ {{ number_format($dre['margem_contribuicao'], 2, ',', '.') }}</div>
                <div style="font-size:11px;color:#6b7280;">{{ $dre['margem_contribuicao_pct'] }}% da receita</div>
            </div>
            <div style="flex:1;min-width:180px;padding:16px;border-radius:12px;background:#1f2937;border-top:4px solid {{ $dre['resultado_operacional'] >= 0 ? '#10b981' : '#ef4444' }};">
                <div style="font-size:11px;color:#9ca3af;text-transform:uppercase;">{{ $this->canal ? 'Margem do Canal' : 'Resultado Operacional' }}</div>
                <div style="font-size:22px;font-weight:800;color:{{ $dre['resultado_operacional'] >= 0 ? '#10b981' : '#ef4444' }};">R$ {{ number_format($dre['resultado_operacional'], 2, ',', '.') }}</div>
                <div style="font-size:11px;color:#6b7280;">{{ $dre['resultado_pct'] }}% da receita</div>
            </div>
        </div>

        {{-- Tabela DRE --}}
        <div class="mt-4 rounded-xl bg-white dark:bg-gray-800 shadow-md overflow-hidden">
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                {{-- RECEITA BRUTA --}}
                <thead>
                    <tr style="background:#111827;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#e5e7eb;font-size:14px;" colspan="2">Receita Bruta</th>
                        <th style="padding:12px;text-align:right;color:#10b981;font-size:16px;font-weight:700;">R$ {{ number_format($dre['receita_bruta'], 2, ',', '.') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;width:30px;">+</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Receita de Produtos</td>
                        <td style="padding:8px 12px;text-align:right;color:#e5e7eb;">R$ {{ number_format($dre['receita_produtos'], 2, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">+</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Receita de Frete</td>
                        <td style="padding:8px 12px;text-align:right;color:#e5e7eb;">R$ {{ number_format($dre['receita_frete'], 2, ',', '.') }}</td>
                    </tr>
                </tbody>

                {{-- DEDUÇÕES --}}
                <thead>
                    <tr style="background:#111827;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#e5e7eb;font-size:14px;" colspan="2">(-) Deduções</th>
                        <th style="padding:12px;text-align:right;color:#ef4444;font-size:16px;font-weight:700;">-R$ {{ number_format($dre['total_deducoes'], 2, ',', '.') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Cancelamentos/Devoluções ({{ $dre['qtd_canceladas'] }})</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($dre['deducao_cancelamentos'], 2, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Impostos Provisionados</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($dre['deducao_impostos'], 2, ',', '.') }}</td>
                    </tr>
                </tbody>

                {{-- RECEITA LÍQUIDA --}}
                <thead>
                    <tr style="background:#0f172a;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#f59e0b;font-size:14px;" colspan="2">(=) Receita Líquida</th>
                        <th style="padding:12px;text-align:right;color:#f59e0b;font-size:16px;font-weight:700;">R$ {{ number_format($dre['receita_liquida'], 2, ',', '.') }}</th>
                    </tr>
                </thead>

                {{-- CMV --}}
                <thead>
                    <tr style="background:#111827;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#e5e7eb;font-size:14px;" colspan="2">(-) CMV - Custo das Mercadorias</th>
                        <th style="padding:12px;text-align:right;color:#ef4444;font-size:16px;font-weight:700;">-R$ {{ number_format($dre['cmv'], 2, ',', '.') }}</th>
                    </tr>
                </thead>

                {{-- CUSTOS VARIÁVEIS --}}
                <thead>
                    <tr style="background:#111827;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#e5e7eb;font-size:14px;" colspan="2">(-) Custos Variáveis</th>
                        <th style="padding:12px;text-align:right;color:#ef4444;font-size:16px;font-weight:700;">-R$ {{ number_format($dre['total_custos_variaveis'], 2, ',', '.') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Frete Transportadora</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($dre['custo_frete'], 2, ',', '.') }}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Comissão Marketplace</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($dre['custo_comissao'], 2, ',', '.') }}</td>
                    </tr>
                    @if($dre['custo_afiliado'] > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Comissão Afiliados</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($dre['custo_afiliado'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($dre['custo_subsidio_pix'] > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Subsídio PIX</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($dre['custo_subsidio_pix'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($dre['custo_subsidio_magalu'] > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">Subsídio Magalu</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($dre['custo_subsidio_magalu'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                </tbody>

                {{-- MARGEM DE CONTRIBUIÇÃO --}}
                <thead>
                    <tr style="background:#0f172a;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#06b6d4;font-size:14px;" colspan="2">(=) Margem de Contribuição</th>
                        <th style="padding:12px;text-align:right;color:#06b6d4;font-size:16px;font-weight:700;">R$ {{ number_format($dre['margem_contribuicao'], 2, ',', '.') }} <span style="font-size:12px;font-weight:400;">({{ $dre['margem_contribuicao_pct'] }}%)</span></th>
                    </tr>
                </thead>

                {{-- DESPESAS FIXAS (só quando não filtra por canal) --}}
                @if(!$this->canal)
                <thead>
                    <tr style="background:#111827;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#e5e7eb;font-size:14px;" colspan="2">(-) Despesas Fixas / Operacionais</th>
                        <th style="padding:12px;text-align:right;color:#ef4444;font-size:16px;font-weight:700;">-R$ {{ number_format($dre['total_despesas_fixas'], 2, ',', '.') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dre['despesas_por_categoria'] as $cat)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">{{ $cat['categoria'] }} <span style="color:#6b7280;font-size:11px;">({{ $cat['qtd'] }}x)</span></td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">R$ {{ number_format($cat['valor'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    @if(empty($dre['despesas_por_categoria']))
                    <tr><td colspan="3" style="padding:12px;text-align:center;color:#6b7280;">Nenhuma despesa no período</td></tr>
                    @endif
                </tbody>
                @endif

                {{-- RESULTADO OPERACIONAL --}}
                <thead>
                    <tr style="background:#0f172a;border-bottom:2px solid #374151;">
                        <th style="padding:14px;text-align:left;font-size:15px;color:{{ $dre['resultado_operacional'] >= 0 ? '#10b981' : '#ef4444' }};" colspan="2">(=) {{ $this->canal ? 'MARGEM DO CANAL' : 'RESULTADO OPERACIONAL' }}</th>
                        <th style="padding:14px;text-align:right;font-size:18px;font-weight:800;color:{{ $dre['resultado_operacional'] >= 0 ? '#10b981' : '#ef4444' }};">R$ {{ number_format($dre['resultado_operacional'], 2, ',', '.') }} <span style="font-size:12px;font-weight:400;">({{ $dre['resultado_pct'] }}%)</span></th>
                    </tr>
                </thead>

                {{-- RECLAMAÇÕES ML --}}
                @if($dre['reclamacoes_bloqueios'] > 0 || $dre['reclamacoes_liberacoes'] > 0)
                <thead>
                    <tr style="background:#111827;border-bottom:2px solid #374151;">
                        <th style="padding:12px;text-align:left;color:#e5e7eb;font-size:14px;" colspan="2">⚖️ Reclamações ML</th>
                        <th style="padding:12px;text-align:right;font-size:16px;font-weight:700;color:{{ $dre['saldo_reclamacoes'] >= 0 ? '#10b981' : '#ef4444' }};">{{ $dre['saldo_reclamacoes'] >= 0 ? '+' : '' }}R$ {{ number_format($dre['saldo_reclamacoes'], 2, ',', '.') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if($dre['reclamacoes_bloqueios'] > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">-</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">🔒 Bloqueios ({{ $dre['qtd_bloqueios'] }} reclamação(ões) abertas)</td>
                        <td style="padding:8px 12px;text-align:right;color:#ef4444;">-R$ {{ number_format($dre['reclamacoes_bloqueios'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($dre['reclamacoes_liberacoes'] > 0)
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 12px;color:#9ca3af;">+</td>
                        <td style="padding:8px 12px;color:#e5e7eb;">✅ Liberações ({{ $dre['qtd_liberacoes'] }} reclamação(ões) resolvidas)</td>
                        <td style="padding:8px 12px;text-align:right;color:#10b981;">+R$ {{ number_format($dre['reclamacoes_liberacoes'], 2, ',', '.') }}</td>
                    </tr>
                    @endif
                </tbody>

                {{-- RESULTADO FINAL --}}
                <thead>
                    <tr style="background:#0f172a;border-top:2px solid #374151;">
                        <th style="padding:14px;text-align:left;font-size:15px;color:{{ $dre['resultado_final'] >= 0 ? '#10b981' : '#ef4444' }};" colspan="2">(=) RESULTADO FINAL</th>
                        <th style="padding:14px;text-align:right;font-size:18px;font-weight:800;color:{{ $dre['resultado_final'] >= 0 ? '#10b981' : '#ef4444' }};">R$ {{ number_format($dre['resultado_final'], 2, ',', '.') }} <span style="font-size:12px;font-weight:400;">({{ $dre['resultado_final_pct'] }}%)</span></th>
                    </tr>
                </thead>
                @endif
            </table>
        </div>

    @else
        {{-- VISÃO DIÁRIA --}}
        @php $dias = $this->dreDiario; @endphp

        <div class="mt-4 rounded-xl bg-white dark:bg-gray-800 shadow-md overflow-x-auto">
            <table style="width:100%;font-size:12px;border-collapse:collapse;min-width:800px;">
                <thead>
                    <tr style="background:#111827;border-bottom:2px solid #374151;">
                        <th style="padding:10px;text-align:left;color:#9ca3af;position:sticky;left:0;background:#111827;min-width:200px;">Linha</th>
                        @foreach($dias as $dia)
                            <th style="padding:10px;text-align:right;color:#9ca3af;min-width:90px;">{{ $dia['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    {{-- Receita Bruta --}}
                    <tr style="border-bottom:1px solid #1f2937;background:#0f172a;">
                        <td style="padding:8px 10px;color:#10b981;font-weight:600;position:sticky;left:0;background:#0f172a;">Receita Bruta</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:#10b981;font-weight:600;">{{ $dia['dre']['receita_bruta'] > 0 ? number_format($dia['dre']['receita_bruta'], 0, ',', '.') : '-' }}</td>
                        @endforeach
                    </tr>
                    {{-- Deduções --}}
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 10px;color:#ef4444;position:sticky;left:0;background:#1f2937;">(-) Deduções</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:#ef4444;">{{ $dia['dre']['total_deducoes'] > 0 ? '-' . number_format($dia['dre']['total_deducoes'], 0, ',', '.') : '-' }}</td>
                        @endforeach
                    </tr>
                    {{-- Receita Líquida --}}
                    <tr style="border-bottom:1px solid #1f2937;background:#0f172a;">
                        <td style="padding:8px 10px;color:#f59e0b;font-weight:600;position:sticky;left:0;background:#0f172a;">(=) Receita Líquida</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:#f59e0b;font-weight:600;">{{ $dia['dre']['receita_liquida'] != 0 ? number_format($dia['dre']['receita_liquida'], 0, ',', '.') : '-' }}</td>
                        @endforeach
                    </tr>
                    {{-- CMV --}}
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 10px;color:#ef4444;position:sticky;left:0;background:#1f2937;">(-) CMV</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:#ef4444;">{{ $dia['dre']['cmv'] > 0 ? '-' . number_format($dia['dre']['cmv'], 0, ',', '.') : '-' }}</td>
                        @endforeach
                    </tr>
                    {{-- Custos Variáveis --}}
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 10px;color:#ef4444;position:sticky;left:0;background:#1f2937;">(-) Custos Variáveis</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:#ef4444;">{{ $dia['dre']['total_custos_variaveis'] > 0 ? '-' . number_format($dia['dre']['total_custos_variaveis'], 0, ',', '.') : '-' }}</td>
                        @endforeach
                    </tr>
                    {{-- Margem Contribuição --}}
                    <tr style="border-bottom:1px solid #1f2937;background:#0f172a;">
                        <td style="padding:8px 10px;color:#06b6d4;font-weight:600;position:sticky;left:0;background:#0f172a;">(=) Margem Contribuição</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:{{ $dia['dre']['margem_contribuicao'] >= 0 ? '#06b6d4' : '#ef4444' }};font-weight:600;">{{ $dia['dre']['qtd_vendas'] > 0 ? number_format($dia['dre']['margem_contribuicao'], 0, ',', '.') : '-' }}</td>
                        @endforeach
                    </tr>
                    {{-- Qtd Vendas --}}
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 10px;color:#9ca3af;position:sticky;left:0;background:#1f2937;">Qtd Vendas</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:#9ca3af;">{{ $dia['dre']['qtd_vendas'] ?: '-' }}</td>
                        @endforeach
                    </tr>
                    {{-- Reclamações ML --}}
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 10px;color:#f59e0b;position:sticky;left:0;background:#1f2937;">⚖️ Reclamações ML</td>
                        @foreach($dias as $dia)
                            @php $saldo = $dia['dre']['saldo_reclamacoes'] ?? 0; @endphp
                            <td style="padding:8px 10px;text-align:right;color:{{ $saldo > 0 ? '#10b981' : ($saldo < 0 ? '#ef4444' : '#6b7280') }};">
                                {{ $saldo != 0 ? ($saldo > 0 ? '+' : '') . number_format($saldo, 0, ',', '.') : '-' }}
                            </td>
                        @endforeach
                    </tr>
                    {{-- Margem % --}}
                    <tr style="border-bottom:1px solid #1f2937;">
                        <td style="padding:8px 10px;color:#9ca3af;position:sticky;left:0;background:#1f2937;">Margem %</td>
                        @foreach($dias as $dia)
                            <td style="padding:8px 10px;text-align:right;color:{{ $dia['dre']['margem_contribuicao_pct'] >= 0 ? '#06b6d4' : '#ef4444' }};">{{ $dia['dre']['qtd_vendas'] > 0 ? $dia['dre']['margem_contribuicao_pct'] . '%' : '-' }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
