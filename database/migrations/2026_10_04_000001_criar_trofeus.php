<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trofeus', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->string('pessoa', 80)->index();
            $tabela->string('mesa_id', 40);
            $tabela->enum('tipo', ['vitoria', 'empate']);
            $tabela->string('mesa_nome', 300);
            $tabela->text('frase')->nullable();
            $tabela->dateTime('concedido_em', 3);
            $tabela->unique(['mesa_id', 'pessoa']);
            $tabela->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trofeus');
    }
};
