<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Frases de inicio.derrota_recente que não citam o vencedor. Servem quando o ouro é
     * dividido (não há um nome só para citar) e entram no sorteio normal da derrota.
     *
     * @var list<string>
     */
    private const FRASES = [
        'A última não foi sua. O placar já anotou.',
        'Derrota registrada. A explicação fica por sua conta.',
        'O pódio teve outros planos. Não era o seu.',
    ];

    public function up(): void
    {
        foreach (self::FRASES as $texto) {
            $id = DB::table('provocacoes')->where('texto', $texto)->value('id');
            if ($id === null) {
                $id = DB::table('provocacoes')->insertGetId([
                    'texto' => $texto,
                    'intensidade' => 1,
                    'perspectiva' => 'pessoa',
                ]);
            }

            DB::table('provocacoes_temas')->insertOrIgnore([
                'provocacao_id' => $id,
                'tema' => 'derrota_recente',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('provocacoes')->whereIn('texto', self::FRASES)->delete();
    }
};
