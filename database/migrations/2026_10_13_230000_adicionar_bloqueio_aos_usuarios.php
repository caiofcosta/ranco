<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->timestamp('bloqueado_em')->nullable()->after('invisivel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabela): void {
            $tabela->dropColumn('bloqueado_em');
        });
    }
};
