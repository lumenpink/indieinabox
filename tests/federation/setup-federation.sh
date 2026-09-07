#!/bin/bash
set -e

# Garante que estamos rodando da pasta correta
cd "$(dirname "$0")"

COMMAND=$1

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
    docker run --rm -v "$(pwd)/data:/data" alpine chown $(id -u):$(id -g) /data || true
    # Copia para o diretório de dados montado no container
    cp indieinabox.php data/indieinabox.php
    echo "Local build updated in data/indieinabox.php!"
    exit 0
fi

if [ "$COMMAND" == "--wipe" ]; then
    echo "Wiping federation environment..."
    # Remove volumes (se existirem) e os bind mounts locais
    docker compose down -v
    echo "Wiping local bind mount data..."
    cd ../../
    docker run --rm -v "$(pwd)/data:/data" alpine sh -c "\
        rm -rf /data/federation_db /data/federation_redis /data/mastodon_public /data/misskey_files /data/pixelfed_app && \
        mkdir -p /data/federation_db /data/federation_redis /data/mastodon_public /data/misskey_files && \
        mkdir -p /data/pixelfed_app/app/public /data/pixelfed_app/framework/views /data/pixelfed_app/framework/cache /data/pixelfed_app/framework/sessions /data/pixelfed_app/logs && \
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
    
    echo "Starting containers..."
    docker compose up -d
    
    echo ">> Waiting for apps to initialize (Health checks)..."
    function wait_for() {
        local name=$1
        local url=$2
        local method=${3:-GET}
        echo -n "Waiting for $name..."
        until docker run --rm --network federation_default curlimages/curl -s -X $method $url > /dev/null; do
            echo -n "."
            sleep 2
        done
        echo " Ready!"
    }

    wait_for "Mastodon" "http://mastodon_web:3000/api/v1/instance"
    wait_for "Pixelfed" "http://pixelfed_web:8080/api/v1/instance"
    wait_for "Misskey" "http://misskey_web:3000/api/meta" "POST"
    wait_for "IndieInABox" "http://federation_indieinabox:80/"
    
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
    
    docker cp data/media pixelfed_web:/tmp/media
    docker exec --user root pixelfed_web chown -R 33:33 /tmp/media || true
    docker exec pixelfed_web php artisan tinker --execute="
      \$user = App\\Models\\User::where('username', 'aaron')->first();
      if (!\$user->email_verified_at) { \$user->email_verified_at = now(); \$user->save(); }
      \$profile = \$user->profile;
      \$profile->name = 'Aaron Pixelfed';
      \$profile->bio = 'Sou um bot de teste no Pixelfed.';
      \$profile->save();
      \$avatar = App\\Models\\Avatar::firstOrNew(['profile_id' => \$profile->id]);
      \$avatar->media_path = 'public/avatars/default.png';
      \$avatar->change_count = 1;
      \$avatar->save();
    "

    # 3. Initialize Misskey Profile
    echo ">> Fixing Misskey Permissions..."
    docker exec --user root misskey_web chown -R misskey:misskey /misskey/files || true
    docker cp data/media misskey_web:/tmp/media
    docker exec --user root misskey_web chown -R misskey:misskey /tmp/media || true
    
    echo ">> Initializing Misskey Admin User via API..."
    MISSKEY_TOKEN=$(docker run --rm --network federation_default curlimages/curl -s -X POST http://misskey_web:3000/api/admin/accounts/create -H "Content-Type: application/json" -d '{"username":"aaron", "password":"aaronpass"}' | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
    
    if [ -n "$MISSKEY_TOKEN" ]; then
        echo ">> Successfully created Misskey Admin and obtained token."
        AVATAR_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/drive/files/create -H "Content-Type: multipart/form-data" -F "i=$MISSKEY_TOKEN" -F "file=@/tmp/media/avatar_misskey.png" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        HEADER_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/drive/files/create -H "Content-Type: multipart/form-data" -F "i=$MISSKEY_TOKEN" -F "file=@/tmp/media/header_misskey.png" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/i/update -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"name\":\"Aaron Misskey\", \"description\":\"Sou um bot de teste no Misskey.\", \"avatarId\":\"$AVATAR_ID\", \"bannerId\":\"$HEADER_ID\"}"
    fi

    # 4. Cross-Interactions (Follows BEFORE Posts!)
    echo ">> Performing cross-interactions (Follows)..."
    
    # Mastodon Follows Pixelfed and Misskey
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails runner \"
      mastodon_account = Account.find_by(username: 'aaron')
      ['aaron@${MISSKEY_DOMAIN}', 'aaron@${PIXELFED_DOMAIN}'].each do |uri|
        ResolveAccountService.new.call(uri)
        domain = uri.split('@').last
        target_account = Account.find_by(domain: domain, username: 'aaron')
        FollowService.new.call(mastodon_account, target_account) if target_account
      end
    \""
    
    # Misskey Follows Mastodon and Pixelfed
    if [ -n "$MISSKEY_TOKEN" ]; then
        MD_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/ap/show -H "Content-Type: application/json" -d "{\"uri\":\"https://${MASTODON_DOMAIN}/users/aaron\"}" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        if [ -n "$MD_ID" ]; then
            docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/following/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"userId\":\"$MD_ID\"}" > /dev/null
        fi
        
        PX_ID=$(docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/ap/show -H "Content-Type: application/json" -d "{\"uri\":\"https://${PIXELFED_DOMAIN}/users/aaron\"}" | grep -o '"id":"[^"]*"' | head -n1 | cut -d'"' -f4)
        if [ -n "$PX_ID" ]; then
            docker exec misskey_web curl -s -X POST http://127.0.0.1:3000/api/following/create -H "Content-Type: application/json" -d "{\"i\":\"$MISSKEY_TOKEN\", \"userId\":\"$PX_ID\"}" > /dev/null
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
    
    # Pixelfed Posts
    docker exec pixelfed_web php artisan tinker --execute="
      \$user = App\\Models\\User::where('username', 'aaron')->first();
      \$profile = \$user->profile;
      \$status = new App\\Models\\Status();
      \$status->profile_id = \$profile->id;
      \$status->caption = 'Primeira foto no Pixelfed local 📸';
      \$status->rendered = 'Primeira foto no Pixelfed local 📸';
      \$status->visibility = 'public';
      \$status->save();
    "
    
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
    
    echo "Data seeded automatically! The platforms are federating."
    
    echo "Data seeded automatically! The platforms are federating."
    exit 0
fi
