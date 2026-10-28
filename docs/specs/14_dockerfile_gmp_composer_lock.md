# Especificação Técnica: Instalação de ext-gmp no Dockerfile e Sincronização de composer.lock

**Ticket Associado:** #14  
**Branch:** `fix/14-dockerfile-gmp-composer-lock`  
**Norma:** GOST 1.4.0

## 1. Objetivo
Garantir que a compilação do container Docker (`docker/Dockerfile`) conclua com sucesso instalando a extensão `ext-gmp` no estágio `builder` e sincronizando a plataforma de dependências no `composer.lock`, além de resguardar o workflow de CI para jobs dependentes de secrets de demonstração.

## 2. Requisitos Técnicos
1. No estágio 1 (`builder`) de `docker/Dockerfile`, adicionar o pacote de desenvolvimento `gmp-dev` via `apk add` e compilar/habilitar a extensão PHP via `docker-php-ext-install gmp`.
2. Sincronizar o hash de conteúdo e a seção de plataforma do `composer.lock` com o `composer.json` através de `composer update --lock`.
3. No arquivo de workflow `.forgejo/workflows/ci.yml`, adicionar salvaguarda no job `deploy-demo` para executar somente quando `secrets.DEMO_SSH_HOST != ''`.

## 3. Critérios de Aceitação
- Comando `docker build -f docker/Dockerfile .` executado localmente com saída limpa (código 0).
- Suíte de testes e linters (`composer sniffer`, `vendor/bin/pest`) aprovados sem regressões.
- Auditoria Gosplan (`cccp check --strict`) mantendo 100% de conformidade.

## 4. Resultados de Validação
- Falha original reproduzida localmente (Red Test): Composer rejeitando instalação por ausência de `ext-gmp`.
- Teste unitário `tests/Unit/Support/DockerConfigTest.php` aprovado com 2 testes e 5 asserções.
- Compilação do container Docker `docker build -f docker/Dockerfile .` executada e finalizada com 100% de sucesso (30/30 etapas concluídas sem erro).
- Sincronização do lockfile validada.
