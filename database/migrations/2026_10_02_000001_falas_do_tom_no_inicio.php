<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Falas do Tom no Início. Idempotente: bancos que já rodaram o SQL inicial ganham os momentos
 * novos e trocam o tipo de tema `tom` por `generico`. `inicio.sem_convites` sai do catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('temas_de_provocacao')->where('tipo', 'tom')->update(['tipo' => 'generico']);
        DB::statement(
            "ALTER TABLE temas_de_provocacao MODIFY tipo VARCHAR(10) NOT NULL COMMENT 'generico (frase que serve em vários lugares) ou contexto (frase específica de um lugar)'",
        );

        DB::table('momentos_de_provocacao')->where('codigo', 'inicio.sem_convites')->delete();

        DB::table('temas_de_provocacao')->insertOrIgnore([
            ['codigo' => 'vez_pendente', 'tipo' => 'contexto', 'significado' => 'É a vez da pessoa, na tela inicial'],
            ['codigo' => 'convite_pendente', 'tipo' => 'contexto', 'significado' => 'Convite de mesa esperando resposta'],
            ['codigo' => 'partida_pronta', 'tipo' => 'contexto', 'significado' => 'Partida em configuração já com jogadores suficientes'],
            ['codigo' => 'vitoria_recente', 'tipo' => 'contexto', 'significado' => 'A pessoa venceu a última partida da mesa'],
            ['codigo' => 'derrota_recente', 'tipo' => 'contexto', 'significado' => 'A pessoa perdeu a última partida da mesa'],
            ['codigo' => 'mesa_aguardando', 'tipo' => 'contexto', 'significado' => 'Mesa sem outros participantes'],
            ['codigo' => 'sem_novidade', 'tipo' => 'contexto', 'significado' => 'Nada acontecendo na tela inicial'],
        ]);

        DB::table('momentos_de_provocacao')->insertOrIgnore([
            ['codigo' => 'inicio.vez_pendente', 'descricao' => 'É a vez da pessoa numa partida em andamento', 'variaveis' => ''],
            ['codigo' => 'inicio.convite_pendente', 'descricao' => 'Há convite de mesa esperando resposta', 'variaveis' => 'admin'],
            ['codigo' => 'inicio.partida_pronta', 'descricao' => 'A pessoa é admin e a partida em configuração já pode começar', 'variaveis' => ''],
            ['codigo' => 'inicio.vitoria_recente', 'descricao' => 'A última partida da mesa terminou com vitória da pessoa', 'variaveis' => 'vencedor'],
            ['codigo' => 'inicio.derrota_recente', 'descricao' => 'A última partida da mesa terminou com derrota da pessoa', 'variaveis' => 'vencedor'],
            ['codigo' => 'inicio.mesa_aguardando', 'descricao' => 'A pessoa está numa mesa sem outros participantes', 'variaveis' => ''],
            ['codigo' => 'inicio.sem_mesa', 'descricao' => 'A pessoa não está em nenhuma mesa', 'variaveis' => ''],
            ['codigo' => 'inicio.sem_novidade', 'descricao' => 'Nada acontecendo na tela inicial', 'variaveis' => ''],
        ]);

        DB::table('momentos_temas')->insertOrIgnore([
            ['momento' => 'inicio.vez_pendente', 'tema' => 'vez_pendente'],
            ['momento' => 'inicio.convite_pendente', 'tema' => 'convite_pendente'],
            ['momento' => 'inicio.partida_pronta', 'tema' => 'partida_pronta'],
            ['momento' => 'inicio.vitoria_recente', 'tema' => 'vitoria_recente'],
            ['momento' => 'inicio.derrota_recente', 'tema' => 'derrota_recente'],
            ['momento' => 'inicio.mesa_aguardando', 'tema' => 'mesa_aguardando'],
            ['momento' => 'inicio.sem_mesa', 'tema' => 'vazio'],
            ['momento' => 'inicio.sem_novidade', 'tema' => 'sem_novidade'],
        ]);

        foreach ($this->frases() as [$texto, $intensidade, $tema]) {
            DB::table('provocacoes')->insertOrIgnore([
                'texto' => $texto,
                'intensidade' => $intensidade,
                'perspectiva' => 'pessoa',
            ]);
            $id = DB::table('provocacoes')->where('texto', $texto)->value('id');
            DB::table('provocacoes_temas')->insertOrIgnore([
                'provocacao_id' => $id,
                'tema' => $tema,
            ]);
        }
    }

    public function down(): void
    {
        $textos = array_column($this->frases(), 0);
        $ids = DB::table('provocacoes')->whereIn('texto', $textos)->pluck('id');
        DB::table('provocacoes_temas')->whereIn('provocacao_id', $ids)->delete();
        DB::table('provocacoes')->whereIn('id', $ids)->delete();

        DB::table('momentos_de_provocacao')->whereIn('codigo', [
            'inicio.vez_pendente',
            'inicio.convite_pendente',
            'inicio.partida_pronta',
            'inicio.vitoria_recente',
            'inicio.derrota_recente',
            'inicio.mesa_aguardando',
            'inicio.sem_mesa',
            'inicio.sem_novidade',
        ])->delete();

        DB::table('temas_de_provocacao')->whereIn('codigo', [
            'vez_pendente',
            'convite_pendente',
            'partida_pronta',
            'vitoria_recente',
            'derrota_recente',
            'mesa_aguardando',
            'sem_novidade',
        ])->delete();

        DB::table('momentos_de_provocacao')->insertOrIgnore([
            'codigo' => 'inicio.sem_convites',
            'descricao' => 'Tela inicial sem nenhum convite',
            'variaveis' => '',
        ]);
        DB::table('momentos_temas')->insertOrIgnore([
            'momento' => 'inicio.sem_convites',
            'tema' => 'vazio',
        ]);

        DB::table('temas_de_provocacao')->where('tipo', 'generico')->update(['tipo' => 'tom']);
        DB::statement(
            "ALTER TABLE temas_de_provocacao MODIFY tipo VARCHAR(10) NOT NULL COMMENT 'tom (frase genérica) ou contexto (frase específica de um lugar)'",
        );
    }

    /** @return list<array{0: string, 1: int, 2: string}> */
    private function frases(): array
    {
        return [
            ['Sua vez. A mesa finge que não está olhando.', 2, 'vez_pendente'],
            ['É a sua vez. O relógio, esse sim, está olhando.', 2, 'vez_pendente'],
            ['Sua vez chegou. Hesitar também gasta prazo.', 2, 'vez_pendente'],
            ['Agora é com você. Sem pressão. Quer dizer, um pouco.', 2, 'vez_pendente'],
            ['Sua vez. Ninguém vai jogar por você, infelizmente.', 2, 'vez_pendente'],
            ['A vez é sua. Desculpa não conta como tentativa.', 2, 'vez_pendente'],

            ['{admin} te chamou para uma mesa. Isso raramente termina bem.', 2, 'convite_pendente'],
            ['Convite de {admin}. Ou é amizade, ou é estratégia.', 2, 'convite_pendente'],
            ['{admin} quer você na mesa. Desconfie do entusiasmo.', 2, 'convite_pendente'],
            ['{admin} mandou convite. Recusar também é uma jogada.', 2, 'convite_pendente'],
            ['Tem convite de {admin}. A paz tinha prazo.', 2, 'convite_pendente'],

            ['Já tem gente suficiente. Só falta coragem de começar.', 2, 'partida_pronta'],
            ['Gente suficiente para uma confusão decente. Pode começar.', 2, 'partida_pronta'],
            ['Quórum atingido. A discussão pode começar oficialmente.', 2, 'partida_pronta'],
            ['Ninguém mais precisa chegar. Adiar agora é escolha sua.', 2, 'partida_pronta'],
            ['A mesa está completa. Agora é com quem manda nela.', 2, 'partida_pronta'],

            ['Você ganhou a última. Tente parecer humilde.', 1, 'vitoria_recente'],
            ['Vitória sua. A mesa vai preferir chamar de sorte.', 1, 'vitoria_recente'],
            ['Você venceu. Aproveite, costuma durar até a próxima partida.', 1, 'vitoria_recente'],
            ['Primeiro lugar. Deve estar agradabilíssimo conviver com você agora.', 1, 'vitoria_recente'],
            ['Ganhou. O histórico registrou, a modéstia não.', 1, 'vitoria_recente'],

            ['{vencedor} levou a última. Só deixando registrado.', 1, 'derrota_recente'],
            ['A última ficou com {vencedor}. Assumindo que havia um plano.', 1, 'derrota_recente'],
            ['{vencedor} ganhou essa. A próxima ainda não tem dono.', 1, 'derrota_recente'],
            ['Derrota registrada. {vencedor} agradece a colaboração.', 1, 'derrota_recente'],
            ['O placar foi de {vencedor}. A explicação fica por sua conta.', 1, 'derrota_recente'],

            ['A mesa existe. Agora falta arrumar companhia para o problema.', 1, 'mesa_aguardando'],
            ['Mesa pronta e ninguém para perder. Ainda.', 1, 'mesa_aguardando'],
            ['Uma mesa para uma pessoa. Até a solidão tem regras.', 1, 'mesa_aguardando'],
            ['Nenhuma derrota na mesa. Também nenhum adversário.', 1, 'mesa_aguardando'],
            ['Mesa montada. Os adversários estão atrasados. Todos eles.', 1, 'mesa_aguardando'],

            ['Nenhuma mesa ainda. Isso explica a paz.', 1, 'vazio'],
            ['Sem mesa, sem derrota. Estatisticamente impecável.', 1, 'vazio'],
            ['Nenhuma mesa. A reputação segue intacta, por falta de provas.', 1, 'vazio'],
            ['Zero mesas. Até o ranço precisa de um lugar para morar.', 1, 'vazio'],
            ['Nenhuma rivalidade em andamento. Por enquanto.', 1, 'vazio'],
            ['Sem mesa. Também sem ninguém para culpar.', 1, 'vazio'],
            ['Nenhuma partida no seu nome. Nem vitória, nem desculpa.', 1, 'vazio'],
            ['Calmo demais para um lugar chamado RANÇO.', 1, 'vazio'],
            ['Nenhuma mesa à vista. As amizades seguem intactas.', 1, 'vazio'],
            ['Sem mesa. O histórico está em branco e bem comportado.', 1, 'vazio'],
            ['Nenhuma mesa. A paz é temporária, mas é sua.', 1, 'vazio'],
            ['Nada para perder hoje. Literalmente.', 1, 'vazio'],

            ['Estranhamente tranquilo por aqui.', 1, 'sem_novidade'],
            ['Nada esperando por você. Aproveite, é raro.', 1, 'sem_novidade'],
            ['Nenhuma confusão pendente. Por enquanto.', 1, 'sem_novidade'],
            ['Nada para resolver agora. Desconfie.', 1, 'sem_novidade'],
            ['Tudo em dia. Isso não costuma durar.', 1, 'sem_novidade'],
            ['Nenhuma pendência. O ranço está só cochilando.', 1, 'sem_novidade'],
            ['Silêncio na mesa. Ou paz, ou estratégia.', 1, 'sem_novidade'],
            ['Nada de novo. O Tom continua de olho.', 1, 'sem_novidade'],
            ['Nenhuma vez sua, nenhum convite. Sossego com prazo de validade.', 1, 'sem_novidade'],
            ['Por enquanto, ninguém precisa de você. Não leve para o lado pessoal.', 1, 'sem_novidade'],
            ['Nada pendente. Situação suspeita, mas legal.', 1, 'sem_novidade'],
        ];
    }
};
