<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Mercado Livre --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6">
            <h2 style="font-size:18px;font-weight:700;color:#f59e0b;margin-bottom:16px;">🟡 Conciliação Mercado Livre</h2>

            <div class="space-y-4 text-sm text-gray-300">
                <div class="rounded-lg bg-gray-700/30 p-4">
                    <h3 style="font-weight:700;color:#e5e7eb;margin-bottom:8px;">📥 Passo 1: Baixar Planilha de Liberações</h3>
                    <p>Acesse: <a href="https://www.mercadopago.com.br/balance/reports/release" target="_blank" style="color:#3b82f6;text-decoration:underline;">Mercado Pago → Relatórios → Liberações</a></p>
                    <p class="mt-2">Gere o relatório do período desejado (mês fechado).</p>
                </div>

                <div class="rounded-lg bg-gray-700/30 p-4">
                    <h3 style="font-weight:700;color:#e5e7eb;margin-bottom:8px;">🔍 Passo 2: Filtrar Antecipações na Planilha</h3>
                    <p>Na planilha de liberações, aplique os filtros:</p>
                    <ul style="list-style:disc;padding-left:20px;margin-top:8px;">
                        <li><strong>Coluna A (Date)</strong>: agrupe por horários próximos (cada grupo = 1 antecipação)</li>
                        <li><strong>Coluna D (RECORD_TYPE)</strong>: filtrar apenas <code style="background:#374151;padding:2px 6px;border-radius:4px;">release</code></li>
                        <li><strong>Coluna E (DESCRIPTION)</strong>: filtrar apenas <code style="background:#374151;padding:2px 6px;border-radius:4px;">payment</code> ou <code style="background:#374151;padding:2px 6px;border-radius:4px;">shipping</code></li>
                    </ul>
                    <p class="mt-2" style="color:#9ca3af;">💡 Todos os registros com horários próximos + release + (payment ou shipping) formam <strong>uma antecipação</strong> do dia.</p>
                </div>

                <div class="rounded-lg bg-gray-700/30 p-4">
                    <h3 style="font-weight:700;color:#e5e7eb;margin-bottom:8px;">✅ Passo 3: Conferir Valor no Mercado Pago</h3>
                    <p>Acesse: <a href="https://www.mercadopago.com.br/activities" target="_blank" style="color:#3b82f6;text-decoration:underline;">Mercado Pago → Atividades</a></p>
                    <ul style="list-style:disc;padding-left:20px;margin-top:8px;">
                        <li>Selecione o período</li>
                        <li>Filtre por <strong>Operações = Depósito de dinheiro</strong></li>
                        <li>Compare o valor de cada depósito com a soma da planilha filtrada</li>
                    </ul>
                </div>

                <div class="rounded-lg bg-gray-700/30 p-4">
                    <h3 style="font-weight:700;color:#e5e7eb;margin-bottom:8px;">💰 Passo 4: Dar Baixa no Sistema</h3>
                    <p>No sistema, vá em <strong>Financeiro → Lote Recebimentos</strong>:</p>
                    <ul style="list-style:disc;padding-left:20px;margin-top:8px;">
                        <li>Cole os números dos pedidos da antecipação (da planilha)</li>
                        <li>Confira o total (deve bater com o depósito)</li>
                        <li>Preencha o <strong>Identificador</strong>: ex: "Antecipação ML 30/04 #1"</li>
                        <li>Preencha a <strong>Data</strong> do depósito</li>
                        <li>Clique <strong>Confirmar Recebimento do Lote</strong></li>
                    </ul>
                </div>

                <div class="rounded-lg bg-yellow-900/20 border border-yellow-700 p-4">
                    <h3 style="font-weight:700;color:#f59e0b;margin-bottom:8px;">⚠️ Pedidos Cancelados com Antecipação</h3>
                    <p>Se um pedido foi antecipado mas depois cancelado:</p>
                    <ul style="list-style:disc;padding-left:20px;margin-top:8px;">
                        <li>Na Dashboard, clique <strong>"↩️ Estorno"</strong> no pedido</li>
                        <li>O sistema cria a conta a receber (para dar baixa na antecipação) + conta a pagar (estorno futuro)</li>
                        <li>Dê baixa normalmente no Lote de Recebimentos</li>
                        <li>Quando o ML debitar, confirme o pagamento em <strong>Contas a Pagar</strong></li>
                    </ul>
                </div>

                <div class="rounded-lg bg-red-900/20 border border-red-700 p-4">
                    <h3 style="font-weight:700;color:#ef4444;margin-bottom:8px;">🔄 Reembolsos (Refund)</h3>
                    <p>Na planilha de liberações, registros com:</p>
                    <ul style="list-style:disc;padding-left:20px;margin-top:8px;">
                        <li><strong>Coluna D (RECORD_TYPE)</strong> = <code style="background:#374151;padding:2px 6px;border-radius:4px;">release</code></li>
                        <li><strong>Coluna E (DESCRIPTION)</strong> = <code style="background:#374151;padding:2px 6px;border-radius:4px;">refund</code></li>
                    </ul>
                    <p class="mt-2">Representam <strong>reembolsos</strong> ao comprador. O ML debita o valor do vendedor.</p>
                    <p class="mt-2">No sistema:</p>
                    <ul style="list-style:disc;padding-left:20px;margin-top:4px;">
                        <li>Na Dashboard, clique <strong>"🔄 Reembolso"</strong> no pedido já recebido</li>
                        <li>O sistema cria uma <strong>conta a pagar</strong> com o valor do reembolso</li>
                        <li>Quando o ML debitar, confirme em <strong>Contas a Pagar</strong></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Shopee --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6">
            <h2 style="font-size:18px;font-weight:700;color:#ea580c;margin-bottom:16px;">🟠 Conciliação Shopee</h2>

            <div class="space-y-4 text-sm text-gray-300">
                <div class="rounded-lg bg-gray-700/30 p-4">
                    <h3 style="font-weight:700;color:#e5e7eb;margin-bottom:8px;">📥 Planilhas Necessárias</h3>
                    <ol style="list-style:decimal;padding-left:20px;">
                        <li class="mb-2"><strong>Planilha de Pedidos</strong> (Order.all): Integrações → Planilha Shopee</li>
                        <li class="mb-2"><strong>Planilha de Afiliados</strong> (SellerConversionReport): Integrações → Afiliados Shopee
                            <br><a href="https://seller.shopee.com.br/portal/web-seller-affiliate/conversion_report" target="_blank" style="color:#3b82f6;text-decoration:underline;">📥 Baixar relatório</a>
                        </li>
                    </ol>
                </div>

                <div class="rounded-lg bg-gray-700/30 p-4">
                    <h3 style="font-weight:700;color:#e5e7eb;margin-bottom:8px;">📋 Ordem de Importação</h3>
                    <ol style="list-style:decimal;padding-left:20px;">
                        <li>Importar pedidos do Bling</li>
                        <li>Importar Planilha Shopee (Order.all)</li>
                        <li>Aprovar pedidos</li>
                        <li>Importar Planilha de Afiliados</li>
                    </ol>
                    <p class="mt-2" style="color:#9ca3af;">⚠️ Importar afiliados apenas UMA vez por período.</p>
                </div>
            </div>
        </div>

        {{-- Magalu --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow-md p-6">
            <h2 style="font-size:18px;font-weight:700;color:#2563eb;margin-bottom:16px;">🔵 Conciliação Magalu</h2>

            <div class="space-y-4 text-sm text-gray-300">
                <div class="rounded-lg bg-gray-700/30 p-4">
                    <h3 style="font-weight:700;color:#e5e7eb;margin-bottom:8px;">📥 Planilha Financeira</h3>
                    <p>Baixe a planilha financeira do portal Magalu e importe em <strong>Integrações → Planilha Magalu</strong>.</p>
                    <p class="mt-2">A planilha atualiza a comissão real (serviços + tarifa fixa) e o subsídio Magalu.</p>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
