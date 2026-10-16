# Funcionalidades do RANÇO

Detalhe de cada área do sistema (telas, jogos e regras de cada fluxo). Não é carregado automaticamente: leia só a seção da área antes de mexer nela. `grep -n '^## ' docs/funcionalidades.md` lista as seções.

## Início (Home autenticada)

- Mobile e desktop são composições separadas na mesma rota (`dashboard`, `mobile.*`): o mobile é o
  Livewire `navegacao.area-mobile` (`lg:hidden`); o desktop fica em `data-desktop-inicio`
  (`hidden lg:block`, breakpoint `lg`, 1024px) em `resources/views/dashboard.blade.php`.
- Ordem no desktop: saudação compacta (`inicio-cabecalho`), Resumo (Agora, Jogando, Mesas), Suas
  mesas ao lado de Amizades, Jogar sozinho ao lado de Destaque.
- Os três itens do Resumo vêm de `App\Navegacao\ResumoDaHome::itens($contexto)`, fonte única do
  mobile (`mobile-inicio`) e do desktop. No desktop os cards levam à seção "Suas mesas" da própria
  Início, porque `/agora`, `/jogando` e `/mesas` mostram a mesma tela. O destaque vem de
  `config/home-mobile.php` (`DestaqueDaHome`). A lista de jogos solo vem de `CatalogoDeJogos::disponiveis()`.
- "Suas mesas" (desktop): convites pendentes primeiro, depois as mesas administradas (selo
  "Administrador"), as participações (selo "Participante") e os resultados pendentes, com "Criar mesa"
  no fim das administradas. O bloco é `resources/views/inicio/suas-mesas.blade.php`, servido também por
  `inicio.suas-mesas`; `SuasMesasDaHome` é a fonte única da página e da atualização. Aceitar e recusar
  seguem como formulários (`inicio.convites.*`).
- Atualização ao vivo sem F5 (`resources/js/suas-mesas-em-tempo-real.js`): usa o canal privado da pessoa
  (`convite.recebido`, `mesas.mudaram`, `pessoa.ficou-invisivel`) e o `mesa.mudou` das mesas exibidas. O
  evento `mesas.mudaram` é emitido só em convite aceito, recusado ou cancelado, saída, remoção e
  visibilidade; não há evento por jogada.
- Limite de mesas: 5 mesas **administradas** por pessoa, contadas em `Plataforma::vagasDeMesa`. Participar
  de mesa de terceiros não consome vaga, e aceitar convite ou entrar por link não passa pelo limite.
- Texto de amizade: o e-mail só localiza uma conta existente; nenhuma tela pode sugerir envio de
  e-mail no convite de amizade.
- Os testes da mesa assertam os textos atuais das modais (Configurar, Resultados, Quem vai jogar?);
  ao mudar um texto dessas telas, ajuste o teste correspondente.

## Disponibilidade dos jogos

- O administrador do sistema ativa ou inativa os jogos em `/painel/jogos/disponibilidade`.
- A disponibilidade fica persistida em `jogos`; todos os jogos são criados ativos na migration.
  Inativos somem das listas de jogo solo e da escolha de jogo da mesa, e não iniciam tentativas
  solo nem novas partidas em mesa. A `Plataforma` revalida novas partidas e o início das partidas
  configuradas. Partidas já iniciadas e tentativas solo em andamento continuam.
- A URL de um jogo inativo e qualquer `/jogar/{jogo}` desconhecido exibem a mesma página “OPS!”,
  com status HTTP 404. Resultados e tentativas existentes continuam acessíveis.

## Jogo solo: Labirinto

- `/jogar` lista os jogos ativos; `/jogar/labirinto` permite escolher um mapa publicado ativo ou sortear.
  Exige autenticação e e-mail verificado, sem exigir mesa. Dez mapas fixos no
  `MapasLabirintoSeeder`, idempotente por `codigo_seed`, sem sobrescrever curadoria.
