<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aceites', function (Blueprint $tabela) {
            $tabela->id();
            $tabela->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $tabela->string('versao', 40);
            $tabela->timestamp('aceito_em');
            $tabela->string('ip', 45);
            $tabela->unique(['user_id', 'versao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aceites');
    }
};
