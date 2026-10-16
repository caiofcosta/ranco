# Identidade visual

Paleta v1 e regras de uso das telas do RANÇO. Vale junto com `docs/principios-de-produto.md`.

## Direção

Sóbria, adulta, editorial e quente (variação B: grafite + areia + coral). A interface transmite
organização e qualidade; o humor vem do texto, não de cores, mascotes ou enfeites. A cor cria o
palco.

## Paleta v1

| Token | Cor | Hex | Uso |
|---|---|---|---|
| `coal` | Grafite | `#20201F` | Texto principal, áreas escuras, elementos de alto contraste |
| `cream` | Marfim | `#F7F3EC` | Fundo principal |
| `sand` | Areia | `#EAE2D5` | Fundos de apoio, avisos, voz do RANÇO |
| `coral` | Coral | `#FF684D` | Assinatura: ação principal, halo do foco, destaques, estado ativo |
| `muted` | Cinza quente | `#706A62` | Texto secundário, links discretos, placeholders |
| `danger` | Vermelho queimado | `#BD392B` | Só erro real: validação, senha errada, pausa, falha, ação destrutiva |
| `white` | Branco | `#FFFFFF` | Cards e superfícies elevadas |

Bordas não têm cor própria: grafite transparente, `coal/6` (sutil), `coal/10` (padrão) e `coal/20`
(destaque).

Os tokens ficam no `@theme` de `resources/css/app.css` (classes como `bg-coal`, `text-muted`,
`border-coral`). A cor de destaque do Flux (`--color-accent`) aponta para o coral, com texto grafite.

## Regras

- A base é `cream` + `white` + `coal`. O coral entra como assinatura, o `sand` dá profundidade e o
  vermelho só aparece quando existe um erro. Se tudo estiver destacado, nada está destacado.
- **Botão principal:** fundo coral com texto grafite. Texto branco sobre coral não tem contraste.
- **Coral nunca é texto sobre fundo claro.** Sobre fundo claro ele aparece em barras, sublinhados,
  bordas e foco; como texto, só sobre grafite.
- **Coral é marca; vermelho é erro.** Os dois significados não se misturam.
- **A provocação não parece erro.** O erro aparece em vermelho; a frase do RANÇO vem logo abaixo, na
  caixa de voz, com o visual normal.
- **`muted` não vai sobre `sand` em texto pequeno.** Na caixa de voz, o rótulo usa grafite a 70%.
- **Erro (`danger`) sobre branco ou marfim**, não sobre `sand`.
- **Foco:** campo com foco ganha borda grafite e halo coral; botões e links, contorno grafite. Coral
  sozinho não serve de indicador de foco (2,9:1 sobre branco, abaixo dos 3:1 pedidos).
- Evitar preto puro. Nada de verde, azul ou roxo na v1: cor funcional nova só com motivo.

## Contraste

Mínimo WCAG AA: 4,5:1 para texto comum; 3:1 para texto grande e elementos de interface.

| Texto | Fundo | Contraste | Situação |
|---|---|---|---|
| coal | white | 16,3:1 | texto nos cards |
| coal | cream | 14,7:1 | texto principal |
| coal | sand | 12,7:1 | mensagem da voz |
| cream | coal | 14,7:1 | títulos no painel escuro |
| cream a 60% | coal | 6,1:1 | texto auxiliar no painel escuro |
| coal | coral | 5,7:1 | texto do botão principal |
| coral | coal | 5,7:1 | destaque no painel escuro |
| coal a 70% | caixa de voz (`sand/65` sobre branco) | 5,6:1 | rótulo da voz |
| danger | white | 5,5:1 | erros |
| muted | white | 5,3:1 | texto secundário nos cards |
| muted | cream | 4,8:1 | texto secundário no fundo |
| danger | sand | 4,3:1 | evitar |
| muted | sand | 4,2:1 | evitar em texto pequeno |
| white | coral | 2,9:1 | não usar |
| coral | white / cream | 2,9:1 / 2,6:1 | não usar como texto |