- Regras e serviços em `app/Dominio/Jogos/Labirinto`: `SoloLabirinto`, `TentativaSolo`
  e `CatalogoDeMapasLabirinto`. Portas de repositório no domínio, adaptadores em
  `app/Infra/Banco`. Transações e relógio são injetados; erros HTTP são traduzidos no controller.
- O POST de início cria UUID vinculado à pessoa e à versão publicada. Recarregar mantém
  essa versão. Publicar outra versão ou desativar o mapa não altera a tentativa existente.
- `TentativaSolo` valida tempo mínimo físico por desenho, máximo de 30 minutos
  (`VALIDADE_SEGUNDOS`) e tempo decorrido no servidor com tolerância de um segundo. Jogada solo
  não concluída em 30 minutos está abandonada: não conclui mais e é apagada, sem estatística.
  É plausibilidade, não comprovação de trajetória. Repetir uma conclusão com o mesmo tempo
  é idempotente; tempo diferente retorna 409. A trava da conta precede a leitura dos recordes.
- `tentativas_solo_labirinto` guarda tentativas e recordes por pessoa/mapa/versão. Empate não
  cria recorde. Resultado mostra tempo atual, recorde e data; Histórico abre os dez melhores.
  A ação principal do resultado é "Jogar de novo"; o resultado não oferece atalho para mesas.
  Jogadas de mesa e prévia administrativa não entram nesses registros.
- O catálogo aplica revisão otimista sob trava, publicação imutável e unicidade de nome
  atual e desenhos reservados, inclusive publicados/inativos. Uso em rodadas pode ser null;
  solo não incrementa esse contador. Administrador pode importar JSON e testar sem registro.
- `labirinto:limpar-tentativas` apaga em lotes só as jogadas não concluídas há mais de 30 minutos.
  Roda a cada 10 minutos pelo agendador (serviço `agendador` do Docker, `schedule:work`; na
  hospedagem, cron a cada minuto com `schedule:run`). O mesmo agendador processa os prazos das
  partidas a cada minuto (`partidas:processar-prazos`).
  Resultados concluídos e contas não são removidos. A tabela `melhores_resultados` permanece
  somente como legado preservado; não há conversão, pois os registros não identificam versão.
- Phaser entra por `resources/js/jogos/labirinto/entrada.js`, nunca pelo `app.js`.
  Alavanca abaixo do tabuleiro, arrastada com dedo ou mouse, como único controle;
  cronômetro começa no primeiro movimento e para na chegada. Direção e velocidade seguem
  o deslocamento do gesto; soltar para a bolinha. Ver `docs/labirinto-controles.md`.
  Tempo fora da aba continua contando. Prévia administrativa permite reinício sem recordes.
- Provocações: `solo.melhor_tempo` e `solo.sem_recorde`.
- Testes: `./dev.sh test`; JS: `./dev.sh npm run test:js`; build: `./dev.sh build`.
- Operação na main deve ser verificada separadamente: migrations, `MapasLabirintoSeeder`,
  build e agendador. Alterar esta documentação não executa nem confirma esses passos na main.

## Jogar a vez da partida

- Contrato com a tela da mesa: `mesas.jogar` (`GET /mesas/{id}/jogar`). Exige autenticação,
  e-mail verificado e membro ativo, pela trait `GaranteMembroDaMesa`. Sem partida em andamento,
  em pausa ou fora da vez, retorna para `mesas.mostrar`.
- Controller `App\Http\Controllers\Jogos\JogarVezController`; views em
  `resources/views/jogos/partida/`. Antes da tentativa, mostra "É a sua vez" e "Iniciar sua vez", junto do painel da partida.
  O POST `mesas.jogar.iniciar` chama `Plataforma::iniciarTentativa()`;
  repetir esse POST não reinicia uma tentativa existente.
- Com a tentativa iniciada, carrega `entrada-partida.js`, entrada própria do Vite. Reutiliza a
  mesma cena, mapa, movimento, colisões, controles e cronômetro do solo. Não carregar pelo
  `app.js`, pela mesa ou pela tela anterior ao início da tentativa.
