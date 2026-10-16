<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Fim da mesa única: a pessoa pode estar em até 5 mesas (limite no domínio). */
    public function up(): void
    {
        Schema::table('membros_mesa', function (Blueprint $tabela) {
            $tabela->dropUnique('uq_membros_mesa_usuario_ativo');
            $tabela->index('usuario_ativo', 'ix_membros_mesa_usuario_ativo');
        });
    }

    /** Só volta se ninguém estiver em mais de uma mesa aberta. */
    public function down(): void
    {
        Schema::table('membros_mesa', function (Blueprint $tabela) {
            $tabela->dropIndex('ix_membros_mesa_usuario_ativo');
            $tabela->unique('usuario_ativo', 'uq_membros_mesa_usuario_ativo');
        });
    }
};
