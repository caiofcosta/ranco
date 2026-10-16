# RANÇO — edição pública (Labirinto)

Competição por coisas completamente inúteis.

O **RANÇO** é uma plataforma web de jogos sociais: grupos criam uma **mesa**, convidam
participantes e disputam partidas que alimentam um ranking próprio da mesa, com
acompanhamento em tempo real.

Esta é uma **edição pública independente** do projeto. Ela disponibiliza **apenas o jogo
Labirinto** e toda a estrutura de plataforma necessária para jogá-lo: contas e autenticação,
mesas, participantes, convites, regras compartilhadas de partida, conquistas/troféus,
painel administrativo e comunicação em tempo real. Os demais jogos do RANÇO foram removidos
deste recorte.

> Esta edição foi preparada a partir do código real do projeto, sem importar o histórico de
> versões. Funções não citadas aqui podem não estar presentes.

## O que esta edição inclui

- **Labirinto**: leve a bolinha da largada até a saída no menor tempo.
  - **Solo**: jogue contra o seu próprio melhor tempo (`/jogar/labirinto`).
  - **Em mesa**: uma partida com vários participantes; cada um joga a sua vez e os outros
    podem **assistir à jogada ao vivo**. Vence quem faz o menor tempo na rodada; as colocações
    rendem pontos e o ranking da mesa acumula entre as partidas.
- **Plataforma**: contas (link por e-mail, senha e “entrar com Google” opcional), amizades,
  mesas, convites de mesa, participantes locais (no mesmo aparelho), conquistas e troféus,
  provocações (o “Tom”), **painel administrativo** (Filament) e **Laravel Pulse**.

## Tecnologias e requisitos

- **PHP 8.5**, **Laravel**, **Livewire**, **Filament 5**
- **MariaDB** (o código usa recursos de MariaDB/MySQL)
- **Laravel Reverb** (WebSocket) para o tempo real, **filas** e **agendador**
- **Vite** + **Tailwind** para os assets (o Labirinto usa **Phaser**)
- **Docker** (desenvolvimento) — o único requisito na sua máquina

Requisito único para rodar localmente: **Docker** e **Docker Compose**. PHP, Composer, Node e
MariaDB rodam todos dentro dos containers; nada precisa ser instalado no host.

Tudo é operado pelo script `./dev.sh` (veja `./dev.sh` sem argumentos para a lista de comandos).

## Subir o projeto do zero

```bash
# 1. Clonar e entrar na pasta
git clone https://github.com/caiofcosta/ranco.git
cd ranco

# 2. Criar o .env a partir do exemplo
cp .env.example .env

# 3. Subir os serviços (constrói a imagem na primeira vez): app, banco, Reverb e agendador
./dev.sh up

# 4. Instalar as dependências PHP e gerar a chave da aplicação
./dev.sh composer install
./dev.sh artisan key:generate

# 5. Definir as chaves do Reverb no .env (qualquer valor local serve; não versione segredos reais)
#    Edite o .env e preencha, por exemplo:
#      REVERB_APP_ID=local-app-id
#      REVERB_APP_KEY=local-app-key
#      REVERB_APP_SECRET=local-app-secret
#      VITE_REVERB_APP_KEY=local-app-key   (precisa ser igual ao REVERB_APP_KEY)
#    Dica para gerar um valor aleatório: openssl rand -hex 16

# 6. Instalar as dependências JS e compilar os assets
./dev.sh npm ci
./dev.sh build

# 7. Criar o esquema do banco e os dados de demonstração (mapas do Labirinto, nomes de mesa, etc.)
./dev.sh migrar
./dev.sh artisan db:seed
```

Pronto. A aplicação fica em **http://127.0.0.1:8080**.

> As portas podem ser trocadas no `.env` (`PORTA_APP`, `PORTA_BANCO`, `PORTA_REVERB`) se houver
> conflito na sua máquina. Esta edição usa o projeto Docker **`ranco`**, isolado de qualquer
> outra cópia (containers, redes e volumes próprios).

### Reiniciar os serviços depois

```bash
./dev.sh up      # sobe de novo (reaproveita a imagem)
./dev.sh down    # para os containers, sem apagar o banco
```

Os serviços de tempo real sobem junto com o `up`: o **Reverb** (WebSocket) e o **agendador**
(`schedule:work`, que processa prazos das partidas e limpa jogadas solo abandonadas). As **filas**
usam o driver `database`.

## Criar o administrador do sistema

Não há administrador com senha fixa. O acesso ao Painel e ao Pulse é liberado por **e-mail**:
quem tiver o e-mail listado em `RANCO_ADMINISTRADORES` (no `.env`, separados por vírgula) e fizer
login com esse e-mail entra como administrador.

```env
RANCO_ADMINISTRADORES=voce@exemplo.com
```

