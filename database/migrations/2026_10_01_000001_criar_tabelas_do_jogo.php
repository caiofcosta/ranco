<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas do jogo. A mesa é gravada inteira (serializada) em `mesas.estado`; as demais tabelas da
 * mesa são projeções usadas em consultas, no histórico e na garantia da mesa única.
 * Datas em UTC. Identificadores em português, sem acento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $tabela) {
            $tabela->string('id', 40)->primary();
            $tabela->string('admin', 80);
            $tabela->string('situacao', 20);
            $tabela->tinyInteger('partida_em_andamento')->default(0)->index();
            $tabela->binary('estado')->comment('Mesa serializada, com partidas e participantes locais');
            $tabela->integer('versao')->comment('Controle de concorrência otimista');
            $tabela->dateTime('atualizada_em', 3);
        });
        DB::statement('ALTER TABLE mesas MODIFY estado LONGBLOB NOT NULL');

        Schema::create('membros_mesa', function (Blueprint $tabela) {
            $tabela->string('mesa_id', 40);
            $tabela->string('usuario', 80);
            $tabela->string('situacao', 20);
            $tabela->string('usuario_ativo', 80)->nullable()->comment('Preenchido só enquanto ativo em mesa aberta: garante a mesa única');
            $tabela->primary(['mesa_id', 'usuario']);
            $tabela->unique('usuario_ativo', 'uq_membros_mesa_usuario_ativo');
            $tabela->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });

        Schema::create('participantes_locais', function (Blueprint $tabela) {
            $tabela->string('id', 80)->primary();
            $tabela->string('mesa_id', 40);
            $tabela->string('nome', 120);
            $tabela->string('email', 190);
            $tabela->string('situacao', 20);
            $tabela->string('usuario', 80)->nullable();
            $tabela->index(['email', 'situacao']);
            $tabela->index('usuario');
            $tabela->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });

        Schema::create('eventos_partida', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->string('mesa_id', 40);
            $tabela->integer('partida')->comment('Número da partida dentro da mesa, a partir de 1');
            $tabela->integer('sequencia')->comment('Posição do evento dentro da partida, a partir de 1');
            $tabela->string('tipo', 40);
            $tabela->string('origem', 20);
            $tabela->string('participante', 80)->nullable();
            $tabela->integer('rodada')->nullable();
            $tabela->dateTime('ocorrido_em', 3);
            $tabela->unique(['mesa_id', 'partida', 'sequencia'], 'uq_eventos_partida_sequencia');
            $tabela->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });

        Schema::create('convites', function (Blueprint $tabela) {
            $tabela->string('id', 40)->primary();
            $tabela->string('mesa_id', 40);
            $tabela->string('de', 80);
            $tabela->string('para', 80);
            $tabela->string('situacao', 20);
            $tabela->dateTime('criado_em', 3);
            $tabela->index(['para', 'situacao']);
            $tabela->index(['mesa_id', 'situacao']);
            $tabela->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });

        Schema::create('pedidos_amizade', function (Blueprint $tabela) {
            $tabela->string('de', 80);
            $tabela->string('para', 80)->index();
            $tabela->dateTime('criado_em', 3);
            $tabela->primary(['de', 'para']);
        });

        Schema::create('amizades', function (Blueprint $tabela) {
            $tabela->string('usuario_a', 80)->comment('Sempre o menor dos dois identificadores');
            $tabela->string('usuario_b', 80);
            $tabela->dateTime('criada_em', 3);
            $tabela->primary(['usuario_a', 'usuario_b']);
        });

        Schema::create('bloqueios', function (Blueprint $tabela) {
            $tabela->string('quem', 80);
            $tabela->string('alvo', 80);
            $tabela->dateTime('criado_em', 3);
            $tabela->primary(['quem', 'alvo']);
        });

        // Provocações: frases têm temas; momentos (lugares do sistema) aceitam temas.
        // Guia completo na nota "RANÇO - Provocações".

        Schema::create('temas_de_provocacao', function (Blueprint $tabela) {
            $tabela->string('codigo', 40)->primary();
            $tabela->string('tipo', 10)->comment('tom (frase genérica) ou contexto (frase específica de um lugar)');
            $tabela->string('significado', 255);
        });

        Schema::create('momentos_de_provocacao', function (Blueprint $tabela) {
            $tabela->string('codigo', 60)->primary();
            $tabela->string('descricao', 255);
            $tabela->string('variaveis', 255)->default('')->comment('Variáveis oferecidas às frases, separadas por vírgula');
            $tabela->string('jogo', 40)->nullable()->comment('Vazio vale para todos os jogos');
        });

        Schema::create('momentos_temas', function (Blueprint $tabela) {
            $tabela->string('momento', 60);
            $tabela->string('tema', 40);
            $tabela->primary(['momento', 'tema']);
            $tabela->foreign('momento')->references('codigo')->on('momentos_de_provocacao')->cascadeOnDelete();
            $tabela->foreign('tema')->references('codigo')->on('temas_de_provocacao')->cascadeOnDelete();
        });

        Schema::create('provocacoes', function (Blueprint $tabela) {
            $tabela->increments('id');
            $tabela->string('texto', 500)->unique()->comment('Pode usar variáveis como {nome}, {minutos}');
            $tabela->tinyInteger('intensidade')->default(1)->comment('1 leve, 2 média, 3 pesada');
            $tabela->string('perspectiva', 10)->default('pessoa')->comment('pessoa (fala com quem sofreu a ação) ou mesa (narra para todos)');
            $tabela->boolean('ativa')->default(true);
        });

        Schema::create('provocacoes_temas', function (Blueprint $tabela) {
            $tabela->unsignedInteger('provocacao_id');
            $tabela->string('tema', 40)->index();
            $tabela->primary(['provocacao_id', 'tema']);
            $tabela->foreign('provocacao_id')->references('id')->on('provocacoes')->cascadeOnDelete();
            $tabela->foreign('tema')->references('codigo')->on('temas_de_provocacao')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        foreach ([
            'provocacoes_temas', 'provocacoes', 'momentos_temas', 'momentos_de_provocacao', 'temas_de_provocacao',
            'bloqueios', 'amizades', 'pedidos_amizade', 'convites', 'eventos_partida', 'participantes_locais',
            'membros_mesa', 'mesas',
        ] as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
