<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FRASES = [
        'Lista de amizades vazia. O ranço precisa de plateia.',
        'Ninguém por aqui para provocar. Ainda.',
        'Rivalidade sem rival não conta. Chame alguém.',
        'Por enquanto, quem perde é ninguém. Isso precisa mudar.',
        'Zero amizades. Até o Tom já estranhou o silêncio.',
    ];

    public function up(): void
    {
        DB::table('temas_de_provocacao')->insert([
            'codigo' => 'amizade_vazia',
            'tipo' => 'contexto',
            'significado' => 'Início sem nenhuma amizade nem convite de amizade',
        ]);
        DB::table('momentos_de_provocacao')->insert([
            'codigo' => 'amizade.vazia',
            'descricao' => 'Coluna de amizades do Início sem ninguém',
            'variaveis' => '',
        ]);
        DB::table('momentos_temas')->insert([
            'momento' => 'amizade.vazia',
            'tema' => 'amizade_vazia',
        ]);
        foreach (self::FRASES as $frase) {
            $id = DB::table('provocacoes')->insertGetId([
                'texto' => $frase,
                'intensidade' => 1,
                'perspectiva' => 'pessoa',
            ]);
            DB::table('provocacoes_temas')->insert([
                'provocacao_id' => $id,
                'tema' => 'amizade_vazia',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('provocacoes')->whereIn('texto', self::FRASES)->delete();
        DB::table('momentos_de_provocacao')->where('codigo', 'amizade.vazia')->delete();
        DB::table('temas_de_provocacao')->where('codigo', 'amizade_vazia')->delete();
    }
};
