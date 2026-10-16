<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nome da mesa: fonte da verdade no estado serializado; a coluna serve para consulta. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $tabela) {
            $tabela->string('nome', 40)->nullable()->after('admin');
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $tabela) {
            $tabela->dropColumn('nome');
        });
    }
};