## Componentes

Em `resources/views/components`:

- `voz-do-ranco`: caixa areia com barra coral, rótulo "comentário completamente necessário" e a
  frase. Só mostra frases do `Provocador`, nunca texto fixo. Entra com um movimento curto, desligado
  com `prefers-reduced-motion`.
- `retorno`: retorno de formulário. Primeiro o fato (erro em vermelho, `role="alert"`), depois a voz
  do RANÇO, se houver provocação.
- `aviso`: informação neutra do sistema (ex.: "Link enviado…"). Não é erro nem voz.
- `campo`: rótulo, entrada e erro do campo; Ver/Ocultar nas senhas; foco com borda grafite e halo
  coral.
- `botao`: ação principal, uma por tela.
- `link`: destacado (grafite com sublinhado coral) ou discreto (`muted`).
- `botao-google`, `separador` e `cabecalho-de-acesso`.
- `tom` e `inicio-cabecalho`: no topo do Início, o Tom, a saudação ("E aí, {primeiro nome}!") e a
  frase dele. Ver a seção Tom.
- `inicio-convites`: cards lado a lado (uma coluna no celular) com as iniciais de quem convidou, o
  nome da mesa e quantas pessoas já estão nela. Aceitar em coral só sem mesa atual; com mesa, em
  grafite, porque aceitar exige sair dela. Recusar é o botão claro.
- `inicio-mesas`: seção "Suas mesas", com o contador "N de 5", em linhas: as mesas abertas (até 5)
  e as encerradas com resultado não visto. No limite, some "+ Criar mesa" e aparece o aviso de que é
  preciso sair de uma mesa; os convites mostram "Saia de uma para aceitar". Cada linha tem nome, pessoas e quem criou, a situação numa pílula (Sua vez em coral claro,
  Em jogo em grafite, Pausada em vermelho sobre branco, as demais em areia) e uma ação. Coral:
  jogar a vez; sem ela, o resultado mais recente; sem resultado, abrir a mesa. Sem mesa, "+ Criar
  mesa" fica na linha do título (coral; branco quando há convite ou resultado pendente; "Ficar
  visível" para quem está invisível). Sem nada, "Nenhuma mesa ainda" com os três passos (criar,
  convidar amigos, jogar e disputar o troféu), pedido do responsável para quem chega pela primeira vez.
- Layout do Início: o cabeçalho ocupa a largura toda; abaixo, duas colunas a partir de `xl` (no
  celular e em telas médias, empilhadas): à esquerda `inicio-convites` e `inicio-mesas`; à direita
  `inicio-amizades`.
