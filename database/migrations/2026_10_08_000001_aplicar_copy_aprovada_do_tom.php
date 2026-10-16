<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Copy aprovada em 2026-10-05. Preserva IDs, intensidades e curadoria divergente; separa contextos aprovados. */
return new class extends Migration
{
    public const FRASES = [
        'Errou logo de primeira. Começamos bem.' => 'Primeira tentativa. O aquecimento não conta.',
        'Algo está dizendo para você não entrar. Hoje não vai ser o seu dia. Vai querer tentar mesmo assim?' => 'Hoje o login resolveu competir também.',
        'O universo está te dando mais um reforço. Pensa bem.' => 'A senha está fazendo jogo duro.',
        'Segunda vez. Já pensou em anotar a senha num papelzinho? Brincadeira, não faça isso.' => 'De novo. Pelo menos aqui não vale ponto.',
        'Se você não consegue nem entrar, como é que quer jogar? Tem certeza de que é isso que você quer fazer?' => 'A senha segue na liderança. Por enquanto.',
        'Tentativa número {tentativa}… A senha não muda sozinha, viu? Tem o “esqueci minha senha” ali, sem vergonha nenhuma.' => 'A senha ganhou essa rodada. A próxima está aberta.',
        'Mais uma e você vai de castigo. Só avisando.' => 'A senha pediu prorrogação.',
        'Errou de novo. Pelo menos aqui a derrota não conta no ranking.' => 'A senha ainda não entregou o jogo.',
        'Pronto, cansou. Vai tomar uma água e volta daqui a {minutos} minutos. O labirinto te espera, rindo.' => 'Intervalo técnico. Daqui a pouco tem revanche.',
        'Castigo: {minutos} minutos sem tentar. Aproveita para lembrar a senha com calma.' => 'Tempo para reorganizar a estratégia.',
        'Calma, o carteiro está sem fôlego. Mais links só daqui a {minutos} minutos.' => 'O primeiro e-mail mal saiu do vestiário.',
        'Sua caixa de entrada já está lotada de nós. Espera {minutos} minutos antes de pedir outro.' => 'Esse e-mail já está a caminho. Sem ultrapassagem.',
        '{admin} resolveu arrumar problema e te chamou para uma mesa.' => '{admin} te chamou pra mesa. Agora ficou interessante.',
        '{admin} te chamou para uma mesa. Isso raramente termina bem.' => '{admin} te chamou para uma mesa. Isso costuma render história.',
        'Convite de {admin}. Ou é amizade, ou é estratégia.' => 'Convite de {admin}. Amizade de um lado, estratégia do outro.',
        '{admin} quer você na mesa. Desconfie do entusiasmo.' => '{admin} está animado demais. Deve ter treinado.',
        '{admin} mandou convite. Recusar também é uma jogada.' => '{admin} mandou convite. Aceitar rende mais história, mas você decide.',
        'Tem convite de {admin}. A paz tinha prazo.' => 'Tem convite de {admin}. A próxima disputa já tem endereço.',
        'Já tem gente suficiente. Só falta coragem de começar.' => 'Já tem gente suficiente. Tudo pronto. Falta só apertar o botão.',
        'Gente suficiente para uma confusão decente. Pode começar.' => 'Gente suficiente para uma boa disputa. Pode começar.',
        'Quórum atingido. A discussão pode começar oficialmente.' => 'Já dá jogo. Agora começa a disputa.',
        'Ninguém mais precisa chegar. Adiar agora é escolha sua.' => 'Ninguém mais precisa chegar. Tudo pronto. O primeiro lance é de vocês.',
        'A mesa está completa. Agora é com quem manda nela.' => 'Já dá jogo. Agora é com o administrador.',
        'A mesa existe. Agora falta arrumar companhia para o problema.' => 'A mesa está pronta. Agora chama quem vai entrar nessa história.',
        'Mesa pronta e ninguém para perder. Ainda.' => 'Mesa pronta. Falta só quem vai disputar com você.',
        'Uma mesa para uma pessoa. Até a solidão tem regras.' => 'A mesa toda é sua por enquanto. Aproveita.',
        'Nenhuma derrota na mesa. Também nenhum adversário.' => 'Placar zerado. Falta só a turma chegar.',
        'Mesa montada. Os adversários estão atrasados. Todos eles.' => 'A mesa está pronta. A turma ainda está chegando.',
        'Isso explica a paz por aqui.' => 'Quieto demais por aqui. Bora abrir uma mesa?',
        'Sem mesa. Também sem ninguém para culpar.' => 'Sem mesa por enquanto. Sem revanche também.',
        'Nenhuma mesa à vista. As amizades seguem intactas.' => 'A turma ainda está fora da mesa. Por enquanto.',
        'Nenhuma mesa. A paz é temporária, mas é sua.' => 'Ainda dá tempo de começar a primeira disputa.',
        'Nada para perder hoje. Literalmente.' => 'Nenhuma partida hoje. Ainda.',
        'Por enquanto, ninguém precisa de você. Não leve para o lado pessoal.' => 'Nada pendente. O Tom oficialmente ficou sem assunto.',
        'Nenhuma confusão pendente. Por enquanto.' => 'Tudo tranquilo por aqui. Estranhamente tranquilo.',
        'Nada para resolver agora. Desconfie.' => 'Nada novo. O Tom continua procurando motivo para aparecer.',
        'Tudo em dia. Isso não costuma durar.' => 'Tudo em dia. Aproveite esse raro momento de organização.',
        'Parabéns. Já pode ficar insuportável por alguns minutos.' => 'Ganhou. Agora tenta disfarçar o sorriso.',
        'Tecnicamente você participou.' => 'Não foi dessa vez. A revanche continua aberta.',
        'Primeiro lugar. Deve estar agradabilíssimo conviver com você agora.' => 'Primeiro lugar. Agora tenta agir normalmente.',
        'Derrota registrada. {vencedor} agradece a colaboração.' => '{vencedor} levou essa. Você fica com a revanche.',
        'Ganhou o jogo. Agora precisa aguentar a mesa comemorando.' => 'Ganhou. Agora vai ouvir que foi sorte.',
        'Prata. Perto o bastante para incomodar, longe o bastante para não levar nada além disso.' => 'Prata. Foi por pouco — e isso rende revanche.',
        'Terceiro lugar. Pelo menos alguém ficou atrás de você.' => 'Bronze no peito. Na próxima, mira mais alto.',
        'Empate no topo com {outro}. A mesa vai precisar de uma explicação.' => 'Empate no topo. Cada um vai contar essa história do seu jeito.',
        'Dividiram o ouro com {outro}. Ninguém sai limpo disso.' => 'Topo dividido. Cada um escolhe sua versão da história.',
        'Prata dividida com {outro}. Nenhum dos dois vai aceitar sem reclamar.' => 'Prata dividida. A revanche já ganhou assunto.',
        'Terceiro lugar empatado com {outro}. Dividiram o que ninguém quis.' => 'Terceiro lugar empatado. O pódio ficou concorrido.',
        'Lista de amizades vazia. O ranço precisa de plateia.' => 'Por enquanto, só o Tom por aqui. Chama reforço.',
        'Por enquanto, quem perde é ninguém. Isso precisa mudar.' => 'Por enquanto, ninguém ganhou nada. Bora mudar isso?',
        'Zero amizades. Até o Tom já estranhou o silêncio.' => 'O primeiro convite ainda não saiu. O Tom está esperando.',
    ];

    public const OPCOES = [
        'Família (infelizmente)' => 'Família em jogo',
        'Amizade em teste' => 'Amizade valendo ponto',
        'Sem perdão' => 'Revanche marcada',
        'Rivalidade saudável (dizem)' => 'Rivalidade saudável',
        'Laboratório de rancor' => 'Laboratório da revanche',
        'Ninguém sai daqui com amizade' => 'Amigos até o placar abrir',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (self::FRASES as $antes => $depois) {
                $frase = DB::table('provocacoes')->whereRaw('BINARY texto = ?', [$antes])->lockForUpdate()->first();
                if ($frase === null) {
                    continue; // Já aplicada ou texto modificado pela curadoria.
                }
                DB::table('provocacoes')->where('id', $frase->id)->update(['texto' => $depois]);
                if ($depois === 'A senha está fazendo jogo duro.') {
                    DB::table('provocacoes_temas')->where('provocacao_id', $frase->id)->delete();
                    DB::table('provocacoes_temas')->insert(['provocacao_id' => $frase->id, 'tema' => 'acesso']);
                    $this->contexto('interrupcao', 'Comentário sobre jogada interrompida', 'partida.tentativa_interrompida');
                    $texto = 'O plano era outro. A próxima jogada está em aberto.';
                    if (! DB::table('provocacoes')->where('texto', $texto)->exists()) {
                        $id = DB::table('provocacoes')->insertGetId([
                            'texto' => $texto, 'intensidade' => $frase->intensidade,
                            'perspectiva' => $frase->perspectiva, 'ativa' => $frase->ativa,
                        ]);
                        DB::table('provocacoes_temas')->insert(['provocacao_id' => $id, 'tema' => 'interrupcao']);
                    }
                }
                if ($depois === 'Prata. Foi por pouco — e isso rende revanche.') {
                    $this->contexto('segundo_lugar', 'Comentário exclusivo sobre a prata da partida', 'partida.segundo_lugar');
                    DB::table('provocacoes_temas')->where('provocacao_id', $frase->id)->delete();
                    DB::table('provocacoes_temas')->insert(['provocacao_id' => $frase->id, 'tema' => 'segundo_lugar']);
                }
            }
            foreach (self::OPCOES as $antes => $depois) {
                DB::table('opcoes')->where('lista', 'nomes_de_mesa')
                    ->whereRaw('BINARY rotulo = ?', [$antes])->update(['rotulo' => $depois]);
            }
        });
    }

    private function contexto(string $tema, string $significado, string $momento): void
    {
        DB::table('temas_de_provocacao')->insertOrIgnore(['codigo' => $tema, 'tipo' => 'contexto', 'significado' => $significado]);
        DB::table('momentos_temas')->insertOrIgnore(['momento' => $momento, 'tema' => $tema]);
    }

    public function down(): void
    {
        // Migração editorial somente para frente: não sobrescrever curadoria posterior.
    }
};
