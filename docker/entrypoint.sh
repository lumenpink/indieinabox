#!/bin/bash
set -e

# Se o executável não estiver no volume, copia da imagem
if [ ! -f "/app/indieinabox.php" ]; then
    echo ">> [indieinabox] /app is missing indieinabox.php. Populating from image..."
    cp /usr/src/indieinabox/indieinabox.php /app/indieinabox.php
    # Cria os diretórios caso não existam
    mkdir -p /app/data /app/content /app/public_html /app/public_gemini /app/public_gopher /app/public_media
    # Garante permissões adequadas
    chown -R www-data:www-data /app || true
else
    echo ">> [indieinabox] /app already contains indieinabox.php. Using existing file."
fi

# Tratamento para o modo cron
if [ "$1" = "cron" ]; then
    echo ">> [indieinabox] Starting cron worker (running every 5 minutes)..."
    while true; do
        php /app/indieinabox.php cron || echo ">> [indieinabox] Cron failed with exit code $?"
        sleep 300
    done
fi

# Se não for cron, executa o comando passado pelo CMD (ex: frankenphp run)
exec "$@"
