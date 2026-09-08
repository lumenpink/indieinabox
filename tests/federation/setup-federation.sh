#!/bin/bash
set -e

# Garante que estamos rodando da pasta correta
cd "$(dirname "$0")"

COMMAND=$1

function show_usage() {
    echo "Usage: $0 [COMMAND]"
    echo "Commands:"
    echo "  --update       Update the local indieinabox binary into the testing environment"
    echo "  --wipe         Wipe all containers, volumes, and bind mounts"
    echo "  --fresh-start  Perform a clean wipe, start all containers, and seed test data"
    echo "  --seed         Seed the currently running environment with test data"
    exit 1
}

if [ "$COMMAND" != "--update" ] && [ "$COMMAND" != "--wipe" ] && [ "$COMMAND" != "--fresh-start" ] && [ "$COMMAND" != "--seed" ]; then
    show_usage
fi

if [ ! -f ".env" ]; then
    echo "Erro: Arquivo .env não encontrado!"
    echo "Copie o .env.example para .env e configure os domínios antes de continuar."
    exit 1
fi

source .env

if [ "$COMMAND" == "--update" ]; then
    echo "Compiling latest local version of indieinabox..."
    cd ../../
    # Gera o executável a partir do repositório local
    php compile.php
    # Garante que temos permissão na pasta data (apenas nela, não nos subdiretórios dos bancos)
    docker run --rm -v "$(pwd)/data:/data" alpine sh -c "chown $(id -u):$(id -g) /data && mkdir -p /data/indieinabox_app && chown -R $(id -u):$(id -g) /data/indieinabox_app" || true
    # Copia para o diretório de dados montado no container
    mkdir -p data/indieinabox_app && cp indieinabox.php data/indieinabox_app/indieinabox.php
    echo "Local build updated in data/indieinabox_app/indieinabox.php!"
    exit 0
fi

if [ "$COMMAND" == "--wipe" ]; then
    echo "Wiping federation environment..."
    # Remove volumes (se existirem) e os bind mounts locais
    docker compose down -v
    echo "Wiping local bind mount data..."
    cd ../../
    docker run --rm -v "$(pwd)/data:/data" alpine sh -c "\
        rm -rf /data/indieinabox_app /data/federation_db /data/federation_redis /data/mastodon_public /data/misskey_files /data/pixelfed_app && \
        mkdir -p /data/indieinabox_app /data/federation_db /data/federation_redis /data/mastodon_public /data/misskey_files && \
        mkdir -p /data/pixelfed_app/app/public /data/pixelfed_app/framework/views /data/pixelfed_app/framework/cache /data/pixelfed_app/framework/sessions /data/pixelfed_app/logs && \
        chown -R $(id -u):$(id -g) /data/indieinabox_app && \
        chown -R 991:991 /data/mastodon_public && \
        chown -R 1000:1000 /data/misskey_files && \
        chown -R 33:33 /data/pixelfed_app && \
        chown -R 70:70 /data/federation_db && \
        chown -R 999:999 /data/federation_redis" || true
    echo "Environment wiped and directories recreated with correct permissions."
    exit 0
fi

