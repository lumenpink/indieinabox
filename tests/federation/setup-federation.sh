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
    # Copia para o diretório de dados montado no container
    cp indieinabox.php data/indieinabox.php
    echo "Local build updated in data/indieinabox.php!"
    exit 0
fi

if [ "$COMMAND" == "--wipe" ]; then
    echo "Wiping federation environment..."
    docker compose -f docker-compose.federation.yml down -v
    echo "Environment wiped."
    exit 0
fi

if [ "$COMMAND" == "--seed" ]; then
    echo "Seeding federation environment with test posts..."
    
    # 1. Setup Mastodon
    echo ">> Setting up Mastodon DB and User..."
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails db:setup"
    docker exec mastodon_web bash -c "RAILS_ENV=production bin/tootctl accounts create admin --email admin@${MASTODON_DOMAIN} --confirmed --role admin"
    
    echo ">> Creating Mastodon Posts (via Rails Runner)..."
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails runner \"
      account = Account.find_by(username: 'admin')
      status = Status.create!(account: account, text: 'Olá do Mastodon local! 🐘', visibility: :public)
      puts 'Mastodon post created: ' + status.uri
    \""

    # 2. Setup Pixelfed
    echo ">> Setting up Pixelfed DB and User..."
    docker exec pixelfed_web php artisan migrate --force
    docker exec pixelfed_web php artisan user:create --name admin --email admin@${PIXELFED_DOMAIN} --username admin --password adminpass --is_admin
    
    echo ">> Creating Pixelfed Posts..."
    # No Pixelfed, usar o tinker é mais fácil para testes simples
    docker exec pixelfed_web php artisan tinker --execute="
      \$user = App\User::where('username', 'admin')->first();
      \$status = new App\Status();
      \$status->profile_id = \$user->profile->id;
      \$status->caption = 'Primeira foto no Pixelfed local 📸';
      \$status->visibility = 'public';
      \$status->save();
    "
    
    # 3. Setup Misskey
    # Como o Misskey não tem uma CLI robusta de usuários,
    # usamos a API nativa dele (o primeiro registro ganha admin)
    echo ">> Waiting for Misskey to be ready..."
    sleep 5
    echo ">> Creating Misskey User via API..."
    docker run --rm --network federation_default curlimages/curl -s -X POST http://misskey_web:3000/api/signup -H "Content-Type: application/json" -d '{"username":"admin", "password":"adminpass"}' || true
    
    echo ">> Creating Misskey Post via API..."
    docker run --rm --network federation_default curlimages/curl -s -X POST http://misskey_web:3000/api/notes/create -H "Content-Type: application/json" -d '{"text":"Hello from Misskey 🦊"}' || true

    # 4. Cross-Interactions (Follows)
    # Exemplo: Mastodon segue o admin do Misskey
    echo ">> Performing cross-interactions (Mastodon follows Misskey)..."
    docker exec mastodon_web bash -c "RAILS_ENV=production bundle exec rails runner \"
      mastodon_account = Account.find_by(username: 'admin')
      misskey_uri = 'admin@${MISSKEY_DOMAIN}'
      ResolveAccountService.new.call(misskey_uri)
      target_account = Account.find_by(domain: '${MISSKEY_DOMAIN}', username: 'admin')
      FollowService.new.call(mastodon_account, target_account) if target_account
    \""
    
    echo "Data seeded automatically! The platforms are federating."
    exit 0
fi

echo "Usage: ./setup-federation.sh [--wipe | --seed | --update]"
