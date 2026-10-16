<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melhores_resultados', function (Blueprint $tabela) {
            $tabela->foreignId('pessoa_id')->constrained('users')->cascadeOnDelete();
            $tabela->string('jogo', 40);
            $tabela->unsignedInteger('resultado')->comment('Tempo em milissegundos');
            $tabela->dateTime('obtido_em', 3);
            $tabela->primary(['pessoa_id', 'jogo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('melhores_resultados');
    }
};