if [ "$COMMAND" == "--fresh-start" ]; then
    echo "Performing a fresh start: wiping, starting, and seeding..."
    $0 --wipe
    $0 --update
    
    echo "Starting caddy proxy first to generate local SSL..."
    docker compose up -d caddy-proxy
    
    echo -n "Waiting for internal SSL certificate..."
    until docker exec federation_caddy test -f /data/caddy/pki/authorities/local/root.crt 2>/dev/null; do
        echo -n "."
        sleep 1
    done
    echo " Ready!"
    
    echo "Starting all other containers..."
    docker compose up -d
    
    echo ">> Waiting for apps to initialize (Health checks)..."
    function wait_for() {
        local name=$1
        local url=$2
        local method=${3:-GET}
        echo -n "Waiting for $name..."
        until docker run --rm --network federation_default curlimages/curl -sk -X $method $url > /dev/null; do
            echo -n "."
            sleep 2
        done
        echo " Ready!"
    }

    wait_for "Mastodon" "https://${MASTODON_DOMAIN}/api/v1/instance"
    wait_for "Pixelfed" "https://${PIXELFED_DOMAIN}/api/v1/instance"
    wait_for "Misskey" "https://${MISSKEY_DOMAIN}/api/meta" "POST"
    wait_for "IndieInABox" "http://federation_indieinabox:80/"
    
    echo "Injecting Caddy Local CA into containers..."
    # Fix CA cert and directory permissions so Misskey node process can read it
    docker exec -u root mastodon_web bash -c "chmod 755 /caddy-data/caddy/pki /caddy-data/caddy/pki/authorities /caddy-data/caddy/pki/authorities/local"
    docker exec -u root mastodon_web bash -c "chmod 644 /caddy-data/caddy/pki/authorities/local/root.crt"
    # Mastodon (Debian base)
    docker exec -u root mastodon_web bash -c "cp /caddy-data/caddy/pki/authorities/local/root.crt /usr/local/share/ca-certificates/caddy-root.crt && update-ca-certificates"
    docker exec -u root mastodon_sidekiq bash -c "cp /caddy-data/caddy/pki/authorities/local/root.crt /usr/local/share/ca-certificates/caddy-root.crt && update-ca-certificates"
    # Pixelfed (Debian/Alpine base)
    docker exec -u root pixelfed_web bash -c "cp /caddy-data/caddy/pki/authorities/local/root.crt /usr/local/share/ca-certificates/caddy-root.crt && update-ca-certificates"
    
    echo "Restarting Mastodon and Pixelfed to pick up new CA..."
    docker compose restart mastodon-web mastodon-sidekiq pixelfed-web
    
    echo "Waiting for Mastodon and Pixelfed to come back up..."
    wait_for "Mastodon" "https://${MASTODON_DOMAIN}/api/v1/instance"
    wait_for "Pixelfed" "https://${PIXELFED_DOMAIN}/api/v1/instance"
    
    $0 --seed
    
    echo "Fresh start completed successfully!"
    exit 0
fi

if [ "$COMMAND" == "--seed" ]; then
    echo "Seeding federation environment with test posts..."
    
    echo ">> Generating dummy media..."
    mkdir -p data/media
    chmod 777 data/media

    docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 200x200 canvas:"#86b300" -pointsize 80 -fill white -gravity center -draw "text 0,0 'MK'" /data/avatar_misskey.png || true
    docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 800x400 canvas:"#86b300" -pointsize 80 -fill white -gravity center -draw "text 0,0 'Aaron Misskey'" /data/header_misskey.png || true

    docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 200x200 canvas:"#6364ff" -pointsize 80 -fill white -gravity center -draw "text 0,0 'MD'" /data/avatar_mastodon.png || true
    docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 800x400 canvas:"#6364ff" -pointsize 80 -fill white -gravity center -draw "text 0,0 'Aaron Mastodon'" /data/header_mastodon.png || true

    docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 200x200 canvas:"#ff0055" -pointsize 80 -fill white -gravity center -draw "text 0,0 'PX'" /data/avatar_pixelfed.png || true
    docker run --rm -v "$(pwd)/data/media:/data" dpokidov/imagemagick -size 800x400 canvas:"#ff0055" -pointsize 80 -fill white -gravity center -draw "text 0,0 'Aaron Pixelfed'" /data/header_pixelfed.png || true

    if [ ! -f data/media/dummy.mp4 ]; then
        docker run --rm -v "$(pwd)/data/media:/data" jrottenberg/ffmpeg:4.4-alpine -y -f lavfi -i testsrc=duration=1:size=320x240:rate=30 -f lavfi -i sine=frequency=1000:duration=1 -c:v libx264 -c:a aac -pix_fmt yuv420p /data/dummy.mp4 || true
    fi
    if [ ! -f data/media/dummy.mp3 ]; then
        docker run --rm -v "$(pwd)/data/media:/data" jrottenberg/ffmpeg:4.4-alpine -y -f lavfi -i sine=frequency=1000:duration=1 -i /data/avatar_mastodon.png -map 0:a -map 1:v -c:a libmp3lame -c:v mjpeg -id3v2_version 3 -metadata:s:v title="Album cover" -metadata:s:v comment="Cover (front)" /data/dummy.mp3 || true
    fi
    docker run --rm -v "$(pwd)/data/media:/data" alpine chmod -R 777 /data || true
    
    # 1. Initialize Mastodon Profile
    echo ">> Initializing Mastodon User Profile..."
    docker exec --user root mastodon_web chown -R mastodon:mastodon /mastodon/public/system || true
    docker cp data/media mastodon_web:/tmp/media
    docker exec --user root mastodon_web chown -R mastodon:mastodon /tmp/media || true
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails db:migrate" || true
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails db:seed" || true
    docker exec mastodon_web bash -c "RAILS_ENV=production bin/tootctl accounts create aaron --email aaron@hero.com --confirmed --role Admin" || true
    
    cat << 'EOF' > data/mastodon_init.rb
