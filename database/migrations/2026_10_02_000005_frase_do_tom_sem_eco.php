<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A home sem mesa já mostra o fato "Nenhuma mesa ainda"; a frase do Tom que começava igual vira só
 * o comentário, sem repetir o card.
 */
return new class extends Migration
{
    private const ANTES = 'Nenhuma mesa ainda. Isso explica a paz.';

    private const DEPOIS = 'Isso explica a paz por aqui.';

    public function up(): void
    {
        DB::table('provocacoes')->where('texto', self::ANTES)->update(['texto' => self::DEPOIS]);
    }

    public function down(): void
    {
        DB::table('provocacoes')->where('texto', self::DEPOIS)->update(['texto' => self::ANTES]);
    }
};
