#!/bin/sh
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
    echo ">> [indieinabox-cron] Starting background worker..."
    while true; do
        if [ ! -f "/app/.config.php" ] && [ ! -f "/app/data/.config.php" ]; then
            echo ">> [indieinabox-cron] Database not configured yet. Waiting for web installer (checking in 30s)..."
            sleep 30
            continue
        fi

        php /app/indieinabox.php cron || echo ">> [indieinabox-cron] Cron execution ended with code $?"
        sleep 300
    done
fi

# Se não for cron, executa o comando passado pelo CMD (ex: frankenphp run)
exec "$@"
