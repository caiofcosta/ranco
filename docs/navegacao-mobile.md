# Navegação mobile

Abaixo do breakpoint `lg` (64 rem / 1024 px), o painel autenticado usa uma barra fixa
com cinco destinos: Início (`/inicio`), Agora (`/agora`), Jogando (`/jogando`), Mesas (`/mesas`) e Mais (`/mais`).
O acesso inicial pelo `/dashboard` mostra Início no mobile. Desktop mantém a navegação lateral
e o conteúdo anterior da home. Não há detecção por user agent nem alteração das regras de negócio.

## Conteúdo e regras preservadas

- Início concentra o Tom compacto, três linhas de resumo (Agora, Jogando e Mesas), acesso ao
  catálogo solo e no máximo um destaque. Não replica cards completos nem lista amizades.
- Agora reúne turnos jogáveis (inclusive de participantes locais sob responsabilidade do administrador),
  configuração/início de partidas administradas e convites de mesa recebidos. Cada ação conta uma vez.
  Não inclui resultados apenas para consulta. Sem ações, mostra um estado vazio.
- Jogando destaca a tentativa em curso, quando existe. Outras participações continuam acessíveis;
  sem tentativa, mostra as participações existentes. O ponto da barra indica contexto, não alerta.
- Mesas mostra somente mesas abertas administradas pela pessoa. O contador desta área é o número
  de mesas administradas abertas sobre o limite atual. A possibilidade de criar respeita a regra
  global e explica quando participações por convite ocupam todas as vagas.
- Mais reúne Amigos, Medalhas e troféus, Histórico, Perfil e conta, Configurações (Segurança),
  e Sair. Jogar sozinho fica diretamente no Início. O status online dos amigos está acessível também na tela Amigos mobile.

**Esclarecimento aprovado pelo usuário:** o código permite até cinco mesas abertas por pessoa,
incluindo mesas aceitas por convite. A exclusividade vale para uma tentativa em andamento,
não para ser membro de uma única mesa. Esta mudança preserva essas regras e adapta os textos.

## Implementação

`ContextoMobile` projeta os dados existentes, sem criar estados de negócio.
Os componentes Livewire `Navegacao/AreaMobile` e `Navegacao/BarraMobile` atualizam o conteúdo
e o badge a cada 15 segundos quando visíveis, sem redirecionar. O desktop não exibe esses componentes.
As rotas usam o controller do início; o conteúdo mobile é próprio de cada URL, com histórico
via `wire:navigate`. Livewire restaura o scroll ao voltar; `navegacao-mobile.js` guarda a posição
entre trocas pela barra durante a sessão da página.

A barra tem labels permanentes, ícones Flux/Heroicons de traço consistente, alvos de pelo menos
56 px de altura e estado ativo com fundo coral discreto. Somente Agora tem contador; Jogando
pode ter um ponto; Mesas e Mais não têm badges. Safe area usa `env(safe-area-inset-bottom)`
e `viewport-fit=cover`. O conteúdo reserva espaço inferior para não ficar coberto.

## Verificação

- Testes PHP: `NavegacaoMobileTest`, regressões do início e amigos.
- Navegador: 360, 375, 390, 412 e 430 px; cinco áreas, conteúdo longo, targets e ausência de overflow.
- Histórico: Mesas → mesa → voltar; refresh em `/mesas`; scroll entre áreas.
- Desktop: comparar layout antes/depois em 1024, 1280 e 1440 px; barra ausente e lateral preservada.
- Build: `npm run build`; testes JavaScript: `npm run test:js`.

## Destaque e personalidade da Home

`DestaqueDaHome::principal()` é a fonte do bloco de destaque. Por enquanto lê
`config/home-mobile.php`, com ativo, rótulo, título, texto, ação e nome de rota interna.
Retornar null remove o bloco inteiro. A view não conhece a origem dos dados; uma futura
consulta a um repositório gerido pelo Filament pode substituir essa fonte. Não foi criada
gestão no painel nem entidade persistida nesta tarefa. O destaque inicial é a alavanca do Labirinto.

A fala do Tom usa `FalaDoTom`, escolhida ao montar o Início e mantida durante o polling.
A versão mobile limita o texto a 100 caracteres e usa o SVG existente em 56 px. O Tom
não aparece nas outras quatro áreas operacionais. O desktop mantém sua apresentação anterior.

## Breadcrumbs

Os layouts de painel, acesso e documentos incluem `mobile-breadcrumb` acima do conteúdo.
`CaminhoMobile` centraliza a hierarquia das rotas: jogos solo retornam ao catálogo e ao
Labirinto; partidas retornam à mesa; conta, amigos, histórico e medalhas retornam a Mais.
Os destinos principais retornam a Início. O próprio Início não tem ancestral e não exibe
um caminho redundante. Links são GET reais, com alvo de 44 px; a página atual não é link.
A lista pode quebrar entre itens em telas estreitas. O desktop não mostra o componente.
Filament mantém sua navegação nativa; modais mantêm os controles internos existentes.
