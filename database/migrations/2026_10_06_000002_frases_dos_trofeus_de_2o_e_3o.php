<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{texto: string, tema: string}> */
    private const FRASES = [
        ['texto' => 'Segundo lugar. Perto do topo, longe da coroa, e a mesa vai lembrar disso.', 'tema' => 'trofeu_segundo'],
        ['texto' => 'Vice da mesa. A conversa de amanhã começa com "quase".', 'tema' => 'trofeu_segundo'],
        ['texto' => 'Segundo lugar garantido. O primeiro já tinha dono, mas o pódio é seu.', 'tema' => 'trofeu_segundo'],
        ['texto' => 'Terceiro lugar: subiu ao pódio e não levou a coroa. Até o bronze tem fã.', 'tema' => 'trofeu_terceiro'],
        ['texto' => 'Pódio conquistado, mesmo na última cadeira. Chegar no pódio também conta.', 'tema' => 'trofeu_terceiro'],
        ['texto' => 'Terceiro lugar. Ninguém vai lembrar do placar, mas o troféu fica na estante.', 'tema' => 'trofeu_terceiro'],
    ];

    public function up(): void
    {
        DB::table('momentos_de_provocacao')->insertOrIgnore([
            ['codigo' => 'mesa.trofeu_segundo', 'descricao' => 'Ficou em segundo na mesa', 'variaveis' => ''],
            ['codigo' => 'mesa.trofeu_terceiro', 'descricao' => 'Ficou em terceiro na mesa', 'variaveis' => ''],
        ]);
        DB::table('temas_de_provocacao')->insertOrIgnore([
            ['codigo' => 'trofeu_segundo', 'tipo' => 'generico', 'significado' => 'Ficou em segundo na mesa'],
            ['codigo' => 'trofeu_terceiro', 'tipo' => 'generico', 'significado' => 'Ficou em terceiro na mesa'],
        ]);
        DB::table('momentos_temas')->insertOrIgnore([
            ['momento' => 'mesa.trofeu_segundo', 'tema' => 'trofeu_segundo'],
            ['momento' => 'mesa.trofeu_terceiro', 'tema' => 'trofeu_terceiro'],
        ]);

        foreach (self::FRASES as $frase) {
            $id = DB::table('provocacoes')->where('texto', $frase['texto'])->value('id');
            if ($id === null) {
                $id = DB::table('provocacoes')->insertGetId([
                    'texto' => $frase['texto'],
                    'intensidade' => 1,
                    'perspectiva' => 'pessoa',
                ]);
            }
            DB::table('provocacoes_temas')->insertOrIgnore([
                'provocacao_id' => $id,
                'tema' => $frase['tema'],
            ]);
        }
    }

    public function down(): void
    {
        DB::table('provocacoes')->whereIn('texto', array_column(self::FRASES, 'texto'))->delete();
        DB::table('momentos_de_provocacao')->whereIn('codigo', ['mesa.trofeu_segundo', 'mesa.trofeu_terceiro'])->delete();
        DB::table('temas_de_provocacao')->whereIn('codigo', ['trofeu_segundo', 'trofeu_terceiro'])->delete();
    }
};
