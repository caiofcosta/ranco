#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
export PATH="${HOME}/.local/bin:${PATH}"
export PORTA_REVERB="${PORTA_REVERB:-8111}"

usage() {
  cat <<'EOF'
Uso: ./dev.sh <comando>

  up           Sobe o Laravel e o MariaDB
  down         Para os containers
  test         Testes do Laravel e do JavaScript
  smoke        Confere se o Laravel responde (/up)
  build        Gera os assets do Laravel (Vite)
  migrar       Aplica as migrações pendentes no MariaDB
  teste-banco  Só os testes de banco (repositórios do jogo no MariaDB)
  prazos       Processa os prazos vencidos (o mesmo que o agendador faz)
  emails       Mostra os e-mails gerados em desenvolvimento (vão para o log)
  artisan      Roda um comando do Artisan. Ex.: ./dev.sh artisan route:list
  composer     Roda o Composer no container. Ex.: ./dev.sh composer require pacote
  npm          Roda o npm do Laravel no container. Ex.: ./dev.sh npm run dev
EOF
}

# Projeto Docker próprio (ranco) desta edição: containers, redes e volumes isolados de qualquer
# outra cópia do RANÇO e da produção (producao.sh).
compose() {
  docker compose -p ranco -f docker-compose.yml -f docker-compose.dev.yml "$@"
}

# Comandos do Laravel rodam como www-data, que na imagem tem o mesmo UID do usuário do host.
laravel() {
  compose run --rm --user www-data -e npm_config_cache=/tmp/.npm app "$@"
}

# O ambiente de dev define APP_ENV=local; os testes precisam de testing.
laravel_teste() {
  compose run --rm --user www-data -e npm_config_cache=/tmp/.npm -e APP_ENV=testing -e DB_DATABASE=ranco_testes app "$@"
}

wait_http() {
  local url="$1"
  local attempts="${2:-30}"
  local i
  for i in $(seq 1 "$attempts"); do
    if curl -fsS "$url" >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  echo "Timeout esperando ${url}" >&2
  return 1
}

case "${1:-}" in
  up)
    compose up --build -d --remove-orphans
    echo "Laravel: http://127.0.0.1:8080"
    echo "Painel:  http://127.0.0.1:8080/painel"
    ;;
  down)
    compose down
    ;;
  test)
    shift
    if [ "$#" -gt 0 ]; then
      # Com argumentos, filtra o PHPUnit no ambiente de teste. Ex.: ./dev.sh test --filter='MeuTeste'
      laravel_teste php artisan test "$@"
    else
      laravel_teste php artisan test
      laravel npm run test:js
    fi
    ;;
  smoke)
    wait_http "http://127.0.0.1:8080/up"
    echo "Laravel respondendo em http://127.0.0.1:8080"
    ;;
  build)
    # O deploy (docker run como root) pode deixar arquivos com dono root; corrige só se precisar.
    if [ -n "$(find node_modules public/build -user root -print -quit 2>/dev/null)" ]; then
      compose run --rm --user root app chown -R www-data:www-data node_modules public/build
    fi
    laravel npm run build
    ;;
  migrar)
    laravel php artisan migrate
    ;;
  teste-banco)
    laravel_teste php artisan test tests/Feature/Banco
    ;;
  prazos)
    laravel php artisan partidas:processar-prazos
    ;;
  emails)
    laravel sh -c "grep -A40 -E 'To: |Subject: ' storage/logs/laravel.log 2>/dev/null | tail -80 || echo 'Nenhum e-mail gerado ainda.'"
    ;;
  artisan)
    shift
    laravel php artisan "$@"
    ;;
  composer)
    shift
    laravel composer "$@"
    ;;
  npm)
    shift
    laravel npm "$@"
    ;;
  *)
    usage
    exit 1
    ;;
esac