- Na mesma aba, F5 retoma a posição mundial da bolinha por `sessionStorage`, isolada por mesa,
  participante, partida, rodada, desempate e versão do mapa. O cronômetro usa o tempo oficial
  desde `liberaEm` (fim da preparação), mesmo antes do primeiro movimento, e inclui o tempo de
  carregamento após o início da resposta; a tentativa não é reiniciada e o progresso só é apagado
  após confirmação do resultado.
- O POST JSON `mesas.jogar.resultado` recebe `tempo` em milissegundos e o contexto `partida`
  (posição na lista de partidas da mesa, começando em 1), `rodada` e `desempate`.
  Ambos os POSTs recebem também `jogada`, comprovante cifrado emitido no GET, vinculado à mesa,
  conta autenticada, participante e contexto. O navegador não escolhe por quem jogar.
  Validação HTTP: inteiro até 86.400.000 ms; mínimo físico calculado pelo desenho da rodada (4.000 ms somente no mapa legado), independente dos recordes e da expiração solo. `concluirTentativa()` calcula os efeitos no
  domínio; o retorno é `{ destino: URL de mesas.jogo com partida=N }`, mantendo a partida de origem
  mesmo após sua conclusão. Não usa resultado nem recorde solo.
- Quem está logado autoriza o pedido; quem joga é sempre `Partida::vezAtual()`. Pode jogar a
  própria conta ou o admin pela vez de um participante local (`Mesa::participanteLocal()`).
  Admin não joga por outra conta. A autorização é repetida dentro de `Plataforma::naMesa()`.
  `PartidaNaMesa::podeJogarAVez` cobre ambos os casos; `vezDeParticipanteLocal` orienta o texto
  "Jogar pela vez de {nome}", também no botão de início. As telas identificam o participante local.
- Contexto antigo não altera uma nova vez, inclusive entre dois locais na mesma rodada/sessão.
  Reenvio idêntico de resultado já gravado confirma o destino sem concluir novamente; outro tempo
  para a tentativa concluída é recusado.
- Erros de situação retornam HTTP 409 com `mensagem` e `pode_reenviar: false`; conflito de
  concorrência permite reenvio. A entrada preserva o tempo até receber confirmação.
- Limitação desta entrega: o GET de uma tentativa em andamento abre o tabuleiro novamente,
  conforme o fluxo da tarefa. Não há detecção automática de saída/queda nem interrupção por
  desconexão. Por isso, a tela informa o início no clique, sem prometer perda automática da vez.
- Testes HTTP: `tests/Feature/Jogos/JogarVezTest.php`; testes do envio:
  `tests/JavaScript/labirinto/envio-da-partida.test.js`. `./dev.sh test` executa PHP e JS.

## Tela do jogo e Labirinto no celular

- `mesas.mostrar` encaminha para `mesas.jogo` quando a partida está em andamento, inclusive
  pausada. `MostrarMesa` faz o mesmo ao receber o evento de tempo real ou iniciar a partida.
  Mensagens de erro dos POSTs que passam pela mesa são preservadas até chegar ao jogo.
- `MostrarJogoController` usa `GaranteMembroDaMesa` e reaproveita `JogarVezController` e
  `AssistirVezController`. `TelaDoJogo` projeta início, jogada, transmissão, espera ou fim,
  sem iniciar tentativas. Sem partida iniciada, volta à mesa. Conta invisível vai para Início.
- `PainelDoJogo` mantém o canal de presença e atualiza ordem/resultados, placar e ranking
  fora do canvas. Revalida acesso e contexto pelo evento `mesa-atualizada`, pela reconexão
  e por consulta a cada 30 segundos. Só troca de página quando muda o contexto da jogada;
  pausa, placar e ações do admin não remontam uma tentativa já iniciada.
- Sequencial incorpora a transmissão existente e mostra vez atual e próximos jogadores,
  incluindo locais. Paralelo mostra tentativas concluídas, seus tempos e quem falta;
  não carrega nem publica transmissão. Ambas as modalidades mostram o placar existente.
