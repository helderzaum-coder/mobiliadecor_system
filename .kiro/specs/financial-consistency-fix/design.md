# Financial Consistency Fix - Bugfix Design

## Overview

O sistema financeiro apresenta dois bugs inter-relacionados: (1) O Dashboard de Vendas exibe apenas `valor_total_venda` (bruto) nos totalizadores KPI, enquanto Contas a Receber mostra `valor_parcela` (repasse líquido), sem que o Dashboard ofereça visibilidade do valor de repasse esperado; e o `recalcular()` não propaga mudanças para ContasReceber pendentes de forma automática ao alterar componentes do repasse. (2) O dropdown de canais no Dashboard carrega todos os canais ativos (`CanalVenda::orderBy('nome_canal')->pluck(...)`) sem filtrar pelo CNPJ/conta selecionada, pois não existe tabela de relacionamento canal↔CNPJ.

A correção envolve: adicionar um totalizador de repasse no Dashboard; garantir que `ContaReceberService::regenerar()` seja chamado automaticamente após alterações de componentes; criar uma tabela pivot `canal_cnpj` para vincular canais a empresas; e filtrar o dropdown de canais com base na conta selecionada.

## Glossary

- **Bug_Condition (C)**: A condição que dispara o bug — (1) Dashboard exibindo apenas valor bruto sem repasse, e recalculação não propagando para ContaReceber pendentes; (2) dropdown de canais mostrando canais de CNPJ errado quando uma conta está filtrada
- **Property (P)**: Comportamento desejado — (1) Dashboard exibe AMBOS os valores (bruto e repasse líquido); ContaReceber pendentes são recalculadas ao alterar componentes; (2) dropdown filtra canais por CNPJ quando conta está selecionada
- **Preservation**: Comportamentos existentes que NÃO devem ser alterados — cálculo de `valor_total_venda`/`margem_venda_total` na tabela vendas, valor travado de ContasReceber já recebidas/em lote, cálculo de Lote como soma_contas + entradas - descontos, exibição de todos canais quando nenhuma conta está filtrada
- **DashboardVendas**: Page Filament em `app/Filament/Pages/DashboardVendas.php` que exibe KPIs de vendas com filtros de período, canal e conta
- **ContaReceberService::regenerar()**: Método em `app/Services/ContaReceberService.php` que recalcula `valor_parcela` das ContasReceber pendentes de uma venda
- **VendaRecalculoService::recalcularMargens()**: Método em `app/Services/VendaRecalculoService.php` que recalcula margens e já chama `ContaReceberService::regenerar()` ao final
- **CanalVenda**: Model em `app/Models/CanalVenda.php` com tabela `canais_venda`, sem vínculo atual com CNPJ/empresa
- **bling_account**: Campo na tabela `vendas` com valores 'primary' (Mobilia Decor, cnpj_id=1) ou 'secondary' (HES Móveis, cnpj_id=2)

## Bug Details

### Bug Condition

**Bug 1 - Inconsistência de valores**: O Dashboard mostra `valor_total_venda` nos KPIs (sum direto da tabela vendas), que é o valor bruto. O valor de repasse líquido (que considera comissões, frete, afiliado, cupom) só é calculado inline no blade para cada linha individual, mas NÃO é totalizado nos KPIs. Quando componentes do repasse são alterados (comissão, frete, afiliado), o `recalcularMargens()` é chamado e já chama `regenerar()` — mas o totalizador do Dashboard não reflete esse valor de repasse.

**Bug 2 - Canal sem filtro por CNPJ**: O dropdown de canais usa `CanalVenda::orderBy('nome_canal')->pluck('nome_canal', 'nome_canal')->toArray()` que carrega TODOS os canais ativos sem considerar a conta/CNPJ selecionada. Não existe tabela ou coluna que vincule canais a CNPJs.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type DashboardRequest
  OUTPUT: boolean
  
  // Bug 1: Dashboard não mostra totalizador de repasse
  condition1 := input.page == 'DashboardVendas' 
                AND input.action == 'viewTotals'
                AND repasseTotalNotDisplayed(input.filters)
  
  // Bug 2: Canal aparece para CNPJ errado
  condition2 := input.page == 'DashboardVendas'
                AND input.filter_conta IS NOT NULL
                AND input.dropdown == 'canal'
                AND existsCanal(c) WHERE c.ativo = true 
                    AND c NOT belongsTo(input.filter_conta.cnpj)
                    AND c IS displayed
  
  RETURN condition1 OR condition2
