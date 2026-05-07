<x-filament-panels::page>
    <form wire:submit.prevent="">
        {{ $this->form }}
    </form>

    @php
        $resumo = $this->resumo;
    @endphp

    {{-- KPIs --}}
    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:16px;">
        <div style="flex:1;min-width:150px;background:var(--kpi-bg,#fff);border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:3px solid #3b82f6;">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Pedidos com Frete</div>
            <div style="font-size:24px;font-weight:800;color:var(--kpi-text,#1f2937);">{{ $resumo['count'] }}</div>
        </div>
        <div style="flex:1;min-width:150px;background:var(--kpi-bg,#fff);border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:3px solid #10b981;">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Frete Cobrado</div>
            <div style="font-size:24px;font-weight:800;color:#059669;">R$ {{ number_format($resumo['total_cobrado'], 2, ',', '.') }}</div>
        </div>
        <div style="flex:1;min-width:150px;background:var(--kpi-bg,#fff);border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:3px solid #f59e0b;">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Frete Pago</div>
            <div style="font-size:24px;font-weight:800;color:#d97706;">R$ {{ number_format($resumo['total_pago'], 2, ',', '.') }}</div>
        </div>
        <div style="flex:1;min-width:150px;background:var(--kpi-bg,#fff);border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:3px solid {{ $resumo['margem_frete'] >= 0 ? '#10b981' : '#ef4444' }};">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Margem Frete Total</div>
            <div style="font-size:24px;font-weight:800;color:{{ $resumo['margem_frete'] >= 0 ? '#059669' : '#dc2626' }};">R$ {{ number_format($resumo['margem_frete'], 2, ',', '.') }}</div>
        </div>
        <div style="flex:1;min-width:150px;background:var(--kpi-bg,#fff);border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:3px solid #ef4444;">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Com Prejuízo</div>
            <div style="font-size:24px;font-weight:800;color:#dc2626;">{{ $resumo['com_prejuizo'] }}</div>
            <div style="font-size:11px;color:#9ca3af;">R$ {{ number_format($resumo['total_prejuizo'], 2, ',', '.') }} perdidos</div>
        </div>
        <div style="flex:1;min-width:150px;background:var(--kpi-bg,#fff);border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.1);border-top:3px solid #f59e0b;">
            <div style="font-size:11px;color:#6b7280;text-transform:uppercase;">Acima do Cotado</div>
            <div style="font-size:24px;font-weight:800;color:#d97706;">{{ $resumo['acima_cotado'] }}</div>
        </div>
    </div>
    <style>
        .dark { --kpi-bg: #1f2937; --kpi-text: #f9fafb; }
        :root { --kpi-bg: #fff; --kpi-text: #1f2937; }
    </style>

    {{-- Tabela --}}
    <div class="mt-6 overflow-x-auto rounded-xl bg-white dark:bg-gray-800 shadow">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="p-3 text-left text-gray-600 dark:text-gray-300">Pedido</th>
                    <th class="p-3 text-left text-gray-600 dark:text-gray-300">Canal</th>
                    <th class="p-3 text-left text-gray-600 dark:text-gray-300">Data</th>
                    <th class="p-3 text-left text-gray-600 dark:text-gray-300">Cliente</th>
                    <th class="p-3 text-left text-gray-600 dark:text-gray-300">Destino</th>
                    <th class="p-3 text-right text-gray-600 dark:text-gray-300">Cobrado</th>
                    <th class="p-3 text-right text-gray-600 dark:text-gray-300">Cotado</th>
                    <th class="p-3 text-right text-gray-600 dark:text-gray-300">Pago</th>
                    <th class="p-3 text-right text-gray-600 dark:text-gray-300">Comissão Frete</th>
                    <th class="p-3 text-right text-gray-600 dark:text-gray-300">Imposto Frete</th>
                    <th class="p-3 text-right text-gray-600 dark:text-gray-300">Margem Frete</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->vendas as $venda)
                    @php
                        $cobrado = (float) $venda->valor_frete_cliente;
                        $pago = (float) $venda->valor_frete_transportadora;
                        $cotado = (float) ($venda->frete_cotado ?? 0);
                        $margem = (float) $venda->margem_frete;

                        // Calcular comissão e imposto sobre frete
                        $canalVenda = $venda->canal;
                        $comissaoSobreFrete = (bool) ($canalVenda->comissao_sobre_frete ?? false);
                        $impostoSobreFrete = (bool) ($canalVenda->imposto_sobre_frete ?? false);
                        $pctImposto = (float) $venda->percentual_imposto;

                        $comissaoFrete = 0;
                        if ($comissaoSobreFrete && $cobrado > 0 && $canalVenda) {
                            $regraCanal = $canalVenda->regrasComissao()->where('ativo', true)->first();
                            if ($regraCanal) {
                                $comissaoFrete = round($cobrado * (float) $regraCanal->percentual / 100, 2);
                            }
                        }
                        $impostoFrete = ($impostoSobreFrete && $cobrado > 0 && $pctImposto > 0)
                            ? round($cobrado * $pctImposto / 100, 2) : 0;
                    @endphp
                    <tr class="border-t border-gray-200 dark:border-gray-700 {{ $margem < 0 ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                        <td class="p-3 font-mono font-semibold text-gray-800 dark:text-white">{{ $venda->numero_pedido_canal }}</td>
                        <td class="p-3 text-gray-600 dark:text-gray-300">{{ $venda->canal?->nome_canal ?? '-' }}</td>
                        <td class="p-3 text-gray-500">{{ $venda->data_venda?->format('d/m/Y') }}</td>
                        <td class="p-3 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($venda->cliente_nome, 25) }}</td>
                        <td class="p-3 text-gray-500">{{ $venda->staging_cidade ? $venda->staging_cidade . '/' . $venda->staging_uf : '-' }}</td>
                        <td class="p-3 text-right text-gray-800 dark:text-white">R$ {{ number_format($cobrado, 2, ',', '.') }}</td>
                        <td class="p-3 text-right {{ $cotado > 0 && $pago > $cotado ? 'text-yellow-600' : 'text-gray-500' }}">
                            {{ $cotado > 0 ? 'R$ ' . number_format($cotado, 2, ',', '.') : '-' }}
                        </td>
                        <td class="p-3 text-right font-semibold text-gray-800 dark:text-white">R$ {{ number_format($pago, 2, ',', '.') }}</td>
                        <td class="p-3 text-right {{ $comissaoFrete > 0 ? 'text-orange-600' : 'text-gray-400' }}">{{ $comissaoFrete > 0 ? 'R$ ' . number_format($comissaoFrete, 2, ',', '.') : '-' }}</td>
                        <td class="p-3 text-right {{ $impostoFrete > 0 ? 'text-orange-600' : 'text-gray-400' }}">{{ $impostoFrete > 0 ? 'R$ ' . number_format($impostoFrete, 2, ',', '.') : '-' }}</td>
                        <td class="p-3 text-right font-bold {{ $margem >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            R$ {{ number_format($margem, 2, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="p-6 text-center text-gray-500">Nenhum registro encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
