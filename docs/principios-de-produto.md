# Princípios de produto

Regras que valem para toda tela e toda mensagem do RANÇO. Toda tela nova e todo texto novo
passam por elas. Cores, componentes e contraste: `docs/identidade-visual.md`.

## Princípios

1. **A interface nunca explica o óbvio.** Botões, hierarquia, estados e próximos passos precisam ser
   entendidos visualmente.
2. **Instruções aparecem quando a regra do jogo exige**, não para ensinar onde clicar.
3. **Humor não interrompe o fluxo.** Ele entra em erro, vitória, derrota, espera, convite, pausa,
   histórico, rivalidade e mensagens do sistema.
4. **Uma tela tem uma ação principal clara.** Nada de cinco botões disputando atenção.
5. **O visual transmite qualidade; o texto transmite personalidade.**
6. **Humor nunca substitui a informação.** Toda provocação acompanha o fato e o próximo passo.
7. **O deboche mira a situação, nunca a pessoa.** Nada sobre aparência, origem, religião, gênero ou
   qualquer característica pessoal.
8. **Toda tela deve funcionar perfeitamente mesmo se removermos todas as frases engraçadas.** Se sem
   humor a tela ainda é clara, bonita e utilizável, a UX está boa; o RANÇO entra por cima.

Hierarquia de quem usa:

- primeiro: "entendi onde estou e o que tenho que fazer";
- depois: "olha a besteira que esse sistema falou";
- nunca: "não sei o que fazer porque estão tentando ser engraçados".

## Na implementação

- **Mensagens de acontecimento vêm do `Provocador`.** Erro, pausa, vitória, convite e qualquer outro
  acontecimento nunca têm piada fixa no código: a frase é sorteada a partir de um momento e vem da
  tabela `provocacoes`, alimentada pelo Painel (`/painel/frases`, `/painel/temas` e
  `/painel/momentos`). O Painel não cria nem apaga momentos.
- **Textos de marca podem ser fixos.** Títulos, chamadas e rótulos das telas (ex.: "Quem é você
  mesmo?", "Entre. Jogue. Arrume problema.") fazem parte da identidade e ficam nas views. A caixa
  "voz do RANÇO" só mostra frases do `Provocador`.
- **Fato e piada separados.** A informação (erro, resultado, próximo passo) e a provocação são dois
  textos diferentes. A tela mostra os dois; a provocação nunca aparece sozinha.
- **O Tom nunca é o único lugar de uma informação.** O boneco que fala pelo sistema comenta o que a
  tela já mostra (o botão de jogar, o convite); sem ele, a tela continua completa.
- **Provocação não bloqueia.** Nada de janela modal para humor; ela aparece junto do conteúdo, sem
  atrasar nem exigir clique.
- **Acessibilidade.** Botões sem texto visível têm rótulo (`aria-label`).
- **Vínculos de copy do Provocador.** A migração `database/migrations/2026_10_08_000001_aplicar_copy_aprovada_do_tom.php`
  localiza cada frase pelo texto exato de origem (`BINARY`) e ignora a que a curadoria já alterou. Seu `down()` é vazio:
  é só para frente.
  - "A senha está fazendo jogo duro." tem só o tema `acesso` (login).
  - "O plano era outro. A próxima jogada está em aberto." tem o tema `interrupcao`, no momento `partida.tentativa_interrompida`.
  - "Prata. Foi por pouco — e isso rende revanche." tem só o tema `segundo_lugar`, no momento `partida.segundo_lugar`.
  - "Intervalo técnico. Daqui a pouco tem revanche." mantém os temas que já tinha.

## Onde a regra do jogo exige aviso

Só estes pontos justificam um texto explicativo:

- **Iniciar sua vez**: o clique reserva a tentativa; o tabuleiro libera após 3, 2, 1, e o cronômetro do resultado começa no primeiro movimento.
  Há um prazo para iniciar e outro prazo completo para concluir após a preparação. Sair ou perder a conexão não reinicia os prazos.
- **Abandonar a mesa**: é definitivo; para voltar, é preciso novo convite.
- **Admin abandonar a mesa**: encerra a mesa e a partida, sem vencedor.
- **Admin encerrar a partida**: termina sem vencedor.
- **Reconhecer uma participação local**: o resultado entra no histórico oficial da conta.

## Ação principal por tela

| Tela | Ação principal |
|---|---|
| Início, sem mesa | Criar mesa (ou aceitar o convite, se houver) |
| Mesa, partida em configuração | Admin: iniciar partida. Demais: aguardar |
| Partida, na sua vez | Iniciar sua vez |
| Partida, fora da sua vez | Acompanhar a jogada ao vivo no sequencial; resultados e pendentes no paralelo |
| Fim da partida | Voltar à mesa; retorno automático após 3, 2, 1 |

A próxima partida é criada na mesa. No celular, o tabuleiro precede título e status;
a instrução acompanha o tipo de ponteiro. A transmissão é somente leitura.

## Checklist de revisão de tela

- [ ] Dá para entender o que fazer sem ler nenhuma instrução?
- [ ] Existe uma única ação principal em destaque?
- [ ] Algum texto explica algo que o visual já mostra? Se sim, sai.
- [ ] O humor aparece sem bloquear nada, e o fato continua visível?
- [ ] Alguma frase mira a pessoa em vez da situação?
- [ ] Sem as frases engraçadas, a tela continua clara e utilizável?
