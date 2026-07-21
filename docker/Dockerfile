FROM php:8.3-cli

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    libonig-dev \
    libicu-dev \
    curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala o Bun (para o Tailwind CSS)
RUN curl -fsSL https://bun.sh/install | bash

# Adiciona o Bun ao PATH
ENV PATH="/root/.bun/bin:${PATH}"

# Instala extensões necessárias do PHP
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    mbstring \
    intl \
    zip \
    opcache

# Instala o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Define o diretório de trabalho
WORKDIR /app

# O comando padrão, caso queira rodar o servidor de dev
CMD ["php", "-S", "0.0.0.0:8081", "build.php"]
