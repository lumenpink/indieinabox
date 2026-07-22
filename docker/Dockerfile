FROM dunglas/frankenphp:php8.4

# Instala dependências necessárias para o Bun
RUN apt-get update && apt-get install -y unzip && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala o Bun (para o Tailwind CSS)
RUN curl -fsSL https://bun.sh/install | bash

# Adiciona o Bun ao PATH
ENV PATH="/root/.bun/bin:${PATH}"

# O FrankenPHP tem um script mágico `install-php-extensions` que já lida com apt-get e dependências por baixo dos panos!
RUN install-php-extensions \
    pdo_sqlite \
    mbstring \
    intl \
    zip \
    gd \
    exif \
    opcache \
    @composer

# Define o diretório de trabalho
WORKDIR /app

# O FrankenPHP serve os arquivos a partir de /app/public por padrão.
# Como o projeto usa a pasta raiz e o build.php como roteador, injetamos nosso Caddyfile:
COPY docker/Caddyfile /etc/caddy/Caddyfile