END FUNCTION
```

### Examples

- **Exemplo 1 (Bug valor)**: Venda de R$ 500 no Mercado Livre com comissão de R$ 80. Dashboard mostra "Total: R$ 500" nos KPIs, mas o repasse real é R$ 420. Usuário espera ver ambos os valores no totalizador.
- **Exemplo 2 (Bug valor)**: Após lançar afiliado de R$ 15 em uma venda Shopee, o `recalcularMargens()` + `regenerar()` atualiza a ContaReceber. O Dashboard KPI continua mostrando `valor_total_venda` (bruto) sem mostrar o repasse totalizado.
- **Exemplo 3 (Bug canal)**: Conta "HES Móveis" selecionada. Canal "Site Mobília" (exclusivo da Mobilia Decor) aparece no dropdown porque não há filtro por CNPJ.
- **Exemplo 4 (Edge case)**: Canal "Mercadolivre" pertence a ambas as empresas — deve aparecer independentemente da conta selecionada (comportamento correto, não é bug).

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Cálculo de `valor_total_venda` e `margem_venda_total` na tabela vendas deve continuar idêntico (sum direta)
- ContasReceber com status "recebido" ou vinculadas a `lote_recebimento_id`/`fatura_recebimento_id` NÃO devem ser recalculadas (valor travado)
- Cálculo de `valor_total` do Lote ao confirmar Fatura: `soma_contas + entradas_avulsas - descontos` (usa valor da ContaReceber no momento da confirmação)
- Quando nenhuma conta está selecionada ("Todas"), todos os canais ativos devem aparecer no dropdown
- Canais vinculados a ambos os CNPJs devem aparecer independentemente da conta selecionada
- Quando nenhum CNPJ possui canais configurados (fallback antes da migração), todos devem aparecer

**Scope:**
Todas as interações que NÃO envolvem: (a) visualização de totalizadores no Dashboard (que já funcionam para bruto); (b) filtragem de dropdown de canais por conta — devem permanecer completamente inalteradas. Isto inclui:
- Mouse clicks em ações da tabela de vendas
- Filtros de período, status, busca por pedido/CPF
- Exportação de planilha
- Operações em lote (buscar NFe, CTE, custos)
- Fluxo completo de Fatura/Lote em FaturaRecebimentos

## Hypothesized Root Cause

Based on the code analysis, the root causes are:

1. **Dashboard KPI mostra apenas bruto**: `getTotaisProperty()` em `DashboardVendas.php` (linha 1040) faz apenas `sum('valor_total_venda')` e `sum('margem_venda_total')` diretamente da tabela `vendas`. Não existe query para somar `valor_parcela` das `contas_receber` correspondentes. O totalizador de repasse simplesmente não foi implementado nos KPIs.

2. **Ausência de tabela canal↔CNPJ**: O model `CanalVenda` (tabela `canais_venda`) não possui nenhuma coluna ou relação com CNPJ/empresa. O dropdown no `form()` do DashboardVendas (linha ~832) usa `CanalVenda::orderBy('nome_canal')->pluck(...)` sem qualquer filtro condicional baseado em `$this->conta`.

3. **Dropdown não reage a mudanças na conta**: Mesmo que existisse a tabela de vínculo, o `Select::make('canal')` usa `->options(fn () => ...)` que é avaliado uma única vez. Seria necessário torná-lo reativo à seleção de conta usando `->options(fn ($get) => ...)` com dependência no campo `conta`.

4. **Propagação de recalculação já funciona parcialmente**: O `VendaRecalculoService::recalcularMargens()` já chama `ContaReceberService::regenerar()` ao final (linha ~560 do VendaRecalculoService). A questão é que esse valor recalculado não é refletido no totalizador KPI porque o KPI soma da tabela `vendas`, não das `contas_receber`.

## Correctness Properties

Property 1: Bug Condition - Dashboard Exibe Repasse Totalizado

_For any_ conjunto de vendas filtrado no Dashboard onde existem ContasReceber correspondentes, o totalizador KPI SHALL exibir tanto o `valor_total_venda` (bruto) quanto a soma dos `valor_parcela` das ContasReceber pendentes/recebidas correspondentes (repasse líquido), de forma distinguível na interface.

**Validates: Requirements 2.1, 2.2**

Property 2: Bug Condition - Canal Filtrado por CNPJ

_For any_ seleção de conta (primary/secondary) no filtro do Dashboard, o dropdown de canais SHALL exibir apenas os canais vinculados ao CNPJ correspondente àquela conta na tabela `canal_cnpj`, excluindo canais que pertencem exclusivamente a outra empresa.

**Validates: Requirements 2.3, 2.4**

Property 3: Preservation - Valores Brutos Inalterados

_For any_ consulta ao Dashboard, os valores `valor_total_venda` e `margem_venda_total` nos KPIs SHALL continuar sendo calculados como soma direta da tabela `vendas`, sem alteração na lógica existente, preservando compatibilidade com relatórios.

**Validates: Requirements 3.5**

Property 4: Preservation - ContasReceber Travadas

_For any_ ContaReceber com status "recebido" ou vinculada a `lote_recebimento_id`/`fatura_recebimento_id`, a função `regenerar()` SHALL NÃO alterar o `valor_parcela`, preservando o valor histórico registrado.

**Validates: Requirements 3.2, 3.3**

Property 5: Preservation - Dropdown Sem Filtro de Conta

_For any_ estado onde nenhuma conta está selecionada (filtro "Todas") ou onde nenhum CNPJ possui canais configurados, o dropdown SHALL exibir todos os canais ativos, mantendo o comportamento atual como fallback.

**Validates: Requirements 3.1, 3.4, 3.6**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `database/migrations/xxxx_create_canal_cnpj_table.php`

**Specific Changes**:
1. **Criar tabela pivot `canal_cnpj`**: Migration com colunas `id_canal` (FK → canais_venda), `cnpj_id` (FK → cnpjs), `ativo` (boolean, default true). Um canal pode pertencer a múltiplos CNPJs.

---

**File**: `app/Models/CanalVenda.php`

**Specific Changes**:
2. **Adicionar relação `cnpjs()`**: Relação `belongsToMany` com a tabela pivot `canal_cnpj` para permitir consulta de quais CNPJs um canal atende.

---

**File**: `app/Filament/Pages/DashboardVendas.php`

**Function**: `form()` (dropdown de canal)

**Specific Changes**:
3. **Filtrar dropdown por conta selecionada**: Alterar o `Select::make('canal')` para usar `->options(fn ($get) => ...)` que filtra canais com base no valor de `conta`. Quando `conta` não está selecionada, retorna todos os canais ativos. Quando está selecionada, busca o `cnpj_id` correspondente via config bling e filtra canais que possuem vínculo na tabela `canal_cnpj` com aquele CNPJ. Se nenhum canal está configurado para aquele CNPJ (fallback), retorna todos.

---

**File**: `app/Filament/Pages/DashboardVendas.php`

**Function**: `getTotaisProperty()`

**Specific Changes**:
4. **Adicionar totalizador de repasse**: Após calcular `$total` (bruto), adicionar query que soma `valor_parcela` das ContasReceber cujo `id_venda` está no conjunto de vendas filtrado. Retornar como `'repasse'` no array de totais. Isso permite que a view mostre ambos os valores.

---

**File**: `resources/views/filament/pages/dashboard-vendas.blade.php`

**Specific Changes**:
5. **Exibir KPI de repasse**: Adicionar card KPI com `$totais['repasse']` ao lado do card de "Total" existente, com label "Repasse Esperado" e ícone/cor diferenciado para distinguir do bruto.

---

**File**: `app/Filament/Pages/DashboardVendas.php`

**Function**: `updatedConta()`

**Specific Changes**:
6. **Resetar canal ao trocar conta**: Ao trocar a conta, resetar o filtro de canal (`$this->canal = null`) para evitar que um canal da conta anterior fique selecionado quando não pertence à nova conta.

## Testing Strategy

### Validation Approach

A estratégia segue duas fases: primeiro, demonstrar os bugs no código atual (counterexamples), depois verificar que o fix resolve os bugs e preserva o comportamento existente.

### Exploratory Bug Condition Checking

**Goal**: Demonstrar os bugs ANTES de implementar o fix. Confirmar ou refutar a análise de root cause.

**Test Plan**: Escrever feature tests que verificam o comportamento do Dashboard e do dropdown. Rodar no código NÃO corrigido para observar as falhas.

**Test Cases**:
1. **Dashboard Total vs Repasse**: Criar vendas com ContasReceber e verificar que `getTotaisProperty()` NÃO retorna chave 'repasse' (vai falhar no unfixed code — chave não existe)
2. **Dropdown Sem Filtro por Conta**: Com conta 'primary' selecionada, verificar que o dropdown retorna canais que deveriam ser exclusivos de 'secondary' (vai falhar no unfixed code — todos aparecem)
3. **ContaReceber Atualizada após Recalcular**: Alterar comissão de venda e verificar que `regenerar()` é chamado (isso já funciona no código atual — confirma que propagação existe)
4. **Dropdown Completo Sem Conta**: Sem filtro de conta, verificar que todos os canais ativos aparecem (deve passar no unfixed code — comportamento correto existente)

**Expected Counterexamples**:
- `getTotaisProperty()` retorna array sem chave 'repasse'
- Dropdown retorna todos os canais independente da conta selecionada
- Confirma que `regenerar()` já é chamado por `recalcularMargens()`

### Fix Checking

**Goal**: Verificar que para todos os inputs onde o bug condition é verdadeiro, a função corrigida produz o comportamento esperado.

**Pseudocode:**
```
FOR ALL request WHERE isBugCondition(request) DO
  IF request.type == 'dashboard_totals' THEN
    result := getTotaisProperty_fixed(request.filters)
    ASSERT 'repasse' IN result.keys
    ASSERT result['repasse'] == SUM(contas_receber.valor_parcela WHERE id_venda IN filtered_vendas)
  END IF
  
  IF request.type == 'canal_dropdown' AND request.conta IS NOT NULL THEN
    options := getCanaisOptions_fixed(request.conta)
    cnpj_id := config("bling.accounts.{request.conta}.cnpj_id")
    FOR ALL canal IN options DO
      ASSERT canal HAS relationship WITH cnpj_id IN canal_cnpj
    END FOR
  END IF
