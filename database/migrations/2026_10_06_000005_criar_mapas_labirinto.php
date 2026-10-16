<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapas_labirinto', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->string('nome', 80);
            $tabela->json('rascunho');
            $tabela->boolean('ativo')->default(false);
            $tabela->unsignedInteger('revisao')->default(1);
            $tabela->unsignedInteger('versao_publicada')->nullable();
            $tabela->timestamps();
        });

        Schema::create('versoes_labirinto', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->foreignId('mapa_id')->constrained('mapas_labirinto')->restrictOnDelete();
            $tabela->unsignedInteger('numero');
            $tabela->string('nome', 80);
            $tabela->json('desenho');
            $tabela->foreignId('publicador_id')->nullable()->constrained('users')->nullOnDelete();
            $tabela->timestamp('publicada_em');
            $tabela->unique(['mapa_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versoes_labirinto');
        Schema::dropIfExists('mapas_labirinto');
    }
};