# encoding: utf-8
account = Account.find_by(username: 'aaron')
if account && account.user
  account.user.password = 'aaronpass'
  account.user.password_confirmation = 'aaronpass'
  account.user.approved = true
  account.update!(display_name: 'Aaron Mastodon', note: 'Sou um bot de teste no Mastodon.')
  account.avatar = File.open('/tmp/media/avatar_mastodon.png')
  account.header = File.open('/tmp/media/header_mastodon.png')
  account.save!
  account.user.save!
  puts 'Mastodon profile initialized.'
end
EOF
    docker cp data/mastodon_init.rb mastodon_web:/tmp/mastodon_init.rb
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails runner /tmp/mastodon_init.rb"

    # 2. Initialize Pixelfed Profile
    echo ">> Initializing Pixelfed DB and User Profile..."
    docker exec pixelfed_web php artisan migrate --force
    docker exec pixelfed_web php artisan storage:link || true
    docker exec pixelfed_web php artisan user:create --name "Aaron Pixelfed" --email aaron@hero.com --username aaron --password aaronpass --is_admin || true
    docker exec pixelfed_web php artisan passport:keys --force
    docker exec pixelfed_web php artisan passport:client --personal --no-interaction || true
    
    cat << 'EOF' > data/pixelfed_init.php
