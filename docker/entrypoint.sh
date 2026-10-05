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

# Tratamento para o modo demo sandbox
if [ "${DEMO_MODE:-false}" = "true" ] || [ "${DEMO_MODE:-0}" = "1" ]; then
    echo ">> [indieinabox-demo] Demo mode enabled."
    # Inicializa o golden snapshot se ainda não existir
    if [ ! -f "/app/data/.demo_seed/demo.sqlite" ]; then
        echo ">> [indieinabox-demo] Initializing demo sandbox seed..."
        php /app/indieinabox.php demo seed \
            --fqdn "${DEMO_FQDN:-https://demo.indieinabox.2lp.in}" \
            --sitename "${DEMO_SITENAME:-IndieInABox Public Sandbox}" \
            --password "${DEMO_PASSWORD:-demo123}"
        chown -R www-data:www-data /app/data /app/content /app/public_html || true
    fi

    # Dispara daemon de reset periodico em background
    echo ">> [indieinabox-demo] Starting periodic reset daemon (interval: ${DEMO_RESET_INTERVAL:-3600}s)..."
    (
        while true; do
            sleep "${DEMO_RESET_INTERVAL:-3600}"
            echo ">> [indieinabox-demo] Triggering periodic sandbox reset..."
            php /app/indieinabox.php demo reset || echo ">> [indieinabox-demo] Reset exited with code $?"
            chown -R www-data:www-data /app/data /app/content /app/public_html || true
        done
    ) &
fi

# Se não for cron, executa o comando passado pelo CMD (ex: frankenphp run)
exec "$@"

