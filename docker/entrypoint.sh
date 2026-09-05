#!/bin/bash
set -e

# Se o diretório /app estiver montado mas vazio, copia o código fonte base
if [ ! -f "/app/build.php" ]; then
    echo ">> [indieinabox] /app is empty or missing build.php. Populating from image..."
    rsync -a --exclude 'data/*' --exclude 'content/*' --exclude 'public_*/*' /usr/src/indieinabox/ /app/
    # Cria os diretórios caso não existam
    mkdir -p /app/data /app/content /app/public_html /app/public_gemini /app/public_gopher /app/public_media
    # Garante permissões adequadas
    chown -R www-data:www-data /app || true
else
    echo ">> [indieinabox] /app already contains the application. Using existing files."
fi

# Tratamento para o modo cron
if [ "$1" = "cron" ]; then
    echo ">> [indieinabox] Starting cron worker (running every 5 minutes)..."
    while true; do
        php /app/cron.php || echo ">> [indieinabox] Cron failed with exit code $?"
        sleep 300
    done
fi

# Se não for cron, executa o comando passado pelo CMD (ex: frankenphp run)
exec "$@"