- Pausar/despausar, pular, admitir, remover e encerrar ficam em “Ações do admin”, secundárias.
  Chamam os wrappers da Plataforma e repetem a autorização no servidor. RankingDaMesa
  continua separado da pontuação da partida, sem novos cálculos na interface.
- Chegar à própria vez apresenta o tabuleiro travado, o aviso e o botão; somente o POST
  do clique reserva a tentativa. O tabuleiro libera após a preparação de 3 segundos;
  o cronômetro do resultado começa no primeiro movimento. Saída/desconexão segue o prazo
  configurado, sem perda imediata automática por desconexão.
- No fim, `mesas.jogo?partida=N` leva a `mesas.mostrar?resultado=N`, e a mesa abre a modal
  “Fim da partida” (vencedor, resultado final e frase da medalha), que só fecha pelo jogador
  (“Continuar na mesa” ou Esc). A frase da medalha aparece só nessa modal, não acima da mesa. O número N fica `#[Locked]` no `MostrarMesa` desde o mount:
  o tempo real e a próxima partida em preparação não trocam nem removem a modal; só iniciar a
  próxima partida leva todos ao jogo. Fechar chama `dispensarResultado()` e tira o `resultado`
  da URL; a mesa sem esse parâmetro não mostra o resultado de novo. A orientação de primeiro
  acesso não abre junto.
- No celular (abaixo de `md`) a mesma modal tem três regiões (`resources/css/resultado-da-partida.css`):
  cabeçalho fixo (“Fim de jogo!”, jogo e número da partida, troféu e vencedor, provocação em
  itálico com até duas linhas e “ver mais”), conteúdo com rolagem interna (“Resultado da
  partida” com medalha, colocação, nome e os pontos da partida como “+N pts”; “Ver rodadas”
  expansível com posição, resultado e pontos de cada rodada) e rodapé fixo com “Continuar na
  mesa”. Altura até 90dvh descontando as safe areas; a página de fundo não rola. O desktop
  mantém a apresentação simples. Os dados vêm de `App\Mesa\ResultadoDaPartida`, que só compõe
  `HistoricoDaMesa` (colocação, empate, pontos), `Medalhas::daPartida()` (medalhas gravadas),
  `PartidaNaMesa` (vencedor e frase) e as rodadas gravadas na `Partida`, sem recalcular nada.
- `resources/css/labirinto.css` põe o tabuleiro antes do título/status abaixo de 640px,
  no solo, na tentativa e no assistir. Nos modos jogáveis, a alavanca fica logo abaixo
  do tabuleiro. `instrucoes-labirinto` orienta arrastar a alavanca com dedo ou mouse,
  soltar para parar e afastar do centro para aumentar a velocidade.
- Testes: `TelaDoJogoTest`, fluxos existentes de mesa/jogar/assistir e testes JS da
  publicação em canal já inscrito. `./dev.sh test` executa PHP e JS; o PHPUnit usa
  limite de memória de 512 MB para a suíte inteira, sem alterar o limite da aplicação.

## Consulta de mesa encerrada

- `mesas.mostrar` usa `GaranteMembroDaMesa::mesaParaVisualizar()`: mesa aberta exige
  membro ativo; mesa encerrada permite consultar quem consta entre seus membros,
  inclusive quem abandonou ou foi removido. Quem nunca participou recebe 404.
- `MostrarMesaController` renderiza `resources/views/mesa/encerrada.blade.php`
  para mesas encerradas, com participantes, saídas e todas as partidas, seus
  placares e vencedores. O resumo apenas apresenta os dados preservados pelo domínio.
- Ações continuam usando `mesaDoMembro()` ou `mesaDoAdmin()`, exigindo mesa aberta.
  A autorização de consulta não deve ser usada para ações de escrita.
- Testes em `tests/Feature/Mesa/MesaEncerradaTest.php`.

## Perfil e medalhas

- `/perfil` (`perfil.mostrar`) mostra as medalhas da conta autenticada. `/perfil/{pessoa}`
  (`perfil.amigo`) permite ver as medalhas de uma amizade aceita, conferida por
  `Plataforma::saoAmigos()`. O nome na lista "Suas amizades" abre o perfil.
