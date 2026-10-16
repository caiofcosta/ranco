<?php

declare(strict_types=1);

use Database\Seeders\NomesDeMesaSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opcoes', function (Blueprint $tabela): void {
            $tabela->string('categoria', 20)->default('generico');
            $tabela->index(['lista', 'categoria', 'ativo']);
        });

        (new NomesDeMesaSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('opcoes', function (Blueprint $tabela): void {
            $tabela->dropIndex(['lista', 'categoria', 'ativo']);
            $tabela->dropColumn('categoria');
        });
    }
};
