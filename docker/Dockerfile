FROM dunglas/frankenphp:php8.4

# Instala dependências necessárias
RUN apt-get update && apt-get install -y unzip curl rsync git && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala o Bun (para o Tailwind CSS, etc, caso necessário no runtime)
RUN curl -fsSL https://bun.sh/install | bash
ENV PATH="/root/.bun/bin:${PATH}"

# O FrankenPHP tem um script mágico `install-php-extensions`
RUN install-php-extensions \
    pdo_sqlite \
    mbstring \
    intl \
    zip \
    gd \
    exif \
    opcache \
    @composer

# Define o diretório de trabalho padrão
WORKDIR /app

# Copia o Caddyfile customizado
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copia o código fonte atual para um diretório de backup/origem
# Isso permite que o entrypoint popule o volume /app caso esteja vazio
COPY . /usr/src/indieinabox/

# Copia e configura o entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