<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('username', 'aaron')->first();
if (!$user->email_verified_at) { $user->email_verified_at = now(); $user->save(); }
$profile = $user->profile;
$profile->name = 'Aaron Pixelfed';
$profile->bio = 'Sou um bot de teste no Pixelfed.';
$avatar = App\Models\Avatar::firstOrCreate(['profile_id' => $profile->id]);
$avatar->media_path = 'public/avatars/aaron.png';
$avatar->save();
$profile->save();
EOF
    
    docker cp data/pixelfed_init.php pixelfed_web:/tmp/pixelfed_init.php
    docker exec pixelfed_web mkdir -p /var/www/html/storage/app/public/avatars && docker cp data/media/avatar_pixelfed.png pixelfed_web:/var/www/html/storage/app/public/avatars/aaron.png
    docker exec pixelfed_web php /tmp/pixelfed_init.php
    docker exec pixelfed_web sed -i "/public static function isPublicIp/,/}/c\    public static function isPublicIp(string \$ip): bool\\n    {\\n        return true;\\n    }" app/Util/ActivityPub/Helpers.php
    docker exec pixelfed_web mkdir -p /var/www/html/storage/app/remcache
    docker exec pixelfed_web chown -R www-data:www-data /var/www/html/storage/app/remcache
    docker exec pixelfed_web php artisan instance:actor

    # 3. Initialize Misskey Profile
    echo ">> Initializing Misskey Admin User via API..."
    MISSKEY_CREATE_RESP=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/admin/accounts/create -H "Content-Type: application/json" -d '{"username":"aaron", "password":"aaronpass"}')
    echo ">> Misskey user creation response: $MISSKEY_CREATE_RESP"
    
    # Always inject a permanent API token via DB so it survives the restart
    echo ">> Injecting permanent Misskey API token via DB..."
    MISSKEY_USER_ID=$(docker exec federation_db psql -U misskey -d misskey -t -c "SELECT id FROM \"user\" WHERE username='aaron';" | tr -d ' \n\r')
    MISSKEY_TOKEN=$(openssl rand -hex 32)
    TOKEN_HASH=$(echo -n "$MISSKEY_TOKEN" | sha256sum | cut -d' ' -f1)
    TOKEN_ID=$(openssl rand -hex 16 | head -c 32)
    docker exec federation_db psql -U misskey -d misskey -c "
        INSERT INTO access_token (id, token, hash, \"userId\", permission, fetched, name)
        VALUES ('$TOKEN_ID', '$MISSKEY_TOKEN', '$TOKEN_HASH', '$MISSKEY_USER_ID',
                ARRAY['write:notes','write:following','write:drive','read:account','write:account']::varchar[], false, 'seed-script')
        ON CONFLICT DO NOTHING;" > /dev/null
    echo ">> Successfully obtained Misskey token: ${MISSKEY_TOKEN:0:8}..."
    
    echo ">> Fixing Misskey Permissions..."
    docker exec --user root misskey_web chown -R misskey:misskey /misskey/files || true
    docker cp data/media misskey_web:/tmp/media
    docker exec --user root misskey_web chown -R misskey:misskey /tmp/media || true
    
    
    if [ -n "$MISSKEY_TOKEN" ]; then
        echo ">> Successfully created Misskey Admin and obtained token."
        AVATAR_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/drive/files/create -H "Content-Type: multipart/form-data" -F "i=$MISSKEY_TOKEN" -F "file=@/tmp/media/avatar_misskey.png" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        HEADER_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/drive/files/create -H "Content-Type: multipart/form-data" -F "i=$MISSKEY_TOKEN" -F "file=@/tmp/media/header_misskey.png" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/i/update -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"name\":\"Aaron Misskey\", \"description\":\"Sou um bot de teste no Misskey.\", \"avatarId\":\"$AVATAR_ID\", \"bannerId\":\"$HEADER_ID\"}"
        
        echo ">> Fixing Misskey Permissions & Federation..."
        docker exec federation_db psql -U misskey -d misskey -c "UPDATE meta SET federation='all';"
        # Bypass setup wizard in DB
        docker exec federation_db psql -U misskey -d misskey -c "UPDATE \"user_profile\" SET \"clientData\" = '{\"hasSetupProfile\":true, \"accountSetupWizard\":-1}'::jsonb WHERE \"userId\" = (SELECT id FROM \"user\" WHERE username = 'aaron' AND host IS NULL);"
        # Restart Misskey to flush in-memory federation cache
        echo ">> Restarting Misskey to apply federation setting..."
        docker compose restart misskey-web
        echo -n "Waiting for Misskey..."
        until docker run --rm --network federation_default curlimages/curl -sk -X POST "https://${MISSKEY_DOMAIN}/api/meta" -H 'Content-Type: application/json' -d '{}' > /dev/null; do
            echo -n "."
            sleep 2
        done
        echo " Ready!"
    fi

    # 4. Initialize IndieInABox Profile
    echo ">> Initializing IndieInABox Profile..."
    docker exec -w /app federation_indieinabox php indieinabox.php profile edit --username aaron --name "Aaron (IndieInABox)" --bio "Sou um bot de teste no IndieInABox."
    # We copy the media to the container first
    docker cp data/media/avatar_iiab.png federation_indieinabox:/tmp/avatar_iiab.png
    docker cp data/media/header_iiab.png federation_indieinabox:/tmp/header_iiab.png
    docker cp data/media/dummy.mp3 federation_indieinabox:/tmp/dummy.mp3
    docker cp data/media/dummy.mp4 federation_indieinabox:/tmp/dummy.mp4
    docker exec -w /app federation_indieinabox php indieinabox.php profile media --avatar /tmp/avatar_iiab.png --background /tmp/header_iiab.png
    echo "IndieInABox Profile initialized."

    # 5. Cross-Interactions (Follows BEFORE Posts!)
    echo ">> Performing cross-interactions (Follows)..."
    # Mastodon Follows Pixelfed, Misskey, and IndieInABox
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails runner \"
      mastodon_account = Account.find_by(username: 'aaron')
      ['aaron@${MISSKEY_DOMAIN}', 'aaron@${PIXELFED_DOMAIN}', 'aaron@${INDIEINABOX_DOMAIN}'].each do |uri|
        begin
          target = ResolveAccountService.new.call(uri)
          FollowService.new.call(mastodon_account, target) if target
          puts 'Mastodon followed: ' + uri
        rescue => e
          puts 'Mastodon follow error for ' + uri + ': ' + e.message
        end
      end
    \""
    
    # Pixelfed Follows Mastodon, Misskey, IndieInABox
    docker cp data/pixelfed_follow.php pixelfed_web:/tmp/pixelfed_follow.php
    docker exec pixelfed_web php /tmp/pixelfed_follow.php

    # Misskey Follows Mastodon and Pixelfed
    if [ -n "$MISSKEY_TOKEN" ]; then
        MD_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/ap/show -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"uri\":\"https://${MASTODON_DOMAIN}/users/aaron\"}" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        if [ -n "$MD_ID" ]; then
            docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/following/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"userId\":\"$MD_ID\"}" > /dev/null
        fi
        
        PX_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/ap/show -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"uri\":\"https://${PIXELFED_DOMAIN}/users/aaron\"}" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        if [ -n "$PX_ID" ]; then
            docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/following/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"userId\":\"$PX_ID\"}" > /dev/null
        fi

        IB_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/ap/show -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"uri\":\"http://${INDIEINABOX_DOMAIN}/actor\"}" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        if [ -n "$IB_ID" ]; then
            docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/following/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"userId\":\"$IB_ID\"}" > /dev/null
        fi
    fi
    
    # 5. Create Posts!
    echo ">> Creating Posts..."
    
    # Mastodon Posts
    cat << 'EOF' > data/mastodon_posts.rb
# encoding: utf-8
account = Account.find_by(username: 'aaron')
Status.create!(account: account, text: 'Hello from local Mastodon! 🐘', visibility: :public)
media_img = MediaAttachment.create!(account: account, file: File.open('/tmp/media/avatar_mastodon.png'), type: :image)
Status.create!(account: account, text: 'Uma foto no Mastodon', visibility: :public, media_attachments: [media_img])
media_aud = MediaAttachment.create!(account: account, file: File.open('/tmp/media/dummy.mp3'), type: :audio)
Status.create!(account: account, text: 'Um áudio no Mastodon', visibility: :public, media_attachments: [media_aud])
media_vid = MediaAttachment.create!(account: account, file: File.open('/tmp/media/dummy.mp4'), type: :video)
Status.create!(account: account, text: 'Um vídeo no Mastodon', visibility: :public, media_attachments: [media_vid])
puts 'Mastodon posts created.'
EOF
    docker cp data/mastodon_posts.rb mastodon_web:/tmp/mastodon_posts.rb
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails runner /tmp/mastodon_posts.rb"
    
    # Pixelfed Posts (Using Tinker)
    cat << 'EOF' > data/pixelfed_posts.php
<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('username', 'aaron')->first();
$media = new App\Models\Media();
$media->profile_id = $user->profile_id;
$media->user_id = $user->id;
$media->media_path = 'public/avatars/aaron.png'; // Using the avatar just for test
$media->original_sha256 = hash_file('sha256', storage_path('app/public/avatars/aaron.png'));
$media->size = filesize(storage_path('app/public/avatars/aaron.png'));
$media->mime = 'image/png';
$media->filter_class = 'slumber';
$media->save();

$status = new App\Models\Status();
$status->profile_id = $user->profile_id;
$status->caption = 'Primeira foto no Pixelfed local 📸';
$status->rendered = '<p>Primeira foto no Pixelfed local 📸</p>';
$status->is_nsfw = false;
$status->visibility = 'public';
$status->save();

$media->status_id = $status->id;
$media->save();

App\Services\StatusService::reconcileStatusCounts($status);
echo "Pixelfed posts created.\n";
EOF
    docker cp data/pixelfed_posts.php pixelfed_web:/tmp/pixelfed_posts.php
    docker exec pixelfed_web php /tmp/pixelfed_posts.php
    
    # Misskey Posts
    if [ -n "$MISSKEY_TOKEN" ]; then
        IMG_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/drive/files/create -H "Content-Type: multipart/form-data" -F "i=$MISSKEY_TOKEN" -F "file=@/tmp/media/avatar_misskey.png" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        AUD_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/drive/files/create -H "Content-Type: multipart/form-data" -F "i=$MISSKEY_TOKEN" -F "file=@/tmp/media/dummy.mp3" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        VID_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/drive/files/create -H "Content-Type: multipart/form-data" -F "i=$MISSKEY_TOKEN" -F "file=@/tmp/media/dummy.mp4" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        
        docker run --rm --network federation_default curlimages/curl -s -X POST http://misskey_web:3000/api/notes/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"text\":\"Hello from Misskey 🦊\"}" > /dev/null
        docker run --rm --network federation_default curlimages/curl -s -X POST http://misskey_web:3000/api/notes/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"text\":\"Uma foto no Misskey\", \"fileIds\":[\"$IMG_ID\"]}" > /dev/null
        docker run --rm --network federation_default curlimages/curl -s -X POST http://misskey_web:3000/api/notes/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"text\":\"Um audio no Misskey\", \"fileIds\":[\"$AUD_ID\"]}" > /dev/null
        docker run --rm --network federation_default curlimages/curl -s -X POST http://misskey_web:3000/api/notes/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"text\":\"Um video no Misskey\", \"fileIds\":[\"$VID_ID\"]}" > /dev/null
    fi
    
    # IndieInABox Posts
    echo ">> Creating IndieInABox Posts..."
    docker exec -w /app federation_indieinabox php indieinabox.php post create --text "Hello from local IndieInABox! 📦"
    sleep 1
    docker exec -w /app federation_indieinabox php indieinabox.php post create --text "Uma foto no IndieInABox" --media /tmp/avatar_iiab.png
    sleep 1
    docker exec -w /app federation_indieinabox php indieinabox.php post create --text "Um áudio no IndieInABox" --media /tmp/dummy.mp3
    sleep 1
    docker exec -w /app federation_indieinabox php indieinabox.php post create --text "Um vídeo no IndieInABox" --media /tmp/dummy.mp4
    
    echo "Data seeded automatically! The platforms are federating."
    
    echo "Data seeded automatically! The platforms are federating."
    exit 0
fi
