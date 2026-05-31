# Implementation Plan: FAQ System

## Overview

Implementação de uma página FAQ estática no painel Filament do sistema Mobilia Decor. A página utiliza Alpine.js para interatividade client-side (acordeão, busca com debounce, scroll-spy, deep linking) e define o conteúdo como array PHP estruturado diretamente na classe da página, sem banco de dados.

## Tasks

- [x] 1. Criar a classe Filament Page e a view Blade base
  - [x] 1.1 Criar a classe `App\Filament\Pages\Faq` com configuração de navegação
    - Criar arquivo `app/Filament/Pages/Faq.php`
    - Configurar `$navigationIcon` como `heroicon-o-question-mark-circle`
    - Configurar `$navigationGroup` como `Ajuda`
    - Configurar `$navigationLabel` como `FAQ`
    - Configurar `$title` como `FAQ - Perguntas Frequentes`
    - Configurar `$view` como `filament.pages.faq`
    - Configurar `$navigationSort` como `99`
    - Implementar `canAccess()` retornando `true` para todos os usuários autenticados
    - Implementar método `getSections()` que retorna o array de dados FAQ (inicialmente com 2-3 seções de exemplo)
    - _Requirements: 1.1, 1.5, 5.1, 5.2, 5.3_

  - [x] 1.2 Criar a view Blade `resources/views/filament/pages/faq.blade.php` com estrutura Alpine.js
    - Criar arquivo `resources/views/filament/pages/faq.blade.php`
    - Implementar layout com sidebar lateral (sticky, hidden em telas < lg) e área de conteúdo principal
    - Implementar componente Alpine.js `faqPage` com estado inicial (sections, filteredSections, searchQuery, expandedSections, expandedQuestions, activeSection)
    - Implementar campo de busca com `x-model` e `@input.debounce.300ms`
    - Implementar renderização das seções em formato acordeão com `x-for` e `x-show`
    - Implementar estado vazio com mensagem "Nenhuma pergunta encontrada para o termo pesquisado"
    - _Requirements: 1.2, 1.3, 3.1, 3.4, 4.1_

- [x] 2. Implementar lógica Alpine.js de busca e navegação
  - [x] 2.1 Implementar função `normalizeText()` e `filterSections()` no componente Alpine.js
    - Implementar `normalizeText(text)` usando `normalize('NFD')` + regex para remover diacríticos + `toLowerCase()`
    - Implementar `filterSections()` que filtra seções e perguntas baseado em `searchQuery` (mínimo 2 caracteres)
    - Filtrar por match em `question` ou `answer` após normalização
    - Ocultar seções sem perguntas correspondentes
    - Retornar todas as seções quando query < 2 caracteres
    - _Requirements: 3.2, 3.3, 3.5, 3.6_

  - [x] 2.2 Implementar `handleDeepLink()` e `initScrollSpy()` no componente Alpine.js
    - Implementar `handleDeepLink()` que lê parâmetro `?secao=` da URL
    - Se slug válido: expandir seção correspondente e fazer scroll suave até ela
    - Se slug inválido: manter estado padrão (todas colapsadas, topo)
    - Implementar `initScrollSpy()` com IntersectionObserver para destacar seção ativa no sidebar
    - Implementar navegação por clique no sidebar com scroll suave e expansão da seção
    - _Requirements: 4.2, 4.3, 4.4, 4.5, 4.6_

  - [x] 2.3 Implementar estilização visual e indicadores de ações destrutivas
    - Estilizar perguntas com `destructive: true` com indicador visual (ícone ⚠️ ou cor de aviso)
    - Garantir que respostas suportam HTML seguro (strong, ul, ol, li, br, code, p)
    - Estilizar sidebar com highlight da seção ativa (scroll-spy)
    - Garantir responsividade (sidebar oculto em mobile, conteúdo full-width)
    - _Requirements: 2.3, 2.5, 4.6_

- [x] 3. Checkpoint - Verificar estrutura base
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Implementar conteúdo FAQ completo
  - [x] 4.1 Implementar conteúdo FAQ para Dashboard Vendas e Caixa
    - Adicionar seção `dashboard-vendas` com documentação completa: filtros (período, canal, conta Bling, status, busca), ações em lote (buscar NF-e, CT-e, custos, aplicar planilha Shopee), ações individuais por pedido (recalcular margens, cancelar com estorno, registrar reembolso, lançar frete manual, buscar NF-e por número, marcar frete Envias, marcar aguardando envio), significado de cada status, exportação CSV
    - Adicionar seção `caixa` com documentação completa: filtros (período, conta bancária, categoria financeira), visões diária e por categoria, saldo anterior, transferência entre contas, significado de entradas e saídas
    - Mínimo 5 perguntas por seção com respostas detalhadas
    - _Requirements: 6.1, 6.2, 2.1, 2.2, 2.4_

  - [x] 4.2 Implementar conteúdo FAQ para Importar Planilhas (5 marketplaces)
    - Adicionar seções para MadeiraMadeira, Magalu, Mercado Livre, Shopee e Webcontinental
    - Documentar propósito da importação, formato esperado do arquivo, resultados possíveis
    - Incluir perguntas sobre erros comuns e resolução de problemas
    - Mínimo 3 perguntas por marketplace
    - _Requirements: 6.3, 2.1, 2.4_

  - [x] 4.3 Implementar conteúdo FAQ para Integrações (Bling, Mercado Livre, Shopee)
    - Adicionar seção `bling-integration` com autenticação OAuth, múltiplas contas, status de autorização
    - Adicionar seção `mercado-livre-integration` com OAuth, múltiplas contas, expiração de token, user_id
    - Adicionar seção `shopee-integration` com OAuth, teste de conexão, nome da loja, modo sandbox/produção
    - Incluir pelo menos 3 perguntas de resolução de problemas por integração (token expirado, falha de sincronização, dados não encontrados)
    - _Requirements: 6.4, 2.2, 2.4, 2.5_

  - [x] 4.4 Implementar conteúdo FAQ para demais páginas do sistema
    - Adicionar seções para: Calculadora de Compras, Calculadora ML, Comparar Estoque Bling, Consulta CTes, Contagem Estoque, Importar Frenet, Importar Pedidos, Importar Shopee Afiliados, Importar Tabela Transportadora, Lote Recebimentos, Mercado Livre Promoções, Recebimentos, Relatório Fretes, Simulador Frete, Troca Tampos, Tutorial Conciliação, Upload CTE
    - Cada seção deve conter: descrição do propósito (até 2 frases), lista de funcionalidades/ações, mínimo 2 perguntas frequentes com respostas
    - Explicar siglas e termos específicos na primeira ocorrência de cada seção
    - _Requirements: 6.5, 6.6, 1.4, 2.1, 2.2_

