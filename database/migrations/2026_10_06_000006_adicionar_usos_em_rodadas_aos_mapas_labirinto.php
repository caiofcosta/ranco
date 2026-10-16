<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapas_labirinto', function (Blueprint $tabela) {
            // null: medição ainda não iniciada; zero: medição ativa sem uso.
            $tabela->unsignedBigInteger('usos_em_rodadas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mapas_labirinto', function (Blueprint $tabela) {
            $tabela->dropColumn('usos_em_rodadas');
        });
    }
};
