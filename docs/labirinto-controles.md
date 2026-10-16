# Controle do Labirinto por alavanca

O Labirinto usa exclusivamente a alavanca abaixo do tabuleiro para movimentar a bolinha.
O controle vale para o solo, a prévia administrativa e as partidas sequenciais e paralelas.
Setas, WASD e arrastar diretamente no tabuleiro não movimentam a bolinha.
A navegação por teclado dos menus, botões e abas continua independente do jogo.

## Como jogar

- No celular, toque e arraste a alavanca com o dedo; no computador, clique, segure e arraste com o mouse.
- Arraste na direção desejada, inclusive nas diagonais. Quanto mais afastar do centro, maior a velocidade.
- Para movimentos precisos, mantenha o gesto próximo ao centro. Solte para parar imediatamente.
- Leve a bolinha à chegada marcada por ✓. O cronômetro começa no primeiro movimento e para na chegada.
- Nas partidas, a bolinha permanece bloqueada antes do início, durante a preparação e após vencer o prazo.

## Implementação

- `resources/js/jogos/labirinto/joystick.js`: controle DOM compartilhado, captura de um ponteiro por vez,
  desenho da inclinação e limpeza de eventos. Soltar, cancelar o gesto, perder a captura,
  sair da janela ou mudar a visibilidade da página zera a direção e centraliza a manopla.
- `controles.js`: zona morta de 6 px, alcance do gesto de 48 px e intensidade proporcional,
  limitada a 1. As diagonais não aumentam a velocidade máxima.
- `cena.js`: consome a direção da alavanca usando as colisões e os bloqueios existentes.
  A alavanca é removida ao reiniciar ou destruir a cena e não aparece na transmissão de quem assiste.
- `resources/css/labirinto.css`: base e manopla vitrificada; projeção visual reduzida da haste,
  independente da sensibilidade do gesto. A alavanca fica após o tabuleiro também no celular.
- `resources/views/components/como-jogar-labirinto.blade.php`: explicação na configuração da mesa.
- `resources/views/components/instrucoes-labirinto.blade.php`: orientação compartilhada junto ao jogo.

## Verificação e publicação

Execute `npm run test:js`, os testes PHP do Labirinto e das telas de partida no ambiente de
desenvolvimento e `npm run build`. No navegador, confira mouse e toque, diagonais, colisões,
parada ao soltar/cancelar, reinício sem controle duplicado, ausência de movimento por teclado
e durante a preparação. Verifique o texto da configuração e os modos solo, partida e assistir.

A mudança não exige migração ou alteração de pontuação. A publicação precisa incluir os
arquivos JS/CSS, os componentes Blade e um novo build dos assets. Siga `docs/publicacao.md`
para o ambiente de produção; a demonstração local não confirma publicação.
