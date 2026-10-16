<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_de_acesso', function (Blueprint $tabela) {
            $tabela->id();
            // Sem FK: a retenção de seis meses continua após excluir a conta.
            $tabela->unsignedBigInteger('user_id');
            $tabela->string('ip', 45);
            $tabela->unsignedSmallInteger('porta_origem')->nullable();
            $tabela->dateTime('ocorrido_em', 3)->index();
            $tabela->index(['user_id', 'ip', 'ocorrido_em'], 'acessos_por_conta_ip_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_de_acesso');
    }
};
