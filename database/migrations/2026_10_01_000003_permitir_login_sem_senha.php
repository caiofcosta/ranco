<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas que entram só pelo Google ou por link de acesso não têm senha. O google_id liga a conta
 * Google à conta do RANÇO (o mesmo e-mail verificado é sempre a mesma conta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabela) {
            $tabela->string('password')->nullable()->change();
            $tabela->string('google_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabela) {
            $tabela->dropUnique(['google_id']);
            $tabela->dropColumn('google_id');
            $tabela->string('password')->nullable(false)->change();
        });
    }
};
