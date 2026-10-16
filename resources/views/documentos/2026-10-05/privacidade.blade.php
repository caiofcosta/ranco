@php
    $documento = <<<'DOCUMENTO_LEGAL'
# Política de Privacidade do RANÇO

Versão de 5 de outubro de 2026.

## 1. Quem trata seus dados

O controlador dos dados é [NOME DO RESPONSÁVEL], [CNPJ/CPF],
responsável pelo RANÇO (https://seu-dominio.exemplo). Para qualquer assunto de
privacidade: contato@seu-dominio.exemplo.
(Preencha estes dados antes de publicar o serviço.)

## 2. Quais dados tratamos

**Da sua conta**
- Nome, e-mail e data de confirmação do e-mail.
- Senha, guardada apenas como hash, um código que não permite recuperar a senha original.
  Contas que entram pelo Google ou por link de acesso podem não ter senha.

**Se você entrar com o Google**
- Identificador da conta Google, nome, e-mail e endereço da foto de perfil. Nunca recebemos
  sua senha do Google.

**Do uso do jogo**
- Mesas, partidas, jogadas, tempos, pontuação, medalhas, troféus e recordes do jogo solo.
- Amizades, bloqueios, convites de mesa e convites de amizade.
- Sua escolha de ficar invisível e o momento da sua última atividade ("visto").

**Convites de amizade**
- Para convidar alguém, você informa um e-mail. Só criamos o convite se existir uma conta com
  esse e-mail. Se não existir, o e-mail não é guardado e nenhuma mensagem é enviada.

**Participantes locais**
- Nome e e-mail de quem joga numa mesa sem ter conta, informados pelo admin da mesa. O
  e-mail serve para que essa pessoa, se criar uma conta com ele, possa reconhecer ou rejeitar
  as participações. Não enviamos mensagens para participantes locais.

**Dados técnicos**
- Endereço IP, navegador e horário da última atividade da sessão.
- Endereço IP, data e versão do seu aceite destes documentos.
- Registros de acesso: endereço IP, data e hora de cada acesso à sua conta.

## 3. Para que usamos e com que base legal

| Finalidade | Base legal (LGPD) |
|---|---|
| Criar e manter sua conta, permitir o acesso e o jogo, mostrar resultados aos participantes | Execução de contrato (art. 7º, V) |
| Enviar e-mails do serviço: confirmação de e-mail, link de acesso e convite de mesa | Execução de contrato (art. 7º, V) |
| Segurança, prevenção de fraude, de trapaça e de abuso | Legítimo interesse (art. 7º, IX) |
| Registrar participações locais para que a pessoa possa reconhecê-las | Legítimo interesse (art. 7º, IX) |
| Guardar o registro do seu aceite | Exercício regular de direitos (art. 7º, VI) |
| Guardar registros de acesso por 6 meses | Legítimo interesse, para segurança e defesa de direitos, e cumprimento de obrigação legal quando aplicável (art. 7º, II e IX), conforme o art. 15 do Marco Civil da Internet (Lei 12.965/2014) |

Não vendemos seus dados, não os usamos para publicidade e não montamos perfis de marketing.

## 4. Quem vê seus dados

**Outros jogadores** veem seu nome, sua foto do Google (quando houver) e seus resultados,
medalhas e troféus nas mesas de que você participa. Amigos veem seu perfil com medalhas e
troféus e se você está online, a menos que você esteja invisível. Seu e-mail não é mostrado
a outros jogadores.

**Prestadores de serviço** que tratam dados em nosso nome, apenas no necessário:
- Google: login com conta Google.
- Cloudflare: rede e proteção do acesso ao site (recebe o IP e os dados de navegação).
- Provedor de e-mail: envio de e-mails transacionais.

**Autoridades**, quando houver ordem judicial ou obrigação legal.

## 5. Transferência internacional

Google e Cloudflare podem tratar dados fora do Brasil. Essas transferências seguem o art. 33
da LGPD.

## 6. Por quanto tempo guardamos

- Conta, dados de jogo e registro de aceite: enquanto a conta existir.
- Sessões: apagadas periodicamente depois que expiram por inatividade.
- Registros de acesso (IP, data e hora): 6 meses, inclusive depois da exclusão da conta,
  conforme o Marco Civil da Internet. Depois disso, são apagados.
- Ao excluir sua conta, apagamos seus dados de conta e seus registros de aceite, exceto os
  registros de acesso, que seguem o prazo acima. Mesas e partidas de que você participou
  continuam no histórico dos outros participantes, inclusive frases já geradas que podem
  conter seu nome de exibição.

## 7. Cookies

Usamos apenas cookies necessários ao funcionamento: manter a sessão, proteger formulários
contra falsificação e, se você escolher, manter o acesso lembrado. Não usamos cookies de
estatística ou de publicidade.

## 8. Segurança

Senhas são guardadas apenas como hash, o acesso ao site é feito por HTTPS e o acesso aos
dados é restrito ao mantenedor. Nenhum sistema é totalmente seguro. Se houver incidente que traga
risco relevante, você e a ANPD serão avisados, conforme a lei.

## 9. Seus direitos

Pela LGPD (art. 18), você pode pedir: confirmação de que tratamos seus dados, acesso,
correção, anonimização, bloqueio ou eliminação de dados desnecessários, portabilidade,
informação sobre com quem compartilhamos e revisão destas condições.

- Para excluir a conta, use as configurações da conta.
- Para os demais pedidos, escreva para contato@seu-dominio.exemplo. Respondemos em até 15 dias.
- Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).

## 10. Menores de idade

O RANÇO não é destinado a menores de 18 anos. Se identificarmos uma conta de menor, ela será
encerrada e excluída, como descrito na seção 6.

## 11. Mudanças nesta Política

Quando esta Política mudar, publicaremos a nova versão e pediremos um novo aceite no seu
próximo acesso.
DOCUMENTO_LEGAL;
@endphp
<x-layouts::documento :versao="$versao" title="Política de Privacidade">
    {!! \Illuminate\Support\Str::markdown($documento, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
</x-layouts::documento>
