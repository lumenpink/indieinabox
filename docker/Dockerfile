# ==========================================
# STAGE 1: Build & Compilação
# ==========================================
FROM php:8.4-cli-alpine AS builder

# Instala ferramentas de compilação, git e dependências para extensões
RUN apk add --no-cache bash curl git unzip nodejs npm

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instala Bun para build de assets (Tailwind, etc.)
RUN npm install -g bun

WORKDIR /build

# Copia arquivos de dependência primeiro para aproveitar cache
COPY composer.json composer.lock* ./
# Removemos o "|| true": se o Composer falhar, o build DEVE quebrar aqui
RUN composer install --no-interaction --prefer-dist

# Copia todo o código-fonte
COPY . .

# Se houver build de CSS/JS com Bun antes de compilar o PHP:
# RUN bun install && bun run build

# Executa o compilador que gera o indieinabox.php único
RUN php compile.php

# Valida se o artefato foi realmente gerado e tem sintaxe válida
RUN test -f indieinabox.php && php -l indieinabox.php


# ==========================================
# STAGE 2: Imagem Final de Produção (Runtime)
# ==========================================
FROM dunglas/frankenphp:php8.4-alpine

# Apenas extensões estritamente necessárias para o runtime
RUN install-php-extensions \
    pdo_sqlite \
    mbstring \
    intl \
    zip \
    gd \
    exif \
    opcache

WORKDIR /app

# Configuração do Caddy
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copia EXCLUSIVAMENTE o artefato compilado para o template base
RUN mkdir -p /usr/src/indieinabox
COPY --from=builder /build/indieinabox.php /usr/src/indieinabox/indieinabox.php

# Entrypoint enxuto
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]