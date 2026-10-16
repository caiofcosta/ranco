@php
    $documento = <<<'DOCUMENTO_LEGAL'
# Regras de Conduta do RANÇO

Versão de 5 de outubro de 2026.

O RANÇO foi feito para provocar, com graça. A regra principal: **o deboche mira a situação,
nunca a pessoa.**

## Pode

- Zoar a jogada, a derrota, o tempo, o azar e a revanche.
- Comemorar a vitória sem dó.
- Provocar quem também está ali para provocar.

## Não pode

- Ofender ou humilhar alguém pelo que a pessoa é: aparência, corpo, origem, raça, cor, etnia,
  religião, gênero, orientação sexual, deficiência, idade ou condição social. Discriminação
  e injúria são crimes no Brasil.
- Ameaçar, assediar ou perseguir alguém, nem insistir com quem pediu para parar ou bloqueou você.
- Usar nomes de mesa, de exibição ou de participante local com conteúdo sexual, violento,
  ilegal ou ofensivo.
- Expor dados de outras pessoas, como telefone, endereço ou documentos.
- Se passar por outra pessoa.
- Trapacear: usar programas ou automações, manipular tempos ou explorar falhas. Achou uma
  falha? Avise, não use.
- Cadastrar alguém como participante local sem que a pessoa saiba e concorde.
- Enviar convites em massa, propaganda ou spam.

## Se algo incomodar

- Bloqueie a pessoa na tela de amigos.
- Saia da mesa.
- Escreva para contato@seu-dominio.exemplo contando o que aconteceu.

## Consequências

Quem descumprir estas regras pode ter conteúdo removido, resultados anulados e a conta
suspensa ou encerrada. Casos graves podem ser comunicados às autoridades.
DOCUMENTO_LEGAL;
@endphp
<x-layouts::documento :versao="$versao" title="Regras de Conduta">
    {!! \Illuminate\Support\Str::markdown($documento, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
</x-layouts::documento>
