<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convites de amizade enviados por e-mail. O código do link é guardado como hash; quem abre o link
 * vira amizade de quem convidou. Serve também para quem ainda não tem conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convites_de_amizade', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->string('de', 80)->comment('Quem convidou (identificador no domínio)');
            $tabela->string('email', 190)->comment('E-mail convidado, em minúsculas');
            $tabela->char('codigo_hash', 64)->unique()->comment('sha256 do código do link');
            $tabela->timestamp('expira_em');
            $tabela->timestamp('aceito_em')->nullable();
            $tabela->string('aceito_por', 80)->nullable()->comment('Quem aceitou (identificador no domínio)');
            $tabela->timestamp('recusado_em')->nullable();
            $tabela->timestamps();

            $tabela->index('de');
            $tabela->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites_de_amizade');
    }
};
