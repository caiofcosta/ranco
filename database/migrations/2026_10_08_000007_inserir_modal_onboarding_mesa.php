<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modais_de_notificacao')->insert([
            'titulo' => 'Sua mesa está pronta!',
            'conteudo' => json_encode([
                'texto_inicial' => 'Aqui você reúne a galera para jogar.',
                'secoes' => [
                    ['texto' => 'Toque em Configurar para escolher quantas pessoas vão participar e preparar o primeiro jogo.'],
                    ['texto' => 'A cada partida, a galera ganha pontos. No final da mesa, quem somar mais leva o troféu!'],
                ],
            ]),
            'tipo' => 'onboarding_mesa',
            'ativo' => true,
            'data_inicio' => null,
            'data_fim' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modais_de_notificacao')->where('tipo', 'onboarding_mesa')->delete();
    }
};
