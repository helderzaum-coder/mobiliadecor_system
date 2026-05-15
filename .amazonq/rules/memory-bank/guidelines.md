# Development Guidelines — Mobilia Decor System

## Code Quality Standards

### PHP / Laravel Conventions
- **PSR-4 autoloading** com namespaces seguindo a estrutura de diretórios
- **Strict typing** não é enforçado, mas type hints são usados em parâmetros e retornos
- **Idioma**: código em inglês (nomes de classes, métodos), comentários e strings de negócio em português
- **Naming**: camelCase para métodos/variáveis, PascalCase para classes, snake_case para colunas do banco
- **Tabelas**: nomes em português no plural (vendas, contas_receber, pedidos_bling_staging)
- **Models**: nomes em português no singular (Venda, ContaReceber, PedidoBlingStaging)

### Filament Patterns
- **Resources** para CRUD padrão (tabela + formulário + listagem)
- **Pages** standalone para funcionalidades complexas (dashboards, importações, simuladores)
- **Livewire properties** públicas para estado reativo da página
- **Notifications** via `Filament\Notifications\Notification::make()` para feedback ao usuário
- **Forms** com `InteractsWithForms` trait para filtros e inputs reativos
- **Access control** via `canAccess()` com `hasRole('admin')`

### Service Layer Pattern
```php
// Services recebem accountKey no construtor para multi-tenant
class BlingImportService
{
    private BlingClient $client;
    private string $accountKey;

    public function __construct(string $accountKey)
    {
        $this->accountKey = $accountKey;
        $this->client = new BlingClient($accountKey);
    }
}

// Métodos estáticos para operações que não dependem de estado
public static function buscarNfePorPedido(PedidoBlingStaging $staging): bool
```

### Return Patterns
```php
// Services retornam arrays associativos com status/resultado
return ['status' => 'importado', 'numero' => $pedido['numero']];
return ['importados' => 0, 'ignorados' => 0, 'erros' => 0, 'mensagens' => []];

// Métodos de ação em Pages retornam void e usam Notifications
public function buscarNfe(int $vendaId): void
{
    $result = SomeService::doSomething($model);
    Notification::make()->title($result['msg'])
        ->{$result['success'] ? 'success' : 'warning'}()->send();
}
```

## Architectural Patterns

### Multi-Account (Multi-Tenant)
- Sistema suporta múltiplas contas Bling/ML (primary, secondary)
- `bling_account` field em models para identificar a conta
- Config via `config("bling.accounts.{$accountKey}.cnpj_id")`

### Import Pipeline
1. API externa → `*ImportService` → `PedidoBlingStaging` (status: pendente)
2. Pré-cálculos automáticos (comissão, imposto) durante importação
3. Reprocessamento via planilhas (ML, Shopee, MM)
4. Aprovação → `Venda` (registro final)
5. Jobs em background para operações pesadas

### Queue Jobs Pattern
```php
// Jobs são dispatched após operações que precisam de processamento assíncrono
SyncEstoquePedidoJob::dispatch($staging->id);
BuscarDadosVendaLoteJob::dispatch('nfe', $ids, auth()->id());
```

### Error Handling
- `try/catch` em operações de API com logging via `Log::error()` / `Log::warning()`
- Retorno de arrays com status de erro em vez de exceptions para o caller
- Progresso logado a cada N registros (`if ($resultado['importados'] % 10 === 0)`)

## Database Conventions

### Migration Naming
- Formato: `YYYY_MM_DD_NNNNNN_descricao_em_snake_case.php`
- Prefixo sequencial para ordenação (000001, 000002...)
- Tabelas com prefixo de domínio quando relacionadas

### Column Patterns
- IDs: `id_venda`, `id_canal`, `bling_id` (prefixo semântico)
- Flags: `frete_pago`, `planilha_processada`, `estorno_pendente` (boolean)
- Valores: `valor_total_venda`, `comissao_calculada`, `margem_venda_total`
- Datas: `data_pedido`, `data_vencimento`, `data_prevista_envio`
- JSON columns: `itens`, `parcelas`, `dados_originais` (para dados estruturados)

## Java (ML Dashboard) Conventions
- Package: `com.mobiliadecor.ml_dashboard_2025`
- Swing GUI com GridBagLayout para formulários complexos
- SwingWorker para operações assíncronas
- Inner classes para DTOs (ProdutoInfo, VariacaoInfo, CampanhaInfo)
- Emojis em logs e UI para feedback visual
- TokenManager estático para gerenciamento de autenticação
- HttpURLConnection para chamadas REST (sem framework HTTP)

## Common Idioms

### Canal Identification
```php
// Identificação de canal por CNPJ do intermediador, observações ou nome
$isMl = str_contains(strtolower($canal), 'mercado')
    || str_starts_with((string) $numeroLoja, '2000');
```

### Flexible Matching
```php
// Busca flexível removendo espaços para matching de nomes
$canal = CanalVenda::get()->first(
    fn ($c) => str_replace(' ', '', strtolower($c->nome_canal)) === str_replace(' ', '', strtolower($canalNome))
);
```

### Batch Operations in Pages
```php
// Padrão para operações em lote: buildQuery → filtrar → dispatch job
public function buscarNfeLote(): void
{
    $ids = $this->buildQuery()
        ->where(fn ($q) => $q->whereNull('nfe_chave_acesso'))
        ->pluck('id_venda')->toArray();
    BuscarDadosVendaLoteJob::dispatch('nfe', $ids, auth()->id());
}
```

### Reactive Filters (Filament Pages)
```php
// Reset paginação quando filtros mudam
public function updatedPeriodo(): void { $this->pagina = 1; }
public function updatedCanal(): void { $this->pagina = 1; }
```

## Commit Convention
- Criar nome do commit descritivo ao final de cada atualização
- Commits em português, descrevendo a mudança realizada

## Deploy
- Push para `main` dispara deploy automático via GitHub Actions
- Workflow: git pull → composer install --no-dev → npm build → migrate → optimize → queue:restart