- Conta inexistente e não amigo recebem a mesma view de perfil com status 403, sem dados do
  alvo. Momento `perfil.privado`, tema de contexto `perfil_privado`; a provocação fica estável
  na sessão, sem depender do identificador consultado. A única ação do conteúdo é "Amigos".
- `Plataforma::medalhasDe()` inclui a conta e somente as participações locais reconhecidas por
  ela. A consulta fica em `RepositorioDeMedalhasNoBanco`; não processa prazos nem concede medalhas.
- `medalhas.mesa_nome` guarda `NomeDaMesa::de()` na concessão. A migração preenche registros
  anteriores com o nome atual da mesa; renomeações futuras não alteram o nome na medalha.
- O perfil usa a frase gravada e exibe datas em `America/Sao_Paulo`, sem mudar o fuso global.
  Sem medalhas: "Nenhuma medalha ainda." Medalhas sem frase continuam mostrando mesa e data.
- Cada medalha do perfil é um `<x-medalha>` (colocação, empate, jogo, logo, frase, mesa e data). O
  nome e o logo do jogo vêm de `CatalogoDeJogos::nome()` e `::logo()`; jogo novo precisa dos dois.
- **Leitura da mesa**: os nomes das pessoas vêm em lote por requisição (`PessoasDaMesa::preparar`,
  cache de `NomesDaMesaNaRequisicao`, esvaziado a cada requisição; nunca usar cache estático) e a
  concessão consulta as medalhas da mesa uma vez. `ConsultasDaMesaTest` fixa um teto de consultas;
  ao mexer na leitura da mesa, rode-o.
- Testes da área: `./dev.sh artisan test --filter='PerfilMedalhasTest|MedalhasDoPerfilTest|NomeDaMesaNaMedalhaTest|MedalhasNovasTest|ConsultasDaMesaTest'`.

## Troféu da mesa

- Só mesa encerrada concede troféu, inclusive por abandono do admin. `PremiadosDoRanking`
  usa as linhas públicas de `RankingDaMesa`: maior pontuação recebe `vitoria`; empate no topo
  dá um troféu `empate` inteiro para cada pessoa. Sem partida com vencedor, não há troféu.
- `ConcedeTrofeuDaMesa` roda pela Plataforma depois da transação, com falha isolada e nova
  tentativa na próxima leitura. `RepositorioDeTrofeusNoBanco` preserva a primeira concessão
  pela unicidade de mesa/pessoa. Nome da mesa e a data da concessão em UTC ficam gravados.
  A frase vem do Provocador (`mesa.trofeu_vitoria` ou `mesa.trofeu_empate`), já preenchida;
  troféu já concedido permanece com frase nula.
- A mesa encerrada mostra os premiados, inclusive quem saiu ou foi removido, e a frase quando
  existe. O perfil próprio e o de amigo mostram troféus da conta e de participações locais
  reconhecidas. A data mostrada é a da criação da mesa, em Brasília; sem essa data, usa a da
  concessão. A lista vai da mais recente para a mais antiga, com desempate pelo id. O 403 de
  perfil privado permanece igual.
- Testes: `--filter='PremiadosDoRankingTest|TrofeusDoPerfilTest|TrofeusDaMesaTest'`.

## Revelação da mesa

- Encerrar leva a `mesas.mostrar`. Contas que participaram, inclusive quem saiu, recebem
  resultados pendentes no Início; participantes locais não. A mesa mais recente tem a única
  ação em destaque, e todos os resultados são listados sem limite. A projeção `mesas.encerrada_em`
  preserva a ordem mesmo após reconhecer participações locais.
- `RepositorioDeRevelacoes` registra a primeira abertura por conta/mesa. A migração preenche
  mesas antigas; `rever=1` repete a cena sem alterar o registro. Sem troféu não há revelação.
- Ranking final denso e troféus permanecem no HTML. A entrada própria `mesa/revelacao.js`
  anima só as posições 3, 2 e 1, troféus juntos e frases por último, em 3,3 segundos.
  Clique, toque, Esc, Enter e Espaço pulam. Sem JS ou com movimento reduzido, estado final direto.

