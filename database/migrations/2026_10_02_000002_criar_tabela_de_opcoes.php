<?php

use Database\Seeders\OpcoesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listas de opções editáveis no Painel (ex.: sugestões de nome de mesa). Já nasce com as opções
 * iniciais, para a produção recebê-las no `migrate`, sem passo extra de seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opcoes', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->string('lista', 60)->comment('Código da lista, ver App\Opcoes\Lista');
            $tabela->string('valor', 80)->comment('O que o sistema guarda; não muda depois de criado');
            $tabela->string('rotulo', 120)->comment('O que aparece na tela');
            $tabela->unsignedSmallInteger('ordem')->default(0);
            $tabela->boolean('ativo')->default(true);
            $tabela->timestamps();

            $tabela->unique(['lista', 'valor']);
            $tabela->index(['lista', 'ativo', 'ordem']);
        });

        (new OpcoesSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('opcoes');
    }
};
