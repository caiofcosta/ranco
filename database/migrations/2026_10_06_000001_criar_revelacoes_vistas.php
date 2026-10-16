<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $tabela) {
            $tabela->dateTime('encerrada_em', 3)->nullable()->index();
        });
        DB::table('mesas')->where('situacao', 'encerrada')
            ->update(['encerrada_em' => DB::raw('atualizada_em')]);

        Schema::create('revelacoes_vistas', function (Blueprint $tabela) {
            $tabela->string('usuario', 80);
            $tabela->string('mesa_id', 40);
            $tabela->dateTime('visto_em', 3);
            $tabela->unique(['usuario', 'mesa_id']);
            $tabela->foreign('mesa_id')->references('id')->on('mesas')->cascadeOnDelete();
        });

        DB::table('revelacoes_vistas')->insertUsing(['usuario', 'mesa_id', 'visto_em'],
            DB::table('membros_mesa')->join('mesas', 'mesas.id', '=', 'membros_mesa.mesa_id')
                ->where('mesas.situacao', 'encerrada')
                ->select('membros_mesa.usuario', 'membros_mesa.mesa_id')
                ->selectRaw('UTC_TIMESTAMP(3)')
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('revelacoes_vistas');
        Schema::table('mesas', function (Blueprint $tabela) {
            $tabela->dropColumn('encerrada_em');
        });
    }
};