Depois:

1. Crie uma conta normalmente no site (cadastro por e-mail). Em desenvolvimento, os e-mails não
   são enviados de verdade — veja o link de acesso/confirmação com `./dev.sh emails`.
2. Faça login com o e-mail que está em `RANCO_ADMINISTRADORES`.
3. Acesse o Painel e o Pulse.

## Acessar jogo, painel e monitoramento

- **Site / jogar**: http://127.0.0.1:8080
- **Labirinto solo**: http://127.0.0.1:8080/jogar/labirinto
- **Painel administrativo**: http://127.0.0.1:8080/painel *(só administradores)*
- **Laravel Pulse**: http://127.0.0.1:8080/pulse *(só administradores)*

## Testar a interação entre duas sessões

O tempo real aparece quando duas pessoas (ou duas sessões) estão na mesma mesa:

1. Abra **dois navegadores** (ou um normal e outro anônimo) e faça login com **duas contas**
   diferentes. Em desenvolvimento, pegue os links de acesso com `./dev.sh emails`.
2. Com a conta A, crie uma mesa e **convide** a conta B (ou adicione um “participante local”,
   que joga no mesmo aparelho).
3. Com a conta B, aceite o convite e entre na mesa.
4. A (administradora da mesa) escolhe **Labirinto**, confirma e **inicia a partida**.
5. Na vez de um participante, o outro vê o botão **Assistir** e acompanha a jogada **ao vivo**;
   ao terminar a vez, a vez passa para o próximo e a tela de todos se atualiza sozinha.
6. Confira o fim da partida (resultado e colocações) e inicie uma **nova partida** para ver o
   ranking da mesa acumular.

## Fluxo de eventos, canais e Reverb (resumo)

- O backend transmite eventos de mesa/partida pelo **Laravel Reverb** (WebSocket). O navegador
  escuta por **Laravel Echo**.
- O backend, dentro do Docker, fala com o Reverb pelo host interno `reverb`; o **navegador** usa
  os endereços `VITE_REVERB_*` (host/porta publicados). Por isso `VITE_REVERB_APP_KEY` precisa ser
  igual ao `REVERB_APP_KEY`.
- Há canais para as atualizações da mesa (quem está online, de quem é a vez, começo/fim de
  partida), para os convites da conta e para a lista de mesas.
- O **acompanhamento da jogada** (“Assistir”) é uma transmissão cliente-a-cliente: quem está
  jogando publica quadros da própria jogada no canal da mesa e quem assiste os desenha.

## Testes

```bash
./dev.sh test          # testes PHP (Laravel) + testes JavaScript (Vitest)
./dev.sh teste-banco   # só os testes dos repositórios no MariaDB
```

Os testes PHP usam o banco `ranco_testes`, criado automaticamente pelo `docker-compose`.

## Limitações conhecidas e problemas comuns

- **Apenas o Labirinto** está disponível nesta edição. URLs de outros jogos respondem “OPS!”
  (HTTP 404), por desenho.
- **E-mails não saem em desenvolvimento** (`MAIL_MAILER=log`): use `./dev.sh emails` para ver os
  links de acesso, convite e confirmação.
- **Entrar com Google** e o **túnel do Cloudflare** são opcionais e ficam desligados sem as
  credenciais no `.env`. Esta edição não depende de nenhum domínio, túnel ou serviço privado.
- **O tempo real não conecta**: confira se o serviço `reverb` está no ar (`./dev.sh up`), se
  `VITE_REVERB_APP_KEY` é igual a `REVERB_APP_KEY` e se você rodou `./dev.sh build` após mudar o
  `.env` (os valores `VITE_*` entram no build dos assets).
- **Erro de permissão em `storage/` ou `bootstrap/cache/`**: a imagem casa o usuário do container
  com o seu (`UID`/`GID`); se precisar, exporte `UID`/`GID` antes do `./dev.sh up`.
- **Tela sem estilo / assets faltando**: rode `./dev.sh build`.
- **“No application encryption key”**: rode `./dev.sh artisan key:generate`.

## Produção / deploy (opcional)

O suporte a produção (`producao.sh`, `docker-compose.producao.yml`, `Dockerfile.producao`) é
**opcional** e está incluído sem credenciais reais. O arquivo `docker/producao/env.exemplo`
traz um modelo de `.env` de produção, com túnel nomeado do **Cloudflare** opcional
(`CLOUDFLARE_TUNNEL_TOKEN`). Preencha os valores do seu próprio ambiente antes de usar.

## Licença

**Pendente.** Este repositório ainda **não** tem uma decisão de licença e **não** é, por ora, um
projeto open source licenciado. Defina uma licença (e adicione um arquivo `LICENSE`) antes de
qualquer abertura pública. As dependências de terceiros mantêm suas próprias licenças.