## Mapas do Labirinto por rodada

- Partidas novas congelam as últimas versões publicadas dos mapas ativos no clique de iniciar,
  dentro da transação da mesa. Sem mapa elegível, o início é recusado sem alterar a configuração.
- `MapasDaPartida` sorteia sem reposição até esgotar o conjunto; ao recomeçar, evita repetir o
  último mapa. Com um único mapa, ele se repete. O conjunto e o histórico dos sorteios são
  serializados com `Partida`; `PdoRepositorioDeMesas` permite essa classe na desserialização.
- `abrirRodada()` escolhe o mapa uma única vez, incluindo avanços por prazo, pulo e abandono.
  Sequencial, paralelo, local e assistir recebem a mesma versão/desenho da rodada. Reload não
  sorteia. Mudanças no catálogo só afetam partidas iniciadas depois.
- `MapasDisponiveisParaPartida` é a porta do domínio para obter o catálogo; o adaptador lê as
  versões imutáveis. Resultado continua identificado por partida/rodada/participante, cuja
  rodada identifica a versão persistida. Validação física compartilhada em `DesenhoLabirinto`;
  não mistura recordes nem prazo de expiração do solo com partidas.
- Compatibilidade: ao carregar partida antiga ainda em andamento, congela o catálogo disponível
  sem trocar o mapa padrão da rodada já aberta; sorteio entra na rodada seguinte. Sem catálogo,
  a partida antiga segue no legado até existir um conjunto; partidas novas não iniciam vazias.
- Nome e desenho vêm do servidor para jogar e assistir; publicação começa na posição inicial
  desse desenho. O contador administrativo `usos_em_rodadas` continua pendente de integração;
  não é necessário para sortear e não representa o histórico persistido na partida.

## Limites iniciais das novas partidas

Desde 2026-10-05, a configuração inicia no modo por pontos com meta real de 5 pontos; ao
selecionar o modo por rodadas, o campo recebe 5 rodadas. São editáveis e somente uma opção
fica ativa; apagar o valor ativo mantém a partida sem esse limite. Editar uma configuração
salva preserva seus valores, inclusive null. O POST antigo sem os dois campos recebe a meta
padrão de 5; campos enviados vazios permanecem sem limite. A verificação de fim continua no
domínio, ao fechar a rodada: atingir a meta OU o limite encerra a partida. Configurações de
partidas existentes não são alteradas retroativamente.

## Configuração do jogo em abas

A modal de escolha do Labirinto separa apresentação, como jogar e como funciona na aba
Labirinto; modo e prazo na aba Modo; pontos por colocação na aba Pontuação; meta de pontos
e limite de rodadas na aba Metas. Confirmar jogo fica à direita de Voltar aos jogos,
abaixo de Fechar, e envia o mesmo formulário em qualquer aba. Campos ocultos preservam
os valores; erros de campo abrem a aba correspondente. As abas têm navegação por setas,
Home e End. A lógica fica em resources/js/mesa/configuracao-do-jogo.js.

## Preparação e prazos da jogada — 2026-10-05

A vez abre com o Labirinto visível, travado, e o botão Iniciar sua vez sobre ele.
Antes do clique, a contagem Sua vez passa em mostra o prazo de início. O clique
reserva a tentativa e grava liberaEm (agora + 3 segundos) e prazoExpiraEm
(liberaEm + prazo configurado), persistidos em Tentativa. Um círculo com anel
regressivo mostra 3, 2, 1; a movimentação pela alavanca fica travada até liberar. O cronômetro
do resultado continua começando no primeiro movimento. O prazo de conclusão fica
oculto até os últimos 30 segundos, quando surge um aviso vermelho no topo. Ao
expirar, Partida::processarPrazos interrompe a tentativa e avança a disputa; no
paralelo cada tentativa tem prazo próprio e a rodada espera as demais pendentes.
Pausa continua impedindo novos inícios; tentativa já iniciada mantém seu prazo
absoluto e pode terminar. Recarregar, duplo clique e desconexão não reiniciam o
prazo. Tentativas antigas já iniciadas, sem as novas datas, mantêm o legado; novas
tentativas recebem a regra. Não há alteração dos cálculos de pontos/ranking.

