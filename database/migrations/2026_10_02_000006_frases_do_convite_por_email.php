<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Falas do Tom no e-mail de convite de mesa. Tema próprio para não misturar com a tela.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('temas_de_provocacao')->insertOrIgnore([
            'codigo' => 'convite_email',
            'tipo' => 'contexto',
            'significado' => 'Específico do e-mail de convite para uma mesa',
        ]);

        DB::table('momentos_de_provocacao')->insertOrIgnore([
            'codigo' => 'email.convite',
            'descricao' => 'Tom no e-mail de convite para uma mesa',
            'variaveis' => 'admin',
        ]);

        DB::table('momentos_temas')->insertOrIgnore([
            'momento' => 'email.convite',
            'tema' => 'convite_email',
        ]);

        foreach ($this->frases() as [$texto, $intensidade]) {
            DB::table('provocacoes')->insertOrIgnore([
                'texto' => $texto,
                'intensidade' => $intensidade,
                'perspectiva' => 'pessoa',
            ]);
            $id = DB::table('provocacoes')->where('texto', $texto)->value('id');
            DB::table('provocacoes_temas')->insertOrIgnore([
                'provocacao_id' => $id,
                'tema' => 'convite_email',
            ]);
        }
    }

    public function down(): void
    {
        $textos = array_column($this->frases(), 0);
        $ids = DB::table('provocacoes')->whereIn('texto', $textos)->pluck('id');
        DB::table('provocacoes_temas')->whereIn('provocacao_id', $ids)->delete();
        DB::table('provocacoes')->whereIn('id', $ids)->delete();
        DB::table('momentos_de_provocacao')->where('codigo', 'email.convite')->delete();
        DB::table('temas_de_provocacao')->where('codigo', 'convite_email')->delete();
    }

    /** @return list<array{0: string, 1: int}> */
    private function frases(): array
    {
        return [
            ['Eu não me meteria. Mas o convite já foi enviado.', 1],
            ['Parece que falaram de você.', 1],
            ['Alguém decidiu incluir você nisso.', 1],
            ['A mesa já existe. Faltava você. Ou não.', 1],
            ['{admin} te chamou. Eu só entrego as mensagens.', 1],
        ];
    }
};
