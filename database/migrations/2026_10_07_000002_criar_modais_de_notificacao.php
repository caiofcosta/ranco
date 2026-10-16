<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modais_de_notificacao', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->json('conteudo'); // { secoes: [{titulo, texto}, ...], texto_inicial, texto_final }
            $table->string('tipo')->default('onboarding'); // onboarding, novo_jogo, aviso, etc
            $table->boolean('ativo')->default(true);
            $table->timestamp('data_inicio')->nullable();
            $table->timestamp('data_fim')->nullable();
            $table->timestamps();

            $table->index('ativo');
            $table->index('tipo');
        });

        Schema::create('dismissoes_de_modal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('modal_de_notificacao_id')->constrained('modais_de_notificacao')->cascadeOnDelete();
            $table->timestamp('visualizou_em')->nullable();
            $table->timestamp('descartou_em')->nullable();
            $table->boolean('nao_mostrar_novamente')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'modal_de_notificacao_id']);
            $table->index('nao_mostrar_novamente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissoes_de_modal');
        Schema::dropIfExists('modais_de_notificacao');
    }
};
