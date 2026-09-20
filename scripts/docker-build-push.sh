#!/usr/bin/env bash

set -euo pipefail

# Script de build e publicação de imagens Docker para o Indieinabox
# Uso:
#   ./scripts/docker-build-push.sh [app|ci|all] [OPTIONS]
#
# Opções:
#   --push         Envia as imagens geradas para o registry (Docker Hub)
#   --tag <tag>    Define a tag primária (padrão: latest)
#   --repo <nome>  Define o namespace/repositório base (padrão: lumenpink)
#   --help         Mostra este texto de ajuda

TARGET="${1:-help}"
if [[ "$TARGET" =~ ^- ]]; then
    TARGET="help"
fi

shift || true

PUSH=false
TAG="latest"
DEFAULT_REPO="lumenpink"
# Tenta obter o usuário logado no docker, fallback para lumenpink
DOCKER_USER=$(docker info 2>/dev/null | grep -i 'Username:' | awk '{print $2}' || true)
REPO="${DOCKER_USER:-$DEFAULT_REPO}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --push)
            PUSH=true
            shift
            ;;
        --tag)
            TAG="$2"
            shift 2
            ;;
        --repo)
            REPO="$2"
            shift 2
            ;;
        --help|-h)
            TARGET="help"
            shift
            ;;
        *)
            echo "Opção desconhecida: $1"
            TARGET="help"
            shift
            ;;
    esac
done

show_help() {
    cat << HELP_EOF
Uso: $(basename "$0") [app|ci|all] [OPÇÕES]

Alvos disponíveis:
  app          Compila a imagem da aplicação (docker/Dockerfile)
  ci           Compila a imagem para os testes da CI (docker/Dockerfile.ci)
  all          Compila tanto app quanto ci

Opções:
  --push       Envia as imagens criadas para o Docker Hub
  --tag <tag>  Tag a ser aplicada (padrão: latest)
  --repo <rep> Nome de usuário / organização no Docker Hub (padrão: ${REPO})
  --help, -h   Exibe esta ajuda

Exemplos:
  $(basename "$0") app
  $(basename "$0") ci --push
  $(basename "$0") all --tag v1.0.0 --push
HELP_EOF
}

build_app() {
    local img="${REPO}/indieinabox:${TAG}"
    echo "=========================================="
    echo ">> Build da imagem da Aplicação: ${img}"
    echo "=========================================="
    docker build -f docker/Dockerfile -t "${img}" .

    if [[ "$PUSH" == "true" ]]; then
        echo ">> Publicando: ${img}"
        docker push "${img}"
    fi
}

build_ci() {
    local img_latest="${REPO}/indieinabox-ci:${TAG}"
    local img_php="${REPO}/indieinabox-ci:php8.4"
    echo "=========================================="
    echo ">> Build da imagem de CI: ${img_latest}"
    echo "=========================================="
    docker build -f docker/Dockerfile.ci -t "${img_latest}" -t "${img_php}" .

    if [[ "$PUSH" == "true" ]]; then
        echo ">> Publicando: ${img_latest}"
        docker push "${img_latest}"
        echo ">> Publicando: ${img_php}"
        docker push "${img_php}"
    fi
}

case "$TARGET" in
    app)
        build_app
        ;;
    ci)
        build_ci
        ;;
    all)
        build_app
        build_ci
        ;;
    help|*)
        show_help
        exit 0
        ;;
esac

echo ">> Concluído com sucesso!"
