#!/bin/sh
# Entrada dos containers de produção: gera os caches do Laravel com as variáveis do .env e inicia o
# processo do serviço. Os comandos do Artisan rodam como www-data, para o storage não ganhar
# arquivos do root.
set -e

como_www_data() {
    if [ "$(id -u)" = 0 ]; then
        su -s /bin/sh www-data -c "$*"
    else
        sh -c "$*"
    fi
}

como_www_data php artisan config:cache
como_www_data php artisan route:cache
como_www_data php artisan event:cache

# As views compiladas ficam no storage, compartilhado: só o app as gera.
if [ "$1" = apache2-foreground ]; then
    como_www_data php artisan view:cache
fi

exec docker-php-entrypoint "$@"
