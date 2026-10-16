<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Temas, momentos e frases iniciais de provocação. Depois disso, a curadoria é feita no Painel.
 * Migração de dados: roda uma vez, em todo ambiente (inclusive produção).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared((string) file_get_contents(database_path('sql/provocacoes-iniciais.sql')));
    }

    public function down(): void
    {
        // Os dados saem junto com as tabelas, na migração anterior.
    }
};