- `inicio-amizades`: seção "Amizades" com o link discreto "Convidar amigos" (vai para `/amigos`),
  os convites de amizade recebidos (Aceitar em grafite, Recusar claro; invisível vê "Fique visível
  para aceitar.") e os amigos, online primeiro, com bolinha coral, "visto há…" e o nome levando ao
  perfil. Sem amigos nem convites, a frase vem do `Provocador` (momento `amizade.vazia`) na
  `voz-do-ranco` e, abaixo, como fazer amigos (convite por e-mail; amigo pode ser chamado para a
  mesa) com o botão "Convidar amigos".
- `mesa-de-jogo`: tampo oval com 8 cadeiras no topo da tela da mesa (`CadeirasDaMesa`). Quem olha
  senta embaixo; a presença acende a cadeira; para o admin, a vaga leva a "Convidar amigos".

Telas de acesso: layout `resources/views/layouts/auth/ranco.blade.php`, com o painel grafite da marca
e o cartão branco do formulário. No celular, o painel vira um cabeçalho curto. Só tema claro.

## Tom

O boneco que fala pelo sistema. SVG no próprio HTML: silhueta preta, olhos brancos e gola coral
(`#FF684D`), sem fundo. No Início autenticado (`dashboard`), fica no topo com a frase ao lado e
substitui o rótulo da caixa de voz. A figura é decorativa para leitores de tela (`aria-hidden`); só a
frase é lida. Regras de conteúdo na nota "RANÇO - Tom" do Obsidian.

Componentes:

- `tom`: coloca o SVG direto no HTML. Aceita `pose` (`base` por padrão) e `expressao`. Só valem os
  arquivos listados abaixo; qualquer outro valor cai na pose `base`. No Início, o celular mostra a
  cabeça e a tela larga o corpo inteiro.
- `inicio-cabecalho`: figura à esquerda, saudação e a frase abaixo dela, na caixa areia (a frase tem
  a mesma entrada curta de `voz-do-ranco`, desligada com `prefers-reduced-motion`). Sem o rótulo
  "comentário completamente necessário". Urgente usa pose `observando` e expressão `atento`; comum
  usa `base` e `neutro`. Se o `Provocador` não devolver frase, o Tom e a frase não entram; a
  saudação continua.

A frase vem de `App\Tom\FalaDoTom`, nos momentos `inicio.*`.

No e-mail de convite de mesa o Tom aparece como PNG (`public/images/tom-email.png`, gerado da expressão `neutro`); o SVG do Início
não entra no HTML de e-mail. Falas: momento `email.convite`. Detalhes em
`docs/email-convite-de-mesa.md`.

Arquivos em `resources/svg/tom/`, vetorizados da folha de exploração do personagem (variação A):

- `poses/`: corpo inteiro, todas no mesmo quadro e com os pés na mesma linha, para trocar de pose
  sem o layout pular: `base`, `pensando`, `observando`, `julgando`, `de-boa`, `indo-embora`. A sombra
  no chão tem a classe `sombra`, para poder ser escondida por CSS.
- `expressoes/`: só a cabeça, para usos pequenos: `neutro`, `desconfiado`, `curioso`, `cansado`,
  `sarcastico`, `atento`.

## Tipografia

Provisória: **Inter** (pesos 400 a 900), pelo Bunny Fonts, em `vite.config.js`. A tipografia
definitiva ainda será documentada.

## Pendente

- Tipografia definitiva.
- Visual das telas da mesa, da partida e de configurações.
- Modo escuro: as telas de acesso usam só o tema claro.

## Labirinto solo

Views em `resources/views/jogos/labirinto/`, no layout do sistema. O item "Jogar" abre o jogo.
Tabuleiro quadrado e responsivo: paredes grafite, caminhos areia, bolinha coral com contorno
grafite e chegada marcada por ✓ em marfim sobre grafite. A forma distingue a chegada sem
depender de cor. O único controle de movimento é a alavanca abaixo do tabuleiro, arrastada
com dedo ou mouse. Uma instrução curta explica direção, velocidade proporcional ao gesto
e parada ao soltar, tanto no computador quanto no celular.

A alavanca tem base circular areia, encaixe grafite, haste metálica curta com pivô fixo e
manopla esférica coral com acabamento vitrificado. O gesto tem alcance de 48 px, mas sua
projeção visual é menor para sugerir inclinação, sem esticar a haste. A área de toque tem
148 × 148 px e captura o ponteiro até soltar, inclusive fora do círculo. O painel de quem
assiste não mostra alavanca. Comportamento e pontos de manutenção em `docs/labirinto-controles.md`.

Cronômetro com algarismos tabulares; retorno do envio anunciado por `role="status"`. Na falha,
o tempo permanece na tela e há uma ação para reenviar. No resultado, tempo da partida e melhor
tempo aparecem juntos; a voz usa `voz-do-ranco`. A única ação em coral é "Criar mesa" ou
"Abrir mesa"; "Jogar de novo" é um link secundário. Não há novo componente Blade compartilhado.
