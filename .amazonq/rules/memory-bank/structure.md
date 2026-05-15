# Project Structure — Mobilia Decor System

## Directory Layout

```
mobiliadecor_system/
├── app/
│   ├── Console/Commands/       # Artisan commands customizados
│   ├── Filament/
│   │   ├── Pages/              # Páginas standalone (dashboards, importações, simuladores)
│   │   └── Resources/          # CRUD Resources do Filament (15 resources)
│   ├── Helpers/                # Helpers utilitários (TransportadoraHelper)
│   ├── Http/Controllers/       # Controllers HTTP (OAuth callbacks Bling/ML)
│   ├── Jobs/                   # Queue jobs (importação, sync estoque, webhooks)
│   ├── Models/                 # Eloquent models (23 models)
│   ├── Policies/               # Authorization policies (9 policies)
│   ├── Providers/              # Service providers
│   └── Services/               # Business logic services
│       ├── Bling/              # BlingClient, OAuth, Import, Estoque, Sync
│       ├── MercadoLivre/       # ML Client, OAuth, Orders
│       ├── Shopee/             # Shopee Client, Service
│       └── *.php               # Domain services (comissão, frete, planilhas, etc.)
├── bootstrap/                  # App bootstrap, cached services
├── config/                     # Configs (bling, mercadolivre, shopee, permission, etc.)
├── database/
│   ├── migrations/             # 60+ migrations
│   ├── seeders/                # Database seeders
│   └── database.sqlite         # Dev database
├── public/                     # Web root (index.php, assets)
├── resources/
│   ├── css/                    # Stylesheets
│   ├── js/                     # JavaScript
│   └── views/                  # Blade templates
├── routes/
│   └── web.php                 # OAuth routes (Bling, ML); Filament handles admin routes
├── storage/
│   └── app/ml_dashboard_2025/  # Java files para dashboard ML (projeto auxiliar)
├── tests/                      # PHPUnit tests
├── .github/workflows/          # CI/CD deploy via SSH
├── composer.json               # PHP dependencies
├── package.json                # Node dependencies (Vite, Tailwind)
└── vite.config.js              # Vite build config
```

## Core Components & Relationships

### Filament Admin Panel (UI Layer)
- **Resources** (CRUD): Venda, ContaReceber, ContaPagar, ExtratoBancario, FaturaTransportadora, ImpostoMensal, CanalVenda, Cnpj, Transportadora, PedidoBlingStaging, ProdutoEstoque, MovimentacaoEstoque, TrocaTampoConfig, User
- **Pages** (standalone): DashboardVendas, ImportarPedidos, ImportarPlanilha*, SimuladorFrete, CalculadoraML, CalculadoraCompras, BlingIntegration, MercadoLivreIntegration, ShopeeIntegration, TrocaTampos, Recebimentos, LoteRecebimentos, UploadCte, ConsultaCtes, RelatorioFretes, TutorialConciliacao

### Services Layer (Business Logic)
- Integrations: BlingClient, MercadoLivreClient, ShopeeClient (API wrappers)
- OAuth: BlingOAuthService, MercadoLivreOAuthService
- Import: BlingImportService, *PlanilhaService (Shopee, ML, MM, Magalu, Webcontinental)
- Domain: EstoqueService, CotacaoFreteService, CalculoComissaoService, AprovacaoVendaService, TrocaTampoService, VendaRecalculoService, ContaReceberService, CteService

### Jobs (Background Processing)
- ImportarPedidosBlingJob, ImportarProdutosBlingJob
- SyncEstoqueBlingJob, SyncEstoquePedidoJob, SyncEstoqueTampoJob, EspelharEstoqueJob, SyncSaldoSecondaryJob
- BlingWebhookSyncJob, BuscarDadosVendaLoteJob, VariacaoTamposJob

### Models (Data Layer)
- Core: Venda, PedidoBlingStaging, ProdutoEstoque, MovimentacaoEstoque
- Financial: ContaReceber, ContaPagar, ExtratoBancario, FaturaTransportadora, ImpostoMensal
- Config: CanalVenda, Cnpj, RegraComissao, TrocaTampoConfig
- Logistics: Transportadora, TransportadoraTabelaFrete, TransportadoraTaxa, TransportadoraUf, Cte
- Auth: User, BlingToken, MercadoLivreToken
- Planilhas: PlanilhaMlDado, PlanilhaMmDado, PlanilhaShopeeDado

## Architectural Patterns
- **Filament 3 Admin Panel** como UI principal (sem controllers tradicionais para CRUD)
- **Service Layer** para lógica de negócio complexa
- **Queue Jobs** para operações pesadas (importações, sync)
- **OAuth integrations** com token refresh automático
- **Policies** para autorização baseada em roles (spatie/permission)
- **Webhook endpoints** registrados fora do middleware web
