# E-mail de convite para mesa

Template HTML de e-mail real (tabelas, CSS inline, sem JavaScript e sem fontes externas).

## Onde fica

| Peça | Caminho |
|---|---|
| HTML | `resources/views/emails/convite-de-mesa.blade.php` |
| Texto puro | `resources/views/emails/convite-de-mesa-texto.blade.php` |
| Notificação | `App\Notifications\ConviteDeMesa` |
| Envio | `App\Convite\ConvidarParaMesa` |
| Link assinado | `App\Convite\LinkDoConvite` |
| Aceite | `GET/POST convites/{convite}/entrar` (`convites.entrar` / `convites.aceitar`) |
| Tom (PNG) | `public/images/tom-email.png` |
| Falas do Tom | momento `email.convite`, tema `convite_email` |

A tela de convite da mesa precisa chamar `ConvidarParaMesa`. `Plataforma::convidar` sozinho grava o convite e não manda e-mail.

## Dados do template

| Variável | Origem |
|---|---|
| `nomeConvidante` | `users.name` de quem convidou |
| `nomeMesa` | Nome gravado ou `Mesa de {admin}` |
| `urlConvite` | URL assinada de `convites.entrar` |
| `mensagemTom` | `Provocador` no momento `email.convite` (`{admin}` = nome de quem convidou) |
| `emailDestinatario` | E-mail da conta convidada |
| `preheader` | Frase fixa: `Parece que a paz acabou.` |
| `urlTom` | `url('images/tom-email.png')` |

Assunto: `{nomeConvidante} te chamou para “{nomeMesa}”`.

## Regras que valem hoje (não foram inventadas)

- O convite de mesa continua exigindo **amizade aceita** e uma **conta existente**. `convites.para` é o identificador da conta, não um e-mail solto.
- O convite **não expira sozinho**. Só deixa de valer se for aceito, recusado, cancelado ou se a mesa encerrar.
- O id do convite já é 16 caracteres hex aleatórios. A URL do e-mail é **assinada** com a chave da aplicação; não leva ids internos além desse token.
- Aceitar é **POST**. Abrir o link só mostra a tela, para o cliente de e-mail não entrar na mesa sozinho.
- Quem abre o link sem sessão vai para login (ou cadastro / Google / link por e-mail). A URL fica em `url.intended` e, depois da autenticação, a pessoa volta ao convite. A conta autenticada precisa ser a de `convites.para`.
- O texto “Ainda não tem conta?” cobre quem usa o cadastro com o **mesmo e-mail da conta convidada**: o cadastro manda o link de acesso e, ao entrar, volta ao convite. **Não existe** convite de mesa para um e-mail sem conta e sem amizade. Isso seria outra regra (como o convite de amizade) e não foi aberta aqui.

## Visual

Paleta v1. Largura máxima 660px. Logo textual **RANÇO.** com o ponto coral. Botão coral com texto grafite. O Tom no e-mail é PNG estático (clientes de e-mail não renderizam o SVG do Início): a cabeça `neutro` de `resources/svg/tom/expressoes/neutro.svg`, gerada em 168×168 px (três vezes os 56 px exibidos, para telas de alta densidade). Para trocar, gere o PNG a partir do SVG; não desenhe outro boneco.
