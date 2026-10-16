<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{texto: string, tema: string}> */
    private const FRASES = [
        ['texto' => 'Levou a mesa. O resto da turma já está inventando desculpa.', 'tema' => 'trofeu_vitoria'],
        ['texto' => 'Primeiro lugar da mesa inteira. Alguém precisava, e foi você.', 'tema' => 'trofeu_vitoria'],
        ['texto' => 'A mesa acabou, e o topo é seu. Vai ser difícil ser modesto com isso.', 'tema' => 'trofeu_vitoria'],
        ['texto' => 'Topo da mesa dividido com {outro}. Cada um vai contar essa história do seu jeito.', 'tema' => 'trofeu_empate'],
        ['texto' => 'Empate no primeiro lugar com {outro}. Ninguém ganhou sozinho, e ninguém vai deixar isso quieto.', 'tema' => 'trofeu_empate'],
        ['texto' => 'Você e {outro} ficaram juntos no topo da mesa. Sorte de um, habilidade do outro, e vice-versa.', 'tema' => 'trofeu_empate'],
    ];

    public function up(): void
    {
        DB::table('momentos_de_provocacao')->insertOrIgnore([
            ['codigo' => 'mesa.trofeu_vitoria', 'descricao' => 'Ganhou o troféu da mesa', 'variaveis' => ''],
            ['codigo' => 'mesa.trofeu_empate', 'descricao' => 'Empatou o troféu da mesa', 'variaveis' => 'outro'],
        ]);
        DB::table('temas_de_provocacao')->insertOrIgnore([
            ['codigo' => 'trofeu_vitoria', 'tipo' => 'generico', 'significado' => 'Ganhou o troféu da mesa'],
            ['codigo' => 'trofeu_empate', 'tipo' => 'generico', 'significado' => 'Empatou o troféu da mesa'],
        ]);
        DB::table('momentos_temas')->insertOrIgnore([
            ['momento' => 'mesa.trofeu_vitoria', 'tema' => 'trofeu_vitoria'],
            ['momento' => 'mesa.trofeu_empate', 'tema' => 'trofeu_empate'],
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
        DB::table('momentos_de_provocacao')->whereIn('codigo', [
            'mesa.trofeu_vitoria',
            'mesa.trofeu_empate',
        ])->delete();
        DB::table('temas_de_provocacao')->whereIn('codigo', ['trofeu_vitoria', 'trofeu_empate'])->delete();
    }
};
