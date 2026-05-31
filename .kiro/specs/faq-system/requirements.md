# Requirements Document

## Introduction

A Página FAQ é uma página estática integrada ao painel Filament que documenta como cada botão, página e funcionalidade do sistema Mobilia Decor funciona. O conteúdo é definido diretamente no código, sem necessidade de administração via banco de dados. O objetivo é fornecer aos usuários uma referência rápida e pesquisável para entender o funcionamento de todas as funcionalidades disponíveis no sistema.

## Glossary

- **Página_FAQ**: Página Filament que exibe as perguntas e respostas organizadas por seção do sistema
- **Seção_FAQ**: Agrupamento de perguntas correspondente a uma página ou funcionalidade do sistema
- **Pergunta_FAQ**: Par de pergunta e resposta sobre uma funcionalidade específica
- **Usuário**: Qualquer usuário autenticado que pode visualizar o FAQ
- **Campo_Busca**: Campo de texto para pesquisa de perguntas e respostas

## Requirements

### Requisito 1: Página FAQ com Conteúdo Estático

**User Story:** Como um usuário, eu quero acessar uma página de FAQ no sistema, para que eu possa consultar rapidamente como cada funcionalidade funciona.

#### Acceptance Criteria

1. THE Página_FAQ SHALL estar acessível no menu de navegação do Filament com o rótulo "FAQ", exibida dentro de um grupo de navegação e visível para todos os usuários autenticados independentemente de role
2. THE Página_FAQ SHALL exibir as Seções_FAQ com suas respectivas Perguntas_FAQ organizadas em formato acordeão (expansível/colapsável), com todas as seções no estado colapsado por padrão ao carregar a página
3. WHEN o usuário clicar no título de uma Pergunta_FAQ, THE Página_FAQ SHALL alternar a visibilidade da resposta correspondente entre expandido e colapsado, sem recarregar a página
4. THE Página_FAQ SHALL conter seções correspondentes a todas as páginas do sistema: Bling Integration, Caixa, Calculadora de Compras, Calculadora ML, Comparar Estoque Bling, Consulta CTes, Contagem Estoque, Dashboard Vendas, Importar Frenet, Importar Pedidos, Importar Planilhas (MadeiraMadeira, Magalu, ML, Shopee, Webcontinental), Importar Shopee Afiliados, Importar Tabela Transportadora, Lote Recebimentos, Mercado Livre Integration, Mercado Livre Promoções, Recebimentos, Relatório Fretes, Shopee Integration, Simulador Frete, Troca Tampos, Tutorial Conciliação e Upload CTE, cada seção contendo no mínimo 1 par de pergunta e resposta
5. THE Página_FAQ SHALL definir o conteúdo das perguntas e respostas diretamente no código-fonte da página, sem utilizar banco de dados

### Requisito 2: Conteúdo das Perguntas e Respostas

**User Story:** Como um usuário, eu quero que o FAQ explique cada botão e funcionalidade de cada página, para que eu saiba exatamente o que cada ação faz no sistema.

#### Acceptance Criteria

1. THE Página_FAQ SHALL documentar para cada Seção_FAQ: o propósito da página, os filtros disponíveis, os botões e ações disponíveis com a descrição do resultado observável de cada ação, e o significado dos dados exibidos
2. THE Página_FAQ SHALL explicar siglas e termos específicos do domínio (como NF-e, CT-e, ERP, SKU, Bling) na primeira ocorrência dentro de cada Seção_FAQ
3. THE Página_FAQ SHALL formatar as respostas com suporte a texto em negrito, listas e quebras de linha para facilitar a leitura
4. WHEN uma funcionalidade possuir pré-requisitos ou dependências de outras páginas, THE Página_FAQ SHALL mencionar essas dependências na resposta
5. WHEN uma ação for destrutiva ou irreversível (como cancelar pedido, estornar ou excluir registros), THE Página_FAQ SHALL incluir um aviso indicando que a ação não pode ser desfeita e suas consequências

### Requisito 3: Busca de Perguntas

**User Story:** Como um usuário, eu quero pesquisar perguntas no FAQ, para que eu possa encontrar rapidamente a informação que preciso sem navegar por todas as seções.

#### Acceptance Criteria

1. THE Página_FAQ SHALL exibir um Campo_Busca no topo da página
2. WHEN o Usuário digitar 2 ou mais caracteres no Campo_Busca, THE Página_FAQ SHALL filtrar as Perguntas_FAQ exibindo apenas aquelas cujo título ou conteúdo da resposta contenha o texto digitado, ignorando diferenças de maiúsculas/minúsculas e acentuação (ex: "entrega" deve corresponder a "Entrega" e "próximo" deve corresponder a "proximo")
3. WHEN o Campo_Busca estiver vazio ou contiver menos de 2 caracteres, THE Página_FAQ SHALL exibir todas as Perguntas_FAQ organizadas por Seção_FAQ
4. WHEN a busca não retornar resultados, THE Página_FAQ SHALL exibir a mensagem "Nenhuma pergunta encontrada para o termo pesquisado" e ocultar todas as Seção_FAQ
5. WHILE o Usuário estiver digitando no Campo_Busca, THE Página_FAQ SHALL aplicar debounce de 300ms antes de executar a filtragem
6. WHEN a busca retornar resultados parciais, THE Página_FAQ SHALL ocultar as Seção_FAQ que não possuem nenhuma Pergunta_FAQ correspondente, exibindo apenas as seções que contêm pelo menos uma pergunta filtrada

### Requisito 4: Navegação por Seções

**User Story:** Como um usuário, eu quero navegar facilmente entre as seções do FAQ, para que eu possa encontrar a ajuda da página específica que estou usando.

#### Acceptance Criteria

