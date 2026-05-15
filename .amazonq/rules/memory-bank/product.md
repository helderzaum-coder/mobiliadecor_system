# Product Overview — Mobilia Decor System

## Purpose & Value Proposition
Sistema ERP/backoffice para a empresa **Mobilia Decor** (e-commerce de móveis). Centraliza a gestão de vendas multicanal, conciliação financeira, estoque, logística e integrações com marketplaces e ERP externo (Bling).

## Key Features
- **Integração Bling (ERP)**: OAuth, importação de pedidos/produtos, sincronização de estoque, webhooks
- **Integração Mercado Livre**: OAuth, importação de pedidos, planilha de conciliação
- **Integração Shopee**: OAuth, importação de planilhas, afiliados, correção de dados
- **Integração MadeiraMadeira / Magalu / Webcontinental**: importação de planilhas de vendas
- **Gestão de Vendas**: CRUD completo, aprovação, recálculo, comissões, impostos
- **Conciliação Financeira**: contas a receber/pagar, extratos bancários, faturas de transportadoras, impostos mensais
- **Gestão de Estoque**: produtos, movimentações, espelhamento entre contas Bling, saldo secundário
- **Logística / Frete**: cadastro de transportadoras, tabelas de frete, cotação, simulador, upload de CT-e
- **Troca de Tampos**: configuração de variações e equalização de estoque
- **Dashboard de Vendas**: relatórios e métricas consolidadas
- **Controle de Acesso**: roles/permissions via spatie/laravel-permission
- **Deploy Automatizado**: GitHub Actions via SSH

## Target Users
- Equipe administrativa e financeira da Mobilia Decor
- Gestores de e-commerce e logística
- Operadores de marketplace

## Tech Stack Summary
- Laravel 12 + Filament 3.3 (admin panel)
- PHP 8.2+, Tailwind CSS 4, Vite 7
- SQLite (dev) / MySQL (prod)
- Queue-based background jobs para importações pesadas
