# Publicação e segurança

Checklist de publicação; os itens abaixo não ficam automaticamente concluídos por abrir um túnel.
Em 2026-10-04 foi ativado um acesso temporário com Cloudflare Quick Tunnel: um túnel HTTPS para
Laravel e outro WSS para Reverb, ambos no próprio PC. Não foi alterado o encaminhamento de portas
do roteador nem configurado domínio na Hostinger.

O guia de uso, configuração, reinício, troca de URLs e retorno ao ambiente local está no Obsidian:
`/home/caio/Obsidian/CaioCofreGeral/game ranço/RANÇO - Infraestrutura.md`.
O login Google público ainda depende de configurar a URI de retorno.

## Produção (`ranco-producao`)

Decidido em 2026-10-05; em uso desde 2026-10-13. Atualizações posteriores seguem a seção
"Atualizar a produção" abaixo.

- Pasta `/home/caio/projetos/ranco-producao`, clone da `main`, é a única pasta de trabalho.
  O código é desenvolvido e testado aqui com `./dev.sh` (projeto Docker `ranco-dev`, banco próprio),
  nunca contra o banco de produção; vai ao ar pelo `./producao.sh atualizar`.
- Endereço `https://seu-dominio.exemplo`, por túnel nomeado do Cloudflare (DNS do domínio no
  Cloudflare; site e e-mail continuam na Hostinger). O túnel manda `/app/*` (WebSocket) para o
  Reverb e o resto para o app.
- `Dockerfile.producao`: código, dependências sem as de desenvolvimento e assets dentro da imagem.
  Os `VITE_*` entram no build; mudar o endereço exige gerar a imagem de novo.
- `docker-compose.producao.yml`: app, Reverb, fila, agendador, MariaDB e túnel, com
  `restart: unless-stopped`, logs com rotação e nenhuma porta publicada no PC. O túnel tem IP fixo
  (`172.30.50.10`), que é o `TRUSTED_PROXIES`, e só sobe com `CLOUDFLARE_TUNNEL_TOKEN` no `.env`.
- Ao iniciar, cada container gera os caches de configuração, rotas e eventos; as views, só o app.
- `.env` a partir de `docker/producao/env.exemplo`, fora do repositório. `APP_KEY`:
  `echo "base64:$(openssl rand -base64 32)"`. O banco começa vazio (migrations e seeders).

```bash
./producao.sh atualizar
./producao.sh estado
./producao.sh logs app
./producao.sh artisan about
```

## Atualizar a produção (`./producao.sh atualizar`)

**Regra:** só com autorização explícita da pessoa dona do projeto, para cada atualização. Autorização
anterior não vale para a seguinte.

**O que o comando faz, nesta ordem:**

1. Recusa se a pasta não estiver na `main` ou se houver alteração rastreada sem commit. Arquivos
   não rastreados não são verificados (ver o último item de "Efeitos" abaixo).
2. `git pull --ff-only`, se houver `origin`.
3. `composer install --no-dev` numa imagem `composer:2`, com a pasta montada. Grava `vendor/` na
   própria pasta, sem pacotes de desenvolvimento.
4. `npm ci` e `npm run build` numa imagem `node:22`, com os `VITE_*` lidos do `.env`. Grava
   `public/build` na própria pasta.
5. `docker compose build app`: gera `ranco-producao-laravel:atual`, copiando a pasta. Usa o `vendor/`
   do próprio Docker e o `public/build` da pasta.
6. `artisan migrate --force` e `artisan db:seed --force` no banco de produção. Os seeders rodam em
   toda atualização: `OpcoesSeeder`, `NomesDeMesaSeeder` e `MapasLabirintoSeeder`.
7. `docker compose up -d --remove-orphans`: recria só os containers cuja configuração ou imagem mudou.
   Os serviços são `app`, `reverb`, `fila`, `pulse` e `agendador`; `db` e `tunel` normalmente ficam.
8. Devolve a posse de `node_modules`, `public/build` e `vendor` ao usuário do host.

**Efeitos que precisam ser conhecidos antes de rodar:**

- **As dependências de desenvolvimento saem da pasta.** O passo 3 troca o `vendor/` da pasta por uma
  versão sem pacotes de dev. Depois disso `./dev.sh test` não roda até `./dev.sh composer install`,
  que restaura as dependências no container de dev a partir do `composer.lock`.
- **O `public/build` da pasta vira o build de produção**, com os `VITE_*` de produção. Quem testa em
  `localhost` passa a usar esses assets. Para voltar ao build de dev é preciso `./dev.sh build`, que
  sobrescreve o mesmo diretório e também exige autorização.
- **Arquivos não rastreados entram na imagem.** O cheque do passo 1 olha só o git, mas o `COPY` do
  Dockerfile leva a pasta inteira. Arquivos ainda não commitados podem ir para a imagem sem querer.
  Por isso o checklist abaixo tem o `git status -uall`.
- **Seeders e migrações rodam sempre.** Antes de rodar, conferir se o que vai ao ar tem migração nova
  (`git diff --stat` de `database/migrations/`) e se os seeders fazem o esperado.
- **Não há reversão automática.** Não existe comando de rollback. Voltar a uma versão anterior exige
  decisão e execução explícitas.
- **Não faz push.** Vai ao ar o `main` local. O `origin` só é lido, no `pull`.

**Checklist antes de rodar:**

1. Autorização explícita da pessoa dona do projeto, para esta atualização.
2. `git status -sb` e `git log -3`: branch `main`, árvore sem alteração rastreada, e o commit que vai
   ao ar é o esperado.
