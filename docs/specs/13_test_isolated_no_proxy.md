# Especificação Técnica: Isolamento de Loopback com no_proxy no SingleFileEntryTest

**Ticket Associado:** #13  
**Branch:** `fix/13-singlefileentrytest-no-proxy`  
**Norma:** GOST 1.4.0

## 1. Objetivo
Garantir que requisições HTTP internas de loopback (`127.0.0.1`, `localhost`) executadas durante o teste de integração `tests/Integration/SingleFileEntryTest.php` não sejam interceptadas por proxies HTTP corporativos ou de cache de pacotes (como Apt-Cacher NG ou proxies HTTP de CI), mantendo o isolamento estritamente no escopo do teste sem demandar alterações de infraestrutura global.

## 2. Requisitos Técnicos
1. Configurar variáveis de ambiente `no_proxy` e `NO_PROXY` contendo `127.0.0.1,localhost` no processo do PHP durante a execução do teste, preservando e restaurando o estado original ao finalizar (`finally`).
2. Prefixar a execução do subprocesso `$cronCmd` (`php indieinabox.php cron`) com `no_proxy=127.0.0.1,localhost NO_PROXY=127.0.0.1,localhost` para assegurar que chamadas cURL (`InboxProcessor::fetchUrl`) ignorem proxies de ambiente.
3. Não alterar definições globais de CI nem o ambiente fora da suíte de teste.

## 3. Critérios de Aceitação
- Teste `tests/Integration/SingleFileEntryTest.php` aprovado com 100% de sucesso mesmo quando `http_proxy` ou `HTTP_PROXY` estiverem configurados no ambiente externo apontando para um proxy HTTP de cache ou proxy inválido.
- Auditoria Gosplan (`cccp check --strict`) mantendo 100% de conformidade.

## 4. Resultados de Validação
- Reprodução exata de falha Red Test obtida configurando `http_proxy=http://172.17.0.1:3142` (com `403 Configuration error`).
- Validação Green Test aprovada em 19 asserções tanto no teste padrão sob `XDEBUG_MODE=coverage` quanto no modo compilado (`TEST_COMPILED=true`).
- `composer sniffer` aprovado com 100% dos 270 arquivos.
