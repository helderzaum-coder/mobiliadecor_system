# Guia de Configuração: Importação de Pedidos com Queue

## ✅ O que foi corrigido

1. **Importação agora é assíncrona** - Você não vai mais sofrer timeout 504
2. **Job com timeout de 10 minutos** - Importações longas podem terminar sem problema
3. **BlingClient com timeout aumentado** - De 30s para 60s

---

## 🔧 Como fazer funcionar

### 1. Rodar o Queue Worker

Para que os jobs sejam processados, você precisa manter um worker rodando:

```bash
php artisan queue:work --tries=1 --timeout=0
```

**Em produção (cPanel/Hostinger)**, adicione no cron:
```
* * * * * cd /caminho/do/projeto && php artisan queue:work --timeout=360
```

### 2. Verificar filas antigas

Se houver jobs antigos no banco, limpe:
```bash
php artisan queue:flush
```

### 3. Testar localmente

**Opção 1: Modo síncrono (testes)**
No seu `.env`, mude:
```env
QUEUE_CONNECTION=sync
```

**Opção 2: Modo banco de dados (produção)**
```env
QUEUE_CONNECTION=database
# Certifique-se que a tabela jobs existe
php artisan migrate
```

---

## 📋 Como usar

1. Vá em **Integrações > Importar Pedidos**
2. Selecione a conta (Mobilia Decor ou HES Móveis)
3. Escolha a data início e fim
4. Clique em **Importar**
5. ✅ A importação é enfileirada e processa em background

O usuário vê uma mensagem de sucesso imediatamente, e a importação continua rodando.

---

## 🔍 Monitorar Jobs

### Ver jobs enfileirados:
```bash
SELECT * FROM jobs WHERE queue = 'default' ORDER BY created_at DESC;
```

### Ver jobs com falha:
```bash
SELECT * FROM failed_jobs ORDER BY failed_at DESC;
```

### Limpar um job incorreto:
```bash
DELETE FROM jobs WHERE id = **ID**;
```

---

## ⚠️ Troubleshooting

| Problema | Solução |
|----------|---------|
| Jobs não processam | Verificar se `queue:work` está rodando |
| Erro "Table jobs doesn't exist" | Executar `php artisan migrate` |
| Job falha silenciosamente | Verificar logs em `storage/logs/laravel.log` |
| Muitos jobs acumulados | Executar `php artisan queue:flush` |

---

## 📊 Melhorias implementadas

- ✅ **Timeout do BlingClient**: 30s → 60s
- ✅ **Timeout do Job**: 10 minutos
- ✅ **Processamento assíncrono**: Evita 504 errors
- ✅ **Logging**: Todos os erros são registrados em `laravel.log`
