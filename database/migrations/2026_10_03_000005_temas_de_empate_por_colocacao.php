<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, array{momento: string, significado: string, frases: list<string>}> */
    private const GRUPOS = [
        'primeiro_empate' => [
            'momento' => 'partida.primeiro_lugar_empate',
            'significado' => 'Empatou em primeiro lugar',
            'frases' => [
                'Empate no topo com {outro}. A mesa vai precisar de uma explicação.',
                'Dividiram o ouro com {outro}. Ninguém sai limpo disso.',
                'Você e {outro} no topo, cada um convencido de que foi habilidade.',
            ],
        ],
        'segundo_empate' => [
            'momento' => 'partida.segundo_lugar_empate',
            'significado' => 'Empatou em segundo lugar',
            'frases' => [
                'Prata dividida com {outro}. Nenhum dos dois vai aceitar sem reclamar.',
                'Você e {outro} no segundo lugar. Os dois acham que deveriam ter ganhado.',
                'Empataram com {outro}. Ninguém ficou com a glória, só com a prata.',
            ],
        ],
        'terceiro_empate' => [
            'momento' => 'partida.terceiro_lugar_empate',
            'significado' => 'Empatou em terceiro lugar',
            'frases' => [
                'Bronze dividido com {outro}. Os dois estão igualmente insatisfeitos.',
                'Você e {outro} no mesmo bronze. Pelo menos estão no mesmo lugar.',
                'Terceiro lugar empatado com {outro}. Dividiram o que ninguém quis.',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::GRUPOS as $tema => $grupo) {
            DB::table('temas_de_provocacao')->insertOrIgnore([
                'codigo' => $tema,
                'tipo' => 'generico',
                'significado' => $grupo['significado'],
            ]);
            DB::table('momentos_temas')->where('momento', $grupo['momento'])->where('tema', '!=', $tema)->delete();
            DB::table('momentos_temas')->insertOrIgnore([
                'momento' => $grupo['momento'],
                'tema' => $tema,
            ]);
            foreach ($grupo['frases'] as $texto) {
                $id = DB::table('provocacoes')->where('texto', $texto)->value('id');
                if ($id === null) {
                    continue;
                }
                DB::table('provocacoes_temas')->where('provocacao_id', $id)->where('tema', '!=', $tema)->delete();
                DB::table('provocacoes_temas')->insertOrIgnore([
                    'provocacao_id' => $id,
                    'tema' => $tema,
                ]);
            }
        }

        DB::table('temas_de_provocacao')->where('codigo', 'empate')->delete();
    }

    public function down(): void
    {
        DB::table('temas_de_provocacao')->insertOrIgnore([
            'codigo' => 'empate',
            'tipo' => 'generico',
            'significado' => 'Empatou a colocação e divide a medalha',
        ]);

        foreach (self::GRUPOS as $tema => $grupo) {
            DB::table('momentos_temas')->insertOrIgnore([
                'momento' => $grupo['momento'],
                'tema' => 'empate',
            ]);
            foreach ($grupo['frases'] as $texto) {
                $id = DB::table('provocacoes')->where('texto', $texto)->value('id');
                if ($id === null) {
                    continue;
                }
                DB::table('provocacoes_temas')->insertOrIgnore([
                    'provocacao_id' => $id,
                    'tema' => 'empate',
                ]);
            }
        }

        DB::table('temas_de_provocacao')->whereIn('codigo', array_keys(self::GRUPOS))->delete();
    }
};
