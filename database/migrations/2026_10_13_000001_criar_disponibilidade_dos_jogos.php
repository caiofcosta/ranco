<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jogos', function (Blueprint $tabela): void {
            $tabela->string('id', 40)->primary();
            $tabela->boolean('ativo')->default(true);
        });

        DB::table('jogos')->insert(array_map(
            static fn (string $id): array => ['id' => $id, 'ativo' => true],
            ['labirinto'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('jogos');
    }
};
