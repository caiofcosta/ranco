<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapas_labirinto', function (Blueprint $tabela) {
            $tabela->string('codigo_seed', 40)->nullable()->unique();
        });
        Schema::create('tentativas_solo_labirinto', function (Blueprint $tabela) {
            $tabela->uuid('id')->primary();
            $tabela->foreignId('pessoa_id')->constrained('users')->cascadeOnDelete();
            $tabela->foreignId('versao_id')->constrained('versoes_labirinto')->restrictOnDelete();
            $tabela->timestamp('iniciada_em');
            $tabela->unsignedInteger('tempo')->nullable();
            $tabela->boolean('recorde')->default(false);
            $tabela->timestamp('concluida_em')->nullable();
            $tabela->index(['pessoa_id', 'versao_id', 'tempo'], 'solo_labirinto_recordes');
            $tabela->index(['pessoa_id', 'versao_id', 'concluida_em'], 'solo_labirinto_historico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentativas_solo_labirinto');
        Schema::table('mapas_labirinto', fn (Blueprint $tabela) => $tabela->dropColumn('codigo_seed'));
    }
};
