#!/usr/bin/env bash
# Operação da produção do RANÇO (pasta ranco-producao). Não tem comando de teste: os testes rodam
# nas cópias de desenvolvimento, nunca contra o banco de produção.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

usage() {
  cat <<'EOF'
Uso: ./producao.sh <comando>

  atualizar   Traz a main, gera a imagem, aplica migrações e seeders e reinicia os serviços
  subir       Sobe os serviços (o túnel só com CLOUDFLARE_TUNNEL_TOKEN no .env)
  parar       Para os serviços, sem apagar dados
  estado      Mostra os serviços e a saúde de cada um
  logs        Acompanha os logs. Ex.: ./producao.sh logs app
  artisan     Roda um comando do Artisan como www-data. Ex.: ./producao.sh artisan about
EOF
}

valor_do_env() {
  grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | tr -d '"'
}

if [ ! -f .env ]; then
  echo "Falta o .env de produção nesta pasta." >&2
  exit 1
fi
if [ "$(valor_do_env APP_ENV)" != production ]; then
  echo "Recusado: o .env desta pasta não tem APP_ENV=production." >&2
  exit 1
fi

compose() {
  local perfis=()
  if [ -n "$(valor_do_env CLOUDFLARE_TUNNEL_TOKEN)" ]; then
    perfis=(--profile tunel)
  fi
  docker compose -f docker-compose.producao.yml "${perfis[@]}" "$@"
}

artisan_avulso() {
  compose run --rm --user www-data app php artisan "$@"
}

case "${1:-}" in
  atualizar)
    if [ "$(git rev-parse --abbrev-ref HEAD)" != main ] || ! git diff --quiet HEAD; then
      echo "Recusado: a produção só recebe a main, sem alterações locais." >&2
      exit 1
    fi
    # Pasta única: só atualiza do remoto se ele existir.
    if git remote get-url origin >/dev/null 2>&1; then git pull --ff-only; fi
    # Docker build não alcança fonts.bunny.net neste servidor; docker run sim.
    # Etapa 1: instala vendor/ (necessário para o build de assets referenciar pacotes PHP).
    docker run --rm \
      -v "$ROOT:/work" \
      -w /work \
      composer:2 \
      install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction \
              --ignore-platform-reqs
    # Etapa 2: gera os assets com Node (acessa fonts.bunny.net via rede padrão do docker run).
    VITE_REVERB_APP_KEY_VAL="$(valor_do_env REVERB_APP_KEY)"
    VITE_REVERB_HOST_VAL="$(valor_do_env VITE_REVERB_HOST)"
    test -n "$VITE_REVERB_APP_KEY_VAL" || { echo "Falta REVERB_APP_KEY no .env." >&2; exit 1; }
    test -n "$VITE_REVERB_HOST_VAL"    || { echo "Falta VITE_REVERB_HOST no .env." >&2; exit 1; }
    docker run --rm \
      -v "$ROOT:/work" \
      -w /work \
      -e "VITE_APP_NAME=$(valor_do_env APP_NAME)" \
      -e "VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY_VAL" \
      -e "VITE_REVERB_HOST=$VITE_REVERB_HOST_VAL" \
      -e "VITE_REVERB_PORT=$(valor_do_env VITE_REVERB_PORT)" \
      -e "VITE_REVERB_SCHEME=$(valor_do_env VITE_REVERB_SCHEME)" \
      node:22-bookworm-slim \
      sh -c "npm ci --no-audit --no-fund && npm run build"
    compose build app
    artisan_avulso migrate --force
    artisan_avulso db:seed --force
    compose up -d --remove-orphans
    compose ps
    # Os docker run acima rodam como root na pasta; devolve os arquivos ao usuário do host.
    docker run --rm -v "$ROOT:/work" node:22-bookworm-slim \
      chown -R "$(id -u):$(id -g)" /work/node_modules /work/public/build /work/vendor
    ;;
  subir)
    compose up -d
    ;;
  parar)
    compose stop
    ;;
  estado)
    compose ps
    ;;
  logs)
    shift
    compose logs -f --tail=100 "$@"
    ;;
  artisan)
    shift
    compose exec --user www-data app php artisan "$@"
    ;;
  *)
    usage
    ;;
esac
