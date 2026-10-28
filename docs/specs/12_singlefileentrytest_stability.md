# Especificação Técnica: Estabilização de Servidor Estático em SingleFileEntryTest

**Ticket Associado:** #12  
**Branch:** `fix/12-singlefileentrytest-stability`  
**Norma:** GOST 1.4.0

## 1. Objetivo
Garantir execução determinística do teste de integração `tests/Integration/SingleFileEntryTest.php` sob instrumentação com Xdebug e alta concorrência de I/O em ambientes de CI.

## 2. Requisitos Técnicos
1. Escrever o arquivo fixture `source_post.html` antes de iniciar o processo do servidor estático embutido do PHP (`php -S`).
2. Adicionar verificação de aquecimento (*warm-up check*) HTTP contra o servidor estático antes de disparar o endpoint de webmention e o background worker (cron).
3. Garantir que timeouts e tolerâncias de rede local previnam falsos negativos sob latência de instrumentação de cobertura de código.

## 3. Critérios de Aceitação
- Teste `tests/Integration/SingleFileEntryTest.php` aprovado com 100% de sucesso mesmo com `XDEBUG_MODE=coverage`.
- Auditoria Gosplan (`cccp check --strict`) mantendo 100% de conformidade.

## 4. Resultados de Validação
- `SingleFileEntryTest.php` aprovado localmente com 19 asserções em 2.52s com `XDEBUG_MODE=coverage`.

