<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medalhas', function (Blueprint $tabela) {
            $tabela->dateTime('partida_criada_em', 3)->nullable()->after('concedida_em');
        });
        Schema::table('trofeus', function (Blueprint $tabela) {
            $tabela->dateTime('mesa_criada_em', 3)->nullable()->after('concedido_em');
        });
    }

    public function down(): void
    {
        Schema::table('medalhas', function (Blueprint $tabela) {
            $tabela->dropColumn('partida_criada_em');
        });
        Schema::table('trofeus', function (Blueprint $tabela) {
            $tabela->dropColumn('mesa_criada_em');
        });
    }
};
