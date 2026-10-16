<?php

declare(strict_types=1);

namespace Tests\Feature\Amizade;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MigracaoDeConvitesPorContaTest extends TestCase
{
    #[Test]
    public function converte_pendentes_existentes_e_apaga_emails_sem_conta(): void
    {
        $temporarias = [];
        $esquema = Schema::getFacadeRoot();
        try {
            DB::statement('CREATE TEMPORARY TABLE users (id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(190))');
            $temporarias[] = 'users';
            DB::statement(<<<'SQL'
                CREATE TEMPORARY TABLE convites_de_amizade (
                    id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(190), codigo_hash CHAR(64),
                    aceito_por VARCHAR(80) NULL, aceito_em DATETIME NULL,
                    recusado_em DATETIME NULL, expira_em DATETIME,
                    KEY convites_de_amizade_email_index (email),
                    UNIQUE KEY convites_de_amizade_codigo_hash_unique (codigo_hash)
                )
                SQL);
            $temporarias[] = 'convites_de_amizade';
            DB::table('users')->insert(['id' => 7, 'email' => 'ana@exemplo.com']);
            foreach (['Ana@Exemplo.com', 'sem-conta@exemplo.com'] as $indice => $email) {
                DB::table('convites_de_amizade')->insert(['id' => $indice + 1, 'email' => $email, 'codigo_hash' => hash('sha256', $email), 'expira_em' => '2026-11-01 00:00:00']);
            }
            // MariaDB não admite FK em tabela temporária. Só a declaração da FK é
            // omitida aqui; o esquema real é exercitado pelos testes com RefreshDatabase.
            $temporario = DB::connection()->getSchemaBuilder();
            $temporario->blueprintResolver(fn ($conexao, $tabela, $callback) => new class($conexao, $tabela, $callback) extends Blueprint
            {
                public function foreign($columns, $name = null)
                {
                    return new ForeignKeyDefinition;
                }
            });
            Schema::swap($temporario);
            Log::spy();
            $migracao = require database_path('migrations/2026_10_08_000004_convites_de_amizade_por_conta.php');
            $migracao->up();
            $convite = DB::table('convites_de_amizade')->sole();
            $this->assertSame(1, (int) $convite->id);
            $this->assertSame(7, (int) $convite->para);
            $this->assertFalse(property_exists($convite, 'email'));
            $this->assertFalse(property_exists($convite, 'codigo_hash'));
            Log::shouldHaveReceived('info')->with('Convites de amizade migrados para contas.', ['migrados' => 1, 'apagados' => 1])->once();
        } finally {
            Schema::swap($esquema);
            foreach (array_reverse($temporarias) as $tabela) {
                DB::statement('DROP TEMPORARY TABLE IF EXISTS '.$tabela);
            }
        }
    }
}
