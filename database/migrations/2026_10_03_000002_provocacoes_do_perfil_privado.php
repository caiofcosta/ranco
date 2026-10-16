<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FRASES = [
        'Vocês não são amigos. Ainda dá tempo de mudar isso.',
        'Esse perfil é só para amigos, e aqui ainda não existe amizade.',
    ];

    public function up(): void
    {
        DB::table('temas_de_provocacao')->insert([
            'codigo' => 'perfil_privado',
            'tipo' => 'contexto',
            'significado' => 'Consulta de perfil sem amizade aceita',
        ]);
        DB::table('momentos_de_provocacao')->insert([
            'codigo' => 'perfil.privado',
            'descricao' => 'Perfil indisponível para quem não é amigo',
            'variaveis' => '',
        ]);
        DB::table('momentos_temas')->insert([
            'momento' => 'perfil.privado',
            'tema' => 'perfil_privado',
        ]);
        foreach (self::FRASES as $frase) {
            $id = DB::table('provocacoes')->insertGetId([
                'texto' => $frase,
                'intensidade' => 1,
                'perspectiva' => 'pessoa',
            ]);
            DB::table('provocacoes_temas')->insert([
                'provocacao_id' => $id,
                'tema' => 'perfil_privado',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('provocacoes')->whereIn('texto', self::FRASES)->delete();
        DB::table('momentos_de_provocacao')->where('codigo', 'perfil.privado')->delete();
        DB::table('temas_de_provocacao')->where('codigo', 'perfil_privado')->delete();
    }
};