- [x] 5. Checkpoint - Verificar conteúdo completo
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implementar testes
  - [x] 6.1 Criar testes unitários e de feature para a página FAQ
    - Criar `tests/Feature/FaqPageAccessTest.php`
    - Testar que usuário autenticado pode acessar a página (status 200)
    - Testar que usuário não autenticado é redirecionado para login
    - Testar que a view renderiza sem erros
    - Testar que o menu exibe label "FAQ" no grupo "Ajuda" com ícone correto
    - Testar que `getSections()` retorna array não vazio
    - Testar que todas as seções obrigatórias existem (verificar slugs)
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 1.1, 1.4_

  - [x] 6.2 Write property test: Section content completeness
    - **Property 1: Section content completeness**
    - Criar `tests/Unit/FaqPageTest.php`
    - Para cada seção no array de dados, verificar: slug não vazio, title não vazio, mínimo 2 question-answer pairs, cada question e answer são strings não vazias
    - **Validates: Requirements 1.4, 6.5, 6.6**

  - [x] 6.3 Write property test: Destructive action warnings
    - **Property 2: Destructive action warnings**
    - Para cada pergunta com `destructive: true`, verificar que a resposta contém "⚠️" ou "aviso" ou "irreversível" ou "não pode ser desfeita"
    - **Validates: Requirements 2.5**

  - [x] 6.4 Write property test: Search filter case and accent insensitivity
    - **Property 3: Search filter case and accent insensitivity**
    - Gerar variações de case e acento de substrings existentes nas perguntas/respostas
    - Verificar que a função de filtro retorna o item correspondente para cada variação
    - Usar Faker para gerar 100+ variações
    - **Validates: Requirements 3.2**

  - [x] 6.5 Write property test: Short query returns all items
    - **Property 4: Short query returns all items**
    - Gerar strings de 0-1 caracteres aleatórios
    - Verificar que a função de filtro retorna todas as seções com todas as perguntas inalteradas
    - **Validates: Requirements 3.3**

  - [x] 6.6 Write property test: Sections without matches are hidden
    - **Property 5: Sections without matches are hidden**
    - Gerar queries de 2+ caracteres que matcham parcialmente
    - Verificar que seções sem match são excluídas do resultado filtrado
    - **Validates: Requirements 3.6**

  - [x] 6.7 Write property test: Valid URL slug deep linking
    - **Property 6: Valid URL slug deep linking**
    - Iterar sobre todos os slugs válidos do array de dados
    - Verificar que ao acessar com `?secao={slug}`, a seção correspondente está expandida
    - **Validates: Requirements 4.3, 4.4**

  - [x] 6.8 Write property test: Invalid URL slug fallback
    - **Property 7: Invalid URL slug fallback**
    - Gerar slugs aleatórios que não existem no array de dados
    - Verificar que todas as seções permanecem no estado colapsado padrão
    - **Validates: Requirements 4.5**

  - [x] 6.9 Write property test: Universal access for authenticated users
    - **Property 8: Universal access for authenticated users**
    - Criar usuários com diferentes roles (admin, financeiro, operacional, visualizador, marketing)
    - Verificar que `canAccess()` retorna `true` para todos
    - **Validates: Requirements 5.1**

- [x] 7. Final checkpoint - Garantir que todos os testes passam
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- O conteúdo FAQ é extenso (24+ seções) e deve ser implementado incrementalmente nas tasks 4.1-4.4
- A lógica de busca/filtro é implementada em Alpine.js (client-side) — os property tests para filtro (3-5) devem testar uma implementação PHP equivalente ou usar testes de browser (Dusk)
- O projeto usa Laravel + Filament 3.x + Alpine.js (já incluído no Filament)

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3"] },
    { "id": 3, "tasks": ["4.1", "4.2", "4.3", "4.4"] },
    { "id": 4, "tasks": ["6.1", "6.2", "6.3", "6.4", "6.5", "6.6", "6.7", "6.8", "6.9"] }
  ]
}
```
