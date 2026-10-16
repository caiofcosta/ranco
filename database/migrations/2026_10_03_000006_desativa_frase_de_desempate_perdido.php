<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEXTO = '{nome} perdeu no desempate para {vencedor}. Vice é só o primeiro dos perdedores.';

    public function up(): void
    {
        DB::table('provocacoes')->where('texto', self::TEXTO)->update(['ativa' => false]);
    }

    public function down(): void
    {
        DB::table('provocacoes')->where('texto', self::TEXTO)->update(['ativa' => true]);
    }
};