A faixa superior mostra iniciais de primeiro/último nome, primeiro nome e estado
de cada participante, com rolagem horizontal acima do jogo. A faixa substitui
o quadro de ordem; placar é acordeão transparente, fechado inicialmente. A projeção TelaDoJogo
fornece datas do servidor; prazo-da-jogada.js usa referência monotônica para evitar
dependência do relógio do aparelho. Ao zerar, a tela consulta novamente o servidor.

## Organização da partida — 2026-10-05

Faixa Ordem dos jogadores (Nesta rodada no paralelo) acima do conteúdo em todas
as larguras, sem quadro duplicado de ordem. Rolagem acompanha a vez atual e
deixa os próximos ao lado. Placar é acordeão transparente como Ranking da mesa.
Na pausa, o card mostra a origem do último evento PartidaPausada: admin ou
sistema por rodada sem início de jogadas. Despausar fica nesse card somente
para o admin e chama o comando existente de PainelDoJogo, com autorização
no servidor. Não muda a regra de pausa nem os prazos.


## Aceite das regras

`ExigeAceiteDasRegras` exige o aceite vigente nas rotas web autenticadas, inclusive
contas antigas. `App\Acesso\Aceites::VERSAO_VIGENTE` identifica os três documentos;
para exigir novo aceite, criar uma pasta `resources/views/documentos/AAAA-MM-DD/` com os
três documentos e mudar essa constante no mesmo deploy. Versão publicada nunca é editada
nem removida: qualquer mudança de texto exige outra versão. `versaoVigente()` centraliza
esse valor para o gate, a validação e as páginas; nos testes, o construtor permite simular
outra versão sem alterar a constante ou os textos publicados.
Um checkbox aceita os documentos e declara 18 anos ou mais, sem verificação documental.
`aceites` guarda conta, versão, UTC e IP, com unicidade conta/versão e cascade na exclusão.
Reenvio preserva o primeiro registro. A resposta HTML volta à URL pretendida após o aceite;
JSON, broadcasting e atualizações Livewire sem aceite recebem 403 com `mensagem`.

A allowlist nominal preserva home pública, documentos, autenticação/recuperação, aceite,
logout e a página restrita `/conta/excluir`, que reutiliza a exclusão existente. Não libera
perfil/segurança inteiros. Livewire aplica o gate à rota original do snapshot verificado.
Filament mantém sua pilha/autorização; a prévia administrativa do Labirinto também é isenta.
Os textos legais nas views são cópias integrais das notas aprovadas no Obsidian. As rotas
públicas `/termos/{versao?}`, `/privacidade/{versao?}` e `/conduta/{versao?}` mostram a
versão solicitada; sem versão, a vigente. Versão inexistente retorna 404. Links da tela de
aceite e do rodapé dos documentos preservam a versão exibida.

`/settings/regras-aceitas` mostra somente os aceites da própria conta, do mais recente
para o mais antigo, com data/hora em `America/Sao_Paulo` e links para o texto aceito.
A página fica no menu das configurações, sem expor aceites no perfil de amigos. Não está
na allowlist: quem tem aceite pendente precisa aceitar antes de consultar o histórico.
A exclusão explica que aceites são apagados, acessos ficam por seis meses e o histórico
de mesas/partidas mantém o nome, com link para a Política de Privacidade.
Nos testes, a factory aceita por padrão apenas quando `runningUnitTests()`; a suíte do
aceite desliga `testes.aceite_automatico`. O estado explícito `aceitou()` está disponível.

## Registros de acesso

