<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{texto: string, tema: string}> */
    private const FRASES = [
        ['texto' => 'Segundo lugar dividido. Ninguém levou a coroa, e o vice ficou em condomínio.', 'tema' => 'trofeu_segundo_empate'],
        ['texto' => 'Empate no segundo lugar. O pódio ficou apertado, mas coube todo mundo.', 'tema' => 'trofeu_segundo_empate'],
        ['texto' => 'Vice da mesa, em dupla ou mais. Dividir o quase também é uma arte.', 'tema' => 'trofeu_segundo_empate'],
        ['texto' => 'Terceiro lugar dividido. O bronze veio, só que repartido em conversa.', 'tema' => 'trofeu_terceiro_empate'],
        ['texto' => 'Empate no terceiro lugar. O pódio lotou na última cadeira.', 'tema' => 'trofeu_terceiro_empate'],
        ['texto' => 'Terceiro lugar com companhia. Pelo menos ninguém subiu sozinho no último degrau.', 'tema' => 'trofeu_terceiro_empate'],
    ];

    public function up(): void
    {
        DB::table('momentos_de_provocacao')->insertOrIgnore([
            ['codigo' => 'mesa.trofeu_segundo_empate', 'descricao' => 'Empatou em segundo na mesa', 'variaveis' => ''],
            ['codigo' => 'mesa.trofeu_terceiro_empate', 'descricao' => 'Empatou em terceiro na mesa', 'variaveis' => ''],
        ]);
        DB::table('temas_de_provocacao')->insertOrIgnore([
            ['codigo' => 'trofeu_segundo_empate', 'tipo' => 'generico', 'significado' => 'Empatou em segundo na mesa'],
            ['codigo' => 'trofeu_terceiro_empate', 'tipo' => 'generico', 'significado' => 'Empatou em terceiro na mesa'],
        ]);
        DB::table('momentos_temas')->insertOrIgnore([
            ['momento' => 'mesa.trofeu_segundo_empate', 'tema' => 'trofeu_segundo_empate'],
            ['momento' => 'mesa.trofeu_terceiro_empate', 'tema' => 'trofeu_terceiro_empate'],
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
        DB::table('momentos_de_provocacao')->whereIn('codigo', ['mesa.trofeu_segundo_empate', 'mesa.trofeu_terceiro_empate'])->delete();
        DB::table('temas_de_provocacao')->whereIn('codigo', ['trofeu_segundo_empate', 'trofeu_terceiro_empate'])->delete();
    }
};
