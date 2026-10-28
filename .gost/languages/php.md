# GOST Language Specification: PHP
**Identificador da Stack:** `php`  
**Padrão Base:** GOST `2.0.0`

---

## 1. Visão Geral e Filosofia
Esta especificação define os requisitos de engenharia, ferramentas e padrões para desenvolvimento em **PHP** no ecossistema GOST.

O ecossistema adota **PHP 8.5+** como estado da arte canônico de runtime e compilação, mantendo **suporte reverso mandatório a partir do PHP 8.2** (`"php": "^8.2 || ^8.3 || ^8.4 || ^8.5"` ou `>=8.2` no `composer.json`). O código de bibliotecas e serviços deve ser validado para operar sem quebra no baseline 8.2, impondo `declare(strict_types=1);` em todos os arquivos de código-fonte.

---

## 2. Ferramental Mandatório de Testes & Qualidade

| Requisito GOST | Ferramenta Padrão | Comando de Validação no CI |
|---|---|---|
| **Test Runner** | **Pest** | `vendor/bin/pest` |
| **Cobertura (≥ 90%)** | **Pest Coverage** | `vendor/bin/pest --coverage --min=90` |
| **Testes de Mutação** | **Infection** | `vendor/bin/infection --min-msi=80 --min-covered-msi=85` |
| **Análise Estática** | **PHPStan** (Nível Máximo + Baseline 8.2) | `vendor/bin/phpstan analyse --level=max` |
| **Formatação Determinística** | **Laravel Pint** | `vendor/bin/pint --test` |
| **Auditoria de Segurança** | **Composer Audit** | `composer audit` |

---

## 3. Gestão de Dependências (Twelve-Factor)
1. **Lockfile Obrigatório:** O arquivo `composer.lock` **DEVE** estar commitado na raiz.
2. **Build Hermético:** Instalação em CI e containers executada estritamente com:
   ```bash
   composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
   ```
3. **Anti-Bloat:** Bibliotecas externas devem ser justificadas no ticket. Proibido pacotes utilitários de função única solucionáveis em PHP nativo.

---

## 4. Arquitetura de Domínio & SOLID Pragmático (Regra GOST-27)
1. **Pureza de Domínio (Zero I/O no Core):** O diretório `src/Domain/` é estritamente puro. É terminantemente proibido o uso de `PDO`, `curl_init`, `file_get_contents`, `file_put_contents`, `fopen`, `$_GET`, `$_POST`, `$_SERVER`, `$_ENV` ou `getenv` dentro de qualquer classe de domínio.
2. **Imutabilidade Canônica (`final readonly`):** Entidades, DTOs e Value Objects no domínio devem ser declarados obrigatoriamente como `final readonly class`.
3. **Estrutura de Pastas Canônica:**
   ```text
   src/
   ├── Domain/             # Modelos puros, final readonly, Backed Enums, Portas
   │   ├── Models/
   │   ├── Enums/          # Status, Roles (backed enums)
   │   └── Ports/          # Contratos e Interfaces de I/O
   ├── Application/        # Use cases / Handlers de orquestração
   │   └── UseCases/
   └── Infrastructure/     # Adaptadores concretos
       ├── Persistence/    # SQLite/Pdo e In-Memory Fakes
       ├── Http/           # Controllers e Middlewares
       └── Console/        # Comandos CLI
   tests/
   ├── Unit/               # Testes herméticos instantâneos com Fakes em memória
   └── Integration/        # Testes com fixtures de banco em SQLite temporário
   ```
4. **In-Memory Fakes:** Repositórios e portas de I/O em testes unitários utilizam fakes simples baseadas em arrays PHP (`private array $items = [];`) sem mocks dinâmicos. Fakes e adaptadores reais compartilham os mesmos testes de contrato (LSP).
5. **Parse, Don't Validate:** Validação de strings, inteiros e decodificação JSON ocorre na borda (Controllers/Request DTOs). O Core opera com primitivos confiáveis.
6. **Estados com Enums Nativos:**
   ```php
   enum ProcessStatus: string {
       case Pending = 'pending';
       case Active = 'active';
       case Completed = 'completed';
       case Failed = 'failed';
   }
   ```

---

## 5. Padrões de Sistema e Containers OCI
1. **Conformidade XDG:**
   Arquivos de configuração e dados respeitam `$XDG_*` com fallbacks:
   ```php
   $configHome = getenv('XDG_CONFIG_HOME') ?: (getenv('HOME') . '/.config');
   ```
2. **Dockerfile Canônico (Multi-stage com Alpine Non-Root e pcntl):**
   ```dockerfile
   FROM composer:2 AS vendor
   WORKDIR /app
   COPY composer.json composer.lock ./
   RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

   FROM php:8.5-cli-alpine
   RUN docker-php-ext-install pcntl && adduser -D -u 1000 appuser
   WORKDIR /app
   COPY --chown=appuser:appuser . /app
   COPY --chown=appuser:appuser --from=vendor /app/vendor /app/vendor
   USER 1000:1000
   ENTRYPOINT ["php", "/app/bin/console"]
   ```
3. **Streams:** Dados úteis em `STDOUT`, mensagens de log em `STDERR` (`error_log()` ou `fwrite(STDERR, ...)`).
