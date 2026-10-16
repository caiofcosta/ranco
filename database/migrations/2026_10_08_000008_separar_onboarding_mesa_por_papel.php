<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A orientação de primeira entrada na mesa passa a ter dois textos, por papel: o do administrador
 * (quem prepara a mesa) e o do participante (quem espera a galera). O tipo antigo `onboarding_mesa`
 * vira `onboarding_mesa_admin` com o texto novo; o `onboarding_mesa_participante` é criado. A
 * preferência "Não mostrar de novo" é por usuário e por modal, então já fica individual e por papel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modais_de_notificacao')
            ->where('tipo', 'onboarding_mesa')
            ->update([
                'tipo' => 'onboarding_mesa_admin',
                'titulo' => 'Bora preparar a mesa?',
                'conteudo' => json_encode([
                    'texto_inicial' => 'Você organiza a brincadeira por aqui!',
                    'secoes' => [
                        ['texto' => 'Toque em Preparar mesa para escolher quantas pessoas vão jogar, organizar os lugares e escolher o primeiro jogo.'],
                        ['texto' => 'Cada partida soma pontos. Quando quiser terminar a disputa, é só finalizar e ver quem levou os troféus!'],
                    ],
                ]),
                'updated_at' => now(),
            ]);

        // Garante o modal do admin mesmo em bancos que nunca tiveram o tipo antigo.
        if (! DB::table('modais_de_notificacao')->where('tipo', 'onboarding_mesa_admin')->exists()) {
            DB::table('modais_de_notificacao')->insert([
                'titulo' => 'Bora preparar a mesa?',
                'conteudo' => json_encode([
                    'texto_inicial' => 'Você organiza a brincadeira por aqui!',
                    'secoes' => [
                        ['texto' => 'Toque em Preparar mesa para escolher quantas pessoas vão jogar, organizar os lugares e escolher o primeiro jogo.'],
                        ['texto' => 'Cada partida soma pontos. Quando quiser terminar a disputa, é só finalizar e ver quem levou os troféus!'],
                    ],
                ]),
                'tipo' => 'onboarding_mesa_admin',
                'ativo' => true,
                'data_inicio' => null,
                'data_fim' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('modais_de_notificacao')->where('tipo', 'onboarding_mesa_participante')->exists()) {
            DB::table('modais_de_notificacao')->insert([
                'titulo' => 'Você entrou na mesa!',
                'conteudo' => json_encode([
                    'texto_inicial' => 'Agora é esperar a galera!',
                    'secoes' => [
                        ['texto' => 'Quem administra a mesa organiza os participantes e escolhe o próximo jogo. Quando estiver tudo pronto, você poderá entrar na partida.'],
                        ['texto' => 'Cada jogo vale pontos, e tudo vai somando na classificação da mesa. No final, a gente descobre quem levou os troféus!'],
                    ],
                ]),
                'tipo' => 'onboarding_mesa_participante',
                'ativo' => true,
                'data_inicio' => null,
                'data_fim' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('modais_de_notificacao')->where('tipo', 'onboarding_mesa_participante')->delete();
        DB::table('modais_de_notificacao')
            ->where('tipo', 'onboarding_mesa_admin')
            ->update(['tipo' => 'onboarding_mesa', 'titulo' => 'Sua mesa está pronta!', 'updated_at' => now()]);
    }
};