END FOR
```

### Preservation Checking

**Goal**: Verificar que para todos os inputs onde o bug condition NÃO é verdadeiro, o comportamento permanece inalterado.

**Pseudocode:**
```
FOR ALL request WHERE NOT isBugCondition(request) DO
  ASSERT getTotaisProperty_original(request)['total'] == getTotaisProperty_fixed(request)['total']
  ASSERT getTotaisProperty_original(request)['lucro'] == getTotaisProperty_fixed(request)['lucro']
  
  IF request.conta IS NULL THEN
    ASSERT getCanaisOptions_original() == getCanaisOptions_fixed(null)
  END IF
  
  FOR ALL contaReceber WHERE contaReceber.status == 'recebido' OR contaReceber.lote_recebimento_id IS NOT NULL DO
    ASSERT contaReceber.valor_parcela IS NOT MODIFIED by regenerar()
  END FOR
END FOR
```

**Testing Approach**: Property-based testing é recomendado para preservation checking porque:
- Gera automaticamente muitas combinações de filtros (período, status, conta)
- Detecta edge cases que testes manuais podem perder (ex: venda sem ContaReceber)
- Garante forte cobertura de que valores brutos não foram alterados

**Test Plan**: Observar comportamento no código NÃO corrigido para filtros, exportações e operações em lote. Depois escrever property-based tests capturando esse comportamento.

**Test Cases**:
1. **Valores Brutos Preservados**: Verificar que `total`, `lucro`, `margem` no array de totais mantêm os mesmos valores antes e depois do fix para qualquer combinação de filtros
2. **ContasReceber Travadas**: Gerar ContasReceber com status 'recebido' e `lote_recebimento_id` preenchido, chamar `regenerar()`, verificar que `valor_parcela` não muda
3. **Dropdown Sem Conta**: Sem conta selecionada, verificar que o dropdown retorna exatamente os mesmos canais que antes do fix
4. **Canais Compartilhados**: Canal vinculado a ambos CNPJs aparece independente da conta selecionada
5. **Fallback Sem Configuração**: Se `canal_cnpj` está vazio, dropdown retorna todos os canais ativos

### Unit Tests

- Testar `getTotaisProperty()` retorna chave 'repasse' com valor correto baseado em ContasReceber
- Testar filtragem de canais por CNPJ com vários cenários (canal exclusivo, compartilhado, sem vínculo)
- Testar que `regenerar()` não altera ContasReceber travadas (status recebido, com lote)
- Testar fallback quando `canal_cnpj` está vazio
- Testar reset de canal ao trocar conta

### Property-Based Tests

- Gerar vendas aleatórias com diferentes combinações de canal, conta, e componentes de repasse — verificar que `totais['total']` permanece como soma de `valor_total_venda`
- Gerar ContasReceber com status aleatório (pendente/recebido) e lote_id aleatório — verificar que `regenerar()` só altera pendentes sem lote
- Gerar combinações de conta selecionada e canais com vínculos variados — verificar que apenas canais do CNPJ correspondente aparecem
- Gerar cenários sem configuração na `canal_cnpj` — verificar fallback para todos os canais

### Integration Tests

- Fluxo completo: criar venda → gerar ContaReceber → alterar comissão → verificar que Dashboard mostra repasse atualizado
- Fluxo de filtro: selecionar conta → verificar dropdown → selecionar canal → verificar vendas filtradas corretamente
- Fluxo de Fatura: confirmar fatura → verificar que lote usa valor da ContaReceber no momento → verificar que KPI reflete corretamente
- Fluxo de troca de conta: selecionar "HES Móveis" → verificar canal resetado → ver apenas canais HES → trocar para "Todas" → ver todos canais
