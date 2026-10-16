<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Conta invisível: aparece offline para os amigos e não interage com outras pessoas. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela) {
            $tabela->boolean('invisivel')->default(false)->after('visto_em');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabela) {
            $tabela->dropColumn('invisivel');
        });
    }
};