`registros_de_acesso` guarda id da conta sem FK, IP, porta de origem nullable e UTC.
`RegistrarEntradaNaConta` escuta o evento Login: senha, Google, link e lembrar de mim.
Cada login gera registro; durante o uso, `RegistraAcesso` limita a uma ocorrência por
conta/IP a cada hora corrida. Cache evita consultas repetidas; na falta dele, consulta
indexada e trava da conta evitam duplicação. Troca de IP gera nova ocorrência.

A exclusão da conta mantém esses registros; `acessos:limpar-registros` apaga diariamente
os anteriores a seis meses de calendário, em lotes de 1000. Sem tela, URL ou user agent.
O IP vem de `Request::ip()` e dos proxies confiáveis configurados. `REMOTE_PORT` só é usada
em conexão direta; atrás de proxy a porta fica nula, pois `X-Forwarded-Port` é de destino.
Conferência de IP pelo túnel e publicação dos itens 19–22: ver `docs/publicacao.md`, seção "Antes da publicação".

## Dashboard do Painel

- A home do Painel (`/painel`) tem dois widgets descobertos em `app/Filament/Widgets`:
  `EstatisticasGeraisWidget` (7 cards, polling de 60s) e `UltimosSeteDiasWidget` (tabela nativa).
  As contas ficam em `App\Painel\ResumoDoPainel`, só leitura: o `estado` da mesa nunca é desserializado.
- O grupo "Usuários" tem a tela `/painel/usuarios/online`, que lista contas distintas com sessão
  autenticada registrada no banco nos últimos cinco minutos; a lista atualiza a cada 30 segundos.
  `/painel/usuarios/buscar` lista todas as contas em páginas de 25, com filtro por nome ou e-mail,
  e abre uma modal de consulta somente leitura ao clicar no nome. A modal reúne dados cadastrais e
  de autenticação (sem credenciais), aceites de termos, acessos e sessões recentes, amizades,
  pedidos e bloqueios, convites de amizade e de mesa, mesas e participações locais, partidas em
  mesa, tentativas solo, medalhas e troféus. Mostra até 20 acessos, 10 sessões e cinco tentativas
  recentes por jogo; contagens de tentativas solo são totais, e os históricos de relações,
  convites, mesas e conquistas são listados integralmente. Respostas brutas dos jogos não são
  exibidas. Ambas as telas são restritas ao administrador do sistema; senhas, tokens, segredos de
  autenticação em dois fatores, IPs, portas, user agents e IPs dos aceites não são exibidos. O
  estado serializado da mesa não é aberto. Na modal, o administrador pode bloquear/desbloquear
  uma conta ou excluí-la; bloquear nega login por senha, Google e link de acesso, encerra sessões e
  troca o token de "lembrar acesso". A exclusão remove a conta, sessões, relações e dados pessoais
  com remoção em cascata; registros de acesso continuam retidos por seis meses e o histórico de
  mesas compartilhadas é preservado, incluindo nomes já gravados nas partidas. Mesas abertas
  administradas pela conta são encerradas sem vencedor; em mesas abertas de terceiros, a conta
  abandona a mesa antes da exclusão. A própria conta do administrador do Painel e as contas
  configuradas como administradores do sistema não podem ser alteradas por essas ações.
- "Hoje" e os dias do histórico são os de `America/Sao_Paulo`; os limites são calculados em PHP e
  convertidos para UTC (sem `CONVERT_TZ`). O banco continua UTC.
- **Online agora** = `users.visto_em` dentro de `AmigosOnline::JANELA_SEGUNDOS`; inclui contas
  invisíveis (a invisibilidade é social). **Mesa inativa** = aberta, sem partida em andamento
  (`partida_em_andamento = 0`; pausada não conta), sem gravação há 15 min e sem membro ativo online
  (`membros_mesa.usuario_ativo`; participante local fica de fora). Mesas de contas excluídas continuam
  abertas no banco e entram nessas contagens.
- `mesas.criada_em` (UTC, nula, indexada) é gravada só no INSERT. Mesas anteriores têm a data só se o
  `estado` trouxer uma data da própria mesa e ela não for posterior a `atualizada_em`; senão ficam
  `NULL` e não entram em "Criadas hoje" nem no histórico.
