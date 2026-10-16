<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medalhas', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->string('pessoa', 80);
            $tabela->string('mesa_id', 40);
            $tabela->unsignedInteger('partida')->comment('Número da partida na mesa, a partir de 1');
            $tabela->text('frase')->nullable();
            $tabela->dateTime('concedida_em', 3);
            $tabela->unique(['mesa_id', 'partida']);
            $tabela->index('pessoa');
            $tabela->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });

        foreach ([
            'Ganhou no W.O. A mesa desistiu antes de você.',
            'Os outros saíram. A medalha ficou.',
        ] as $frase) {
            $id = DB::table('provocacoes')->insertGetId([
                'texto' => $frase,
                'intensidade' => 1,
                'perspectiva' => 'pessoa',
            ]);
            DB::table('provocacoes_temas')->insert(['provocacao_id' => $id, 'tema' => 'desistencia']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('medalhas');
        DB::table('provocacoes')->whereIn('texto', [
            'Ganhou no W.O. A mesa desistiu antes de você.',
            'Os outros saíram. A medalha ficou.',
        ])->delete();
    }
};
