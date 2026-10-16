<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tentativas_solo_labirinto', function (Blueprint $tabela) {
            $tabela->index(['tempo', 'concluida_em', 'iniciada_em'], 'solo_labirinto_limpeza');
        });
    }

    public function down(): void
    {
        Schema::table('tentativas_solo_labirinto', function (Blueprint $tabela) {
            $tabela->dropIndex('solo_labirinto_limpeza');
        });
    }
};
