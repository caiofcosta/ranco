<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class MigracaoDeMedalhasPorColocacaoTest extends TestCase
{
    #[Test]
    public function preencheMedalhasAntigasEVoltaSemApagarAMedalhaQuandoHaUmaPorPartida(): void
    {
        $temporarias = [];
        try {
            $this->tabelasTemporarias($temporarias);
            DB::table('medalhas')->insert([
                'id' => 1,
                'pessoa' => 'ana',
                'mesa_id' => 'mesa',
                'mesa_nome' => 'Grupo',
                'partida' => 1,
                'frase' => 'Frase antiga.',
                'concedida_em' => '2026-10-01 01:02:03.456',
            ]);

            $migracao = require database_path('migrations/2026_10_03_000003_medalhas_por_colocacao.php');
            $migracao->up();

            $medalha = DB::table('medalhas')->first();
            $this->assertSame(1, (int) $medalha->colocacao);
            $this->assertSame('labirinto', $medalha->jogo);
            $this->assertSame(0, (int) $medalha->empate);
            $this->assertSame('Frase antiga.', $medalha->frase);
            $this->assertSame('outro', DB::table('momentos_de_provocacao')->where('codigo', 'partida.primeiro_lugar_empate')->value('variaveis'));

            $migracao->down();

            $depois = DB::table('medalhas')->first();
            $this->assertSame('ana', $depois->pessoa);
            $this->assertSame('Frase antiga.', $depois->frase);
            $this->assertSame('2026-10-01 01:02:03.456', $depois->concedida_em);
            $this->assertFalse(isset($depois->colocacao));
            $this->assertSame(0, DB::table('momentos_de_provocacao')->where('codigo', 'partida.terceiro_lugar')->count());
        } finally {
            foreach (array_reverse($temporarias) as $tabela) {
                DB::statement('DROP TEMPORARY TABLE IF EXISTS '.$tabela);
            }
        }
    }

    #[Test]
    public function voltaRecusaQuandoAMesmaPartidaJaTemDuasMedalhas(): void
    {
        $temporarias = [];
        try {
            $this->tabelasTemporarias($temporarias);
            DB::table('medalhas')->insert([
                'id' => 1,
                'pessoa' => 'ana',
                'mesa_id' => 'mesa',
                'mesa_nome' => 'Grupo',
                'partida' => 1,
                'frase' => 'Ouro.',
                'concedida_em' => '2026-10-01 01:02:03.456',
            ]);

            $migracao = require database_path('migrations/2026_10_03_000003_medalhas_por_colocacao.php');
            $migracao->up();
            DB::table('medalhas')->insert([
                'id' => 2,
                'pessoa' => 'bruno',
                'mesa_id' => 'mesa',
                'mesa_nome' => 'Grupo',
                'partida' => 1,
                'colocacao' => 2,
                'empate' => false,
                'jogo' => 'labirinto',
                'frase' => 'Prata.',
                'concedida_em' => '2026-10-01 01:02:03.456',
            ]);

            try {
                $migracao->down();
                $this->fail('A volta deveria recusar duas medalhas na mesma partida.');
            } catch (RuntimeException $erro) {
                $this->assertStringContainsString('Não dá para voltar', $erro->getMessage());
            }

            $this->assertSame(2, DB::table('medalhas')->count());
            $this->assertSame(1, (int) DB::table('medalhas')->where('pessoa', 'ana')->value('colocacao'));
            $this->assertSame('Prata.', DB::table('medalhas')->where('pessoa', 'bruno')->value('frase'));
        } finally {
            foreach (array_reverse($temporarias) as $tabela) {
                DB::statement('DROP TEMPORARY TABLE IF EXISTS '.$tabela);
            }
        }
    }

    /** @param list<string> $temporarias */
    private function tabelasTemporarias(array &$temporarias): void
    {
        DB::statement(<<<'SQL'
            CREATE TEMPORARY TABLE medalhas (
                id BIGINT UNSIGNED PRIMARY KEY,
                pessoa VARCHAR(80) NOT NULL,
                mesa_id VARCHAR(40) NOT NULL,
                mesa_nome VARCHAR(300) NOT NULL,
                partida INT UNSIGNED NOT NULL,
                frase TEXT NULL,
                concedida_em DATETIME(3) NOT NULL,
                UNIQUE KEY medalhas_mesa_id_partida_unique (mesa_id, partida)
            )
            SQL);
        $temporarias[] = 'medalhas';
        DB::statement(<<<'SQL'
            CREATE TEMPORARY TABLE momentos_de_provocacao (
                codigo VARCHAR(60) PRIMARY KEY,
                descricao VARCHAR(255) NOT NULL,
                variaveis VARCHAR(255) NOT NULL DEFAULT '',
                jogo VARCHAR(40) NULL
            )
            SQL);
        $temporarias[] = 'momentos_de_provocacao';
    }
}
