@php
    $documento = <<<'DOCUMENTO_LEGAL'
# Termos de Uso do RANÇO

Versão de 5 de outubro de 2026.

## 1. Quem mantém o RANÇO

O RANÇO (https://seu-dominio.exemplo) é mantido por [NOME DO RESPONSÁVEL],
[CNPJ/CPF], [Inscrição, se houver]. Contato: contato@seu-dominio.exemplo.
(Preencha estes dados antes de publicar o serviço.)

## 2. O que é o RANÇO

Uma plataforma gratuita de minijogos para grupos, com humor de provocação entre os jogadores.
O RANÇO está em fase inicial: funções podem mudar, ficar indisponíveis ou deixar de existir.

## 3. Aceite

Para usar o RANÇO, você precisa aceitar estes Termos, a Política de Privacidade e as Regras de
Conduta. Se não concordar, não use o serviço. Quando estes documentos mudarem, pediremos um
novo aceite no seu próximo acesso.

## 4. Idade mínima

O RANÇO é só para maiores de 18 anos. Ao aceitar, você declara ter 18 anos ou mais. Se
soubermos que uma conta pertence a menor de 18 anos, ela será encerrada e excluída, conforme
a Política de Privacidade.

## 5. Sua conta

- A conta é pessoal e intransferível.
- Use um nome que represente você e um e-mail que seja seu.
- Você é responsável por proteger o acesso: sua senha, sua caixa de e-mail e, se usar,
  sua conta Google.
- Se perceber uso indevido da sua conta, avise pelo e-mail de contato.

## 6. Uso permitido

Você pode usar o RANÇO para jogar e se divertir com outras pessoas, seguindo as Regras de
Conduta. Não é permitido:

- usar programas, scripts ou automações para jogar ou alterar resultados e tempos;
- explorar falhas do sistema em vez de reportá-las;
- tentar acessar contas, mesas ou dados de outras pessoas;
- sobrecarregar ou atrapalhar o funcionamento do serviço;
- usar o RANÇO para fins comerciais, propaganda ou envio de mensagens em massa.

## 7. O que você escreve

Nomes de mesa, nome de exibição e nomes de participantes locais aparecem para outros
jogadores. Você é responsável pelo que escreve. Podemos remover ou alterar conteúdo que
viole as Regras de Conduta.

## 8. Participantes locais

Ao cadastrar uma pessoa sem conta numa mesa, com nome e e-mail, você declara que ela sabe
disso, concorda e tem 18 anos ou mais.

## 9. Provocações

As mensagens de humor do RANÇO são automáticas e brincam com a situação do jogo, nunca com
quem você é. Se alguma mensagem incomodar, escreva para o e-mail de contato.

## 10. Resultados, medalhas e troféus

Pontos, medalhas, troféus e recordes são registros do jogo. Não têm valor em dinheiro e não
podem ser transferidos ou trocados. Resultados obtidos por falha do sistema ou trapaça podem
ser corrigidos ou anulados.

## 11. Gratuidade

O RANÇO não cobra pelo uso e não oferece prêmios em dinheiro.

## 12. Suspensão e encerramento

Você pode excluir sua conta a qualquer momento, nas configurações. Podemos suspender ou
encerrar contas que violem estes Termos ou as Regras de Conduta. O que acontece com os dados
está na Política de Privacidade.

## 13. Propriedade intelectual

A marca RANÇO, o personagem Tom, os jogos, os textos e o visual do serviço pertencem ao
mantenedor. Não copie nem reutilize esse material sem autorização.

## 14. Responsabilidade

O RANÇO é oferecido como está, sem garantia de funcionamento contínuo ou livre de erros. Não
respondemos pelo conteúdo escrito por outros jogadores, salvo nos casos previstos em lei.
Nada nestes Termos limita direitos garantidos pelo Código de Defesa do Consumidor.

## 15. Lei aplicável e foro

Estes Termos seguem a legislação brasileira. Fica eleito o foro do domicílio do usuário.
DOCUMENTO_LEGAL;
@endphp
<x-layouts::documento :versao="$versao" title="Termos de Uso">
    {!! \Illuminate\Support\Str::markdown($documento, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
</x-layouts::documento>
