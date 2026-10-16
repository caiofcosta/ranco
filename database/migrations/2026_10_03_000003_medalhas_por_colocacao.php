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
            $tabela->unsignedInteger('colocacao')->nullable()->after('partida');
            $tabela->boolean('empate')->default(false)->after('colocacao');
            $tabela->string('jogo', 40)->nullable()->after('empate');
        });

        DB::table('medalhas')->whereNull('colocacao')->update([
            'colocacao' => 1,
            'jogo' => 'labirinto',
        ]);

        DB::statement('ALTER TABLE medalhas MODIFY colocacao INT UNSIGNED NOT NULL, MODIFY jogo VARCHAR(40) NOT NULL');

        $chave = $this->soltarChaveDaMesa();
        DB::statement('ALTER TABLE medalhas DROP INDEX medalhas_mesa_id_partida_unique');
        DB::statement('ALTER TABLE medalhas ADD UNIQUE medalhas_mesa_id_partida_pessoa_unique (mesa_id, partida, pessoa)');
        $this->religarChaveDaMesa($chave);

        foreach ([
            ['partida.terceiro_lugar', 'Terminou a partida em terceiro lugar', 'vencedor'],
            ['partida.primeiro_lugar_empate', 'Empatou em primeiro lugar', 'outro'],
            ['partida.segundo_lugar_empate', 'Empatou em segundo lugar', 'outro'],
            ['partida.terceiro_lugar_empate', 'Empatou em terceiro lugar', 'outro'],
        ] as [$codigo, $descricao, $variaveis]) {
            DB::table('momentos_de_provocacao')->insertOrIgnore([
                'codigo' => $codigo,
                'descricao' => $descricao,
                'variaveis' => $variaveis,
            ]);
        }
    }

    public function down(): void
    {
        $duplicada = DB::table('medalhas')
            ->select('mesa_id')
            ->groupBy('mesa_id', 'partida')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicada) {
            throw new \RuntimeException('Não dá para voltar: há mais de uma medalha na mesma partida.');
        }

        $chave = $this->soltarChaveDaMesa();
        DB::statement('ALTER TABLE medalhas DROP INDEX medalhas_mesa_id_partida_pessoa_unique');
        DB::statement('ALTER TABLE medalhas ADD UNIQUE medalhas_mesa_id_partida_unique (mesa_id, partida)');
        $this->religarChaveDaMesa($chave);
        DB::statement('ALTER TABLE medalhas DROP COLUMN colocacao, DROP COLUMN empate, DROP COLUMN jogo');

        DB::table('momentos_de_provocacao')->whereIn('codigo', [
            'partida.terceiro_lugar',
            'partida.primeiro_lugar_empate',
            'partida.segundo_lugar_empate',
            'partida.terceiro_lugar_empate',
        ])->delete();
    }

    /** A chave de mesa usa o índice único antigo. Soltá-la permite trocar o índice. */
    private function soltarChaveDaMesa(): ?string
    {
        $definicao = DB::selectOne('SHOW CREATE TABLE medalhas');
        $criar = (string) ($definicao->{'Create Table'} ?? '');
        if (!preg_match('/CONSTRAINT `([^`]+)` FOREIGN KEY \(`mesa_id`\)/', $criar, $encontrado)) {
            return null;
        }

        DB::statement('ALTER TABLE medalhas DROP FOREIGN KEY `'.$encontrado[1].'`');

        return $encontrado[1];
    }

    private function religarChaveDaMesa(?string $nome): void
    {
        if ($nome === null) {
            return;
        }

        DB::statement('ALTER TABLE medalhas ADD CONSTRAINT `'.$nome.'` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`) ON DELETE CASCADE');
    }
};