3. `git status --short -uall`: nenhum arquivo não rastreado que não deveria ir ao ar.
4. `.env` da pasta com `APP_ENV=production`, `REVERB_APP_KEY` e `VITE_REVERB_HOST` preenchidos. O
   script recusa se faltar algum; não é preciso ler o `.env` para isso.

**Depois de rodar:**

- `./producao.sh estado`: serviços `Up` e `app` `healthy`.
- `./producao.sh logs app`: sem erro de inicialização.
- Para retomar o dev: `./dev.sh composer install` (e `./dev.sh build` só com autorização).

## Antes do teste com amigos

- Banco sem porta publicada; senha forte fora do repositório.
- App em 127.0.0.1; a entrada pública é só o túnel. Nada aberto no roteador.
- APP_ENV=production, APP_DEBUG=false, APP_KEY próprio, código sem montagem no container.
- Proxies confiáveis configurados; APP_URL e redirect do Google com o subdomínio.
- Senha forte no painel; só o administrador do sistema acessa.
- Backup diário do banco, guardado fora do PC.
- Firewall bloqueando entrada por padrão; containers com restart automático.
- PC sem suspensão nem hibernação.
- Agendador ligado: serviço `agendador` do Docker (`schedule:work`) ou cron a cada minuto com
  `php artisan schedule:run`. Sem ele, a vez de quem saiu só é pulada quando alguém abre a mesa e
  as jogadas solo abandonadas não são apagadas.
- Como desligar: parar o túnel, o Reverb e os containers.

## Tempo real (WebSocket)

- Quick Tunnel: upgrade WSS, ping/pong e recebimento de mensagem publicada pelo Laravel
  verificados em 2026-10-04. Revalidar se mudar o túnel ou o ambiente; isso não substitui o teste
  de uma partida completa em navegadores separados.
- Reverb local em `127.0.0.1:8081`; manter sem publicação direta em outras interfaces.
- BROADCAST_CONNECTION=reverb e chaves REVERB_* próprias, fora do repositório; origens permitidas
  limitadas ao subdomínio público.
- O processo `php artisan reverb:start` sobe com o PC e volta sozinho após reinício, como os
  containers e o agendador.
- Eventos em fila: com QUEUE_CONNECTION=database, o processo `php artisan queue:work` também
  precisa subir sozinho com o PC, senão os eventos não saem. A alternativa é enviar sem fila.
- Canal privado da mesa: só membro ativo recebe evento.

## Antes da publicação

- Publicar juntos os itens 19 (aceite), 20 (amizade sem e-mail), 21 (registros de acesso)
  e 22 (documentos versionados), após a conferência dos textos e da implementação.
- Conferir, após login pelo túnel público, se `registros_de_acesso.ip` é o IP real do
  visitante e `ocorrido_em` está em UTC. Revalidar `TRUSTED_PROXIES` se o túnel mudar.
  A verificação anterior do Laravel não comprova a nova tabela. Porta de origem fica
  nula atrás do proxy enquanto não houver fonte confiável; não usar `X-Forwarded-Port`.
- Conferir `acessos:limpar-registros` no agendador diário e restringir acesso direto ao banco.
- Convites de amizade somente por id de conta existente, sem e-mail nem lista de enviados.
- Limite de cadastros por dia.
- Aviso de privacidade e caminho para excluir a conta.
- Autorização antes de mostrar nomes ou prints; uso de apelidos por padrão.
- Dados coletados listados (LGPD).
- Dependências revisadas (composer audit).
- Monitor externo de disponibilidade.
- Link da home testado em celular.

## Pontos já verificados no código

- `docker-compose.yml` publica MariaDB em `127.0.0.1:3306:3306`, Reverb em
  `127.0.0.1:8081:8081` e o app em `8080:80`. O app ainda atende em todas as interfaces.
- O código é montado no container (`.:/var/www/html`).
- `.env.example` tem `APP_ENV=local` e `APP_DEBUG=true`; no `.env` deste PC, o túnel foi
  ativado com `APP_DEBUG=false`. `APP_ENV` continua `local`.
- `config/trustedproxy.php` fornece `trustedproxy.proxies` ao middleware do Laravel instalado,
  usando `TRUSTED_PROXIES`. O gateway conferido de `ranco_default` é `172.26.0.1`; revalidar
  se a rede Docker for recriada. Não está sendo usada confiança irrestrita em proxies.
- O login tem limite de 5 erros por e-mail ou 20 por IP em 15 minutos (`FortifyServiceProvider`).
- O painel só aceita os e-mails de `RANCO_ADMINISTRADORES` (`User::canAccessPanel`).
- `ConvitesDeAmizade` não envia e-mail; endereço sem conta recebe resposta uniforme sem convite persistido.
- Reverb instalado no Compose, com `restart: unless-stopped`; `MesaMudou` usa
  `ShouldBroadcastNow`, sem aguardar fila para esse aviso. Origens permitidas em
  `REVERB_ALLOWED_ORIGINS` (padrão `*`; na produção, só o host público).
- `ranco-tunel-web` e `ranco-tunel-reverb` são containers avulsos, fora do Compose, sem reinício
  automático. Foram criados com cloudflared 2026.9.3, imagem fixada por digest.
- `APP_URL` usa o túnel web; `VITE_REVERB_*` usa o túnel WSS. As variáveis `REVERB_*` do
  Laravel mantêm o destino interno. Novas URLs exigem atualizar o `.env` e executar `./dev.sh build`.
- Parar os túneis não para o app nem o banco. O tempo real local também perde o caminho até
  restaurar `VITE_REVERB_HOST=127.0.0.1`, porta `8081`, esquema `http`, e recompilar os assets.
- A inicialização de toda a pilha e do agendador após reiniciar o PC permanece sem validação.
