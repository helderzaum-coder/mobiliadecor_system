# Bugfix Requirements Document

## Introduction

O sistema financeiro apresenta dois bugs inter-relacionados que comprometem a confiabilidade dos dados exibidos ao usuário:

1. **Inconsistência de valores entre telas**: O Dashboard de Vendas mostra `valor_total_venda` (valor bruto da venda), enquanto Contas a Receber mostra `valor_parcela` (repasse líquido após comissões), e Lote/Fatura mostram `soma_contas + entradas - descontos`. Não existe recalculação automática quando componentes do valor mudam (comissão atualizada, frete ajustado, afiliado lançado), fazendo com que valores fiquem defasados entre as telas.

2. **Canal aparecendo para CNPJ errado**: O dropdown de canais no Dashboard (e em outras telas com filtro) carrega TODOS os canais ativos sem considerar a conta/CNPJ selecionada como filtro. Canais que pertencem exclusivamente a uma empresa (primary=Mobilia Decor ou secondary=HES Móveis) aparecem quando a outra empresa está filtrada.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN o Dashboard de Vendas exibe totalizadores e o usuário espera ver o valor de repasse THEN o sistema mostra `valor_total_venda` (valor bruto) e `margem_venda_total` diretamente da tabela vendas, sem considerar descontos de canal, entradas avulsas ou ajustes aplicados em Faturas/Lotes

1.2 WHEN um valor que compõe o repasse é alterado após a geração da ContaReceber (ex: comissão_afiliado lançada, frete ajustado, cupom adicionado) e a ContaReceber já está vinculada a um Lote ou Fatura confirmada THEN o sistema NÃO recalcula o `valor_total` do Lote/Fatura para refletir o novo valor, gerando divergência entre o valor na ContaReceber pendente atualizada e o Lote/Fatura já confirmado

1.3 WHEN o usuário filtra por uma conta (primary/secondary) no Dashboard de Vendas e abre o dropdown de canal THEN o sistema exibe TODOS os canais ativos do banco de dados, pois não existe configuração que vincule canais a CNPJs específicos

1.4 WHEN um canal existe apenas para uma empresa (ex: canal exclusivo Mobilia Decor) e o usuário seleciona a outra empresa (HES Móveis) THEN o canal exclusivo da outra empresa ainda aparece como opção selecionável no dropdown, pois não há relação canal↔CNPJ no sistema

### Expected Behavior (Correct)

2.1 WHEN o Dashboard de Vendas exibe totalizadores THEN o sistema SHALL exibir AMBOS os valores: o `valor_total_venda` (bruto) E o valor de repasse esperado (soma dos `valor_parcela` das ContasReceber correspondentes), de forma clara e distinguível, para que o usuário tenha visão rápida tanto do faturamento bruto quanto do valor líquido que será recebido

2.2 WHEN um valor que compõe o repasse é alterado (comissão, frete, afiliado, cupom) e a ContaReceber ainda está pendente (não vinculada a lote/fatura confirmada) THEN o sistema SHALL recalcular automaticamente o `valor_parcela` da ContaReceber via `ContaReceberService::regenerar()` para manter consistência

2.3 WHEN o usuário filtra por uma conta (primary/secondary) no Dashboard de Vendas THEN o sistema SHALL filtrar o dropdown de canais para exibir apenas os canais que estão configurados como ativos para o CNPJ correspondente àquela conta, utilizando a tabela de relacionamento canal-CNPJ

2.4 WHEN um canal NÃO está vinculado ao CNPJ da conta selecionada THEN o sistema SHALL NÃO exibir esse canal no dropdown de filtro, garantindo que apenas canais relevantes apareçam para cada empresa

### Unchanged Behavior (Regression Prevention)

3.1 WHEN nenhuma conta está selecionada como filtro (opção "Todas") THEN o sistema SHALL CONTINUE TO exibir todos os canais ativos no dropdown, independentemente da conta

3.2 WHEN uma ContaReceber já está com status "recebido" ou vinculada a um lote_recebimento_id THEN o sistema SHALL CONTINUE TO manter o valor_parcela travado (sem recalcular), preservando o valor histórico registrado no momento do recebimento

3.3 WHEN a Fatura é confirmada e gera um Lote THEN o sistema SHALL CONTINUE TO calcular o valor_total do Lote como: soma das contas + entradas_avulsas - descontos, usando o valor da ContaReceber no momento da confirmação

3.4 WHEN um canal está vinculado a ambos os CNPJs (ex: Mercado Livre vende tanto pela Mobilia Decor quanto pela HES Móveis) THEN o sistema SHALL CONTINUE TO exibir esse canal no dropdown independentemente de qual conta está selecionada

3.5 WHEN o Dashboard exibe `valor_total_venda` e `margem_venda_total` para fins de análise de vendas brutas THEN o sistema SHALL CONTINUE TO calcular esses valores da mesma forma atual (soma direta da tabela vendas) para manter compatibilidade com relatórios existentes

3.6 WHEN nenhum CNPJ possui canais configurados (estado inicial antes da migração) THEN o sistema SHALL CONTINUE TO exibir todos os canais ativos, funcionando como fallback até que a configuração seja realizada
