<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Última atividade da conta com o RANÇO aberto; base do "online" entre amigos. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela) {
            $tabela->dateTime('visto_em', 3)->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabela) {
            $tabela->dropColumn('visto_em');
        });
    }
};
