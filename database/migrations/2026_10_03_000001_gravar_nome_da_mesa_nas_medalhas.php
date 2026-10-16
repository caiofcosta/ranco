<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medalhas', function (Blueprint $tabela) {
            // Também comporta "Mesa de " seguido do nome completo da conta (255 caracteres).
            $tabela->string('mesa_nome', 300)->nullable()->after('mesa_id');
        });

        // O nome na concessão não existe nos registros antigos. Preserva o nome atual,
        // com os mesmos nomes de exibição de NomeDaMesa::de, sem alterar frase ou data.
        DB::statement(<<<'SQL'
            UPDATE medalhas AS medalha
            LEFT JOIN mesas AS mesa ON mesa.id = medalha.mesa_id
            LEFT JOIN users AS admin ON CAST(admin.id AS CHAR) = mesa.admin
            SET medalha.mesa_nome = CASE
                WHEN mesa.nome IS NOT NULL AND mesa.nome <> '' THEN mesa.nome
                WHEN admin.name IS NOT NULL AND admin.name <> '' THEN CONCAT('Mesa de ', admin.name)
                ELSE 'Mesa'
            END
            WHERE medalha.mesa_nome IS NULL
            SQL);

        Schema::table('medalhas', function (Blueprint $tabela) {
            $tabela->string('mesa_nome', 300)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('medalhas', function (Blueprint $tabela) {
            $tabela->dropColumn('mesa_nome');
        });
    }
};