1. THE Página_FAQ SHALL exibir um índice lateral fixo (sticky) com links para cada Seção_FAQ, permanecendo visível durante a rolagem da página
2. WHEN o Usuário clicar em uma Seção_FAQ no índice, THE Página_FAQ SHALL rolar a página suavemente (smooth scroll) até a seção correspondente e expandir o acordeão da Seção_FAQ
3. THE Página_FAQ SHALL suportar links diretos para seções específicas via parâmetro na URL utilizando o slug da seção (ex: /faq?secao=dashboard-vendas)
4. WHEN a Página_FAQ for acessada com um parâmetro de seção válido, THE Página_FAQ SHALL expandir automaticamente a Seção_FAQ correspondente e rolar até ela
5. IF a Página_FAQ for acessada com um parâmetro de seção que não corresponde a nenhuma Seção_FAQ existente, THEN THE Página_FAQ SHALL exibir todas as seções no estado padrão (colapsadas) e posicionar a visualização no topo da página
6. WHILE o Usuário rolar a página, THE Página_FAQ SHALL destacar visualmente no índice lateral o link da Seção_FAQ atualmente visível na viewport

### Requisito 5: Acesso à Página

**User Story:** Como um usuário, eu quero que todos os usuários do sistema possam acessar o FAQ, para que qualquer pessoa possa consultar a documentação.

#### Acceptance Criteria

1. THE Página_FAQ SHALL ser acessível a qualquer Usuário autenticado no sistema, retornando true no método canAccess() independente da role atribuída (admin, financeiro, operacional, visualizador, marketing)
2. THE Página_FAQ SHALL estar visível e posicionada no grupo de navegação "Ajuda" do menu lateral do Filament para todos os usuários autenticados
3. THE Página_FAQ SHALL utilizar o ícone heroicon-o-question-mark-circle no menu de navegação
4. IF um usuário não autenticado tentar acessar a Página_FAQ via URL direta, THEN THE Sistema SHALL redirecionar o usuário para a página de login

### Requisito 6: Conteúdo Específico por Página do Sistema

**User Story:** Como um usuário, eu quero que o FAQ cubra detalhadamente cada página do sistema, para que eu entenda todas as funcionalidades disponíveis.

#### Acceptance Criteria

1. THE Página_FAQ SHALL documentar na seção "Dashboard Vendas": filtros disponíveis (período com opções hoje/esta semana/este mês/mês passado/selecionar mês/customizado, canal de venda, conta Bling, status, e busca por número de pedido); ações em lote (buscar NF-e, buscar CT-e, buscar custos, aplicar planilha Shopee); ações individuais por pedido (recalcular margens, cancelar com estorno, registrar reembolso, lançar frete manual com valor e transportadora, buscar NF-e por número, marcar frete Envias, marcar aguardando envio); significado de cada status de filtro (falta NF-e, falta frete, falta planilha, falta afiliado Shopee, sem custo produto, aguardando envio, ME2/FULL, Shopee Xpress, Envias, incompleto, completo, cancelados/estornos); e exportação de planilha CSV com descrição das colunas exportadas
2. THE Página_FAQ SHALL documentar na seção "Caixa": filtros disponíveis (período, conta bancária, categoria financeira); visões diária (agrupada por dia com saldo acumulado) e por categoria (agrupada por categoria com totais de entradas e saídas); funcionalidade de saldo anterior (soma de todas as movimentações anteriores ao período mais saldo inicial das contas); funcionalidade de transferência entre contas (seleção de conta origem, conta destino, valor mínimo de R$ 0,01, data e descrição opcional); e significado das entradas (recebimentos com status "recebido") e saídas (pagamentos com status "pago")
3. THE Página_FAQ SHALL documentar na seção "Importar Planilhas" para cada marketplace: o propósito da importação (vincular dados financeiros do marketplace aos pedidos do sistema para cálculo de margens); o formato esperado do arquivo (MadeiraMadeira: CSV; Magalu: XLSX; Mercado Livre: XLSX, XLS ou CSV com seleção de conta; Shopee: XLSX, XLS ou CSV; Webcontinental: XLSX, XLS ou CSV); e os resultados possíveis após a importação (quantidade de processados, não encontrados, já processados, com divergência, e erros)
4. THE Página_FAQ SHALL documentar na seção "Integrações" para cada serviço: Bling (autenticação OAuth com suporte a múltiplas contas, verificação de status de autorização); Mercado Livre (autenticação OAuth com múltiplas contas, exibição de data de expiração do token e user_id); e Shopee (autenticação OAuth via redirecionamento, teste de conexão com exibição do nome da loja, indicação de modo sandbox/produção); e para cada integração, pelo menos 3 perguntas sobre resolução de problemas incluindo token expirado, falha de sincronização e dados não encontrados
5. THE Página_FAQ SHALL documentar cada uma das demais páginas do sistema (Calculadora de Compras, Calculadora ML, Comparar Estoque Bling, Consulta CTes, Contagem Estoque, Importar Frenet, Importar Pedidos, Importar Shopee Afiliados, Importar Tabela Transportadora, Lote Recebimentos, Mercado Livre Promoções, Recebimentos, Relatório Fretes, Simulador Frete, Troca Tampos, Tutorial Conciliação e Upload CTE) contendo no mínimo: uma descrição do propósito da página em até 2 frases, a lista de funcionalidades ou ações disponíveis, e pelo menos 2 perguntas frequentes com respostas sobre uso ou problemas comuns da página
6. WHEN o usuário acessar qualquer seção de página documentada no FAQ, THE Página_FAQ SHALL apresentar o conteúdo organizado em formato de perguntas e respostas, com cada pergunta descrevendo uma funcionalidade ou cenário de uso específico e cada resposta fornecendo o passo a passo ou explicação correspondente
