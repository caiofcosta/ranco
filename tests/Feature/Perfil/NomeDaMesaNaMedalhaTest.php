<?php

declare(strict_types=1);

namespace Tests\Feature\Perfil;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NomeDaMesaNaMedalhaTest extends TestCase
{
    #[Test]
    public function migracaoPreencheMedalhasAntigasComONomeAtualSemMudarFraseOuData(): void
    {
        // Tabelas temporárias desta conexão ocultam as permanentes só durante este teste.
        // Nenhum DDL da migração é aplicado às tabelas compartilhadas.
        $temporarias = [];
        try {
            DB::statement('CREATE TEMPORARY TABLE users (id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(255))');
            $temporarias[] = 'users';
            DB::statement('CREATE TEMPORARY TABLE mesas (id VARCHAR(40) PRIMARY KEY, admin VARCHAR(80), nome VARCHAR(40))');
            $temporarias[] = 'mesas';
            DB::statement('CREATE TEMPORARY TABLE medalhas (id BIGINT UNSIGNED PRIMARY KEY, mesa_id VARCHAR(40), frase TEXT, concedida_em DATETIME(3))');
            $temporarias[] = 'medalhas';
            DB::table('users')->insert(['id' => 1, 'name' => 'Ana']);
            DB::table('mesas')->insert([
                ['id' => 'nomeada', 'admin' => '1', 'nome' => 'Nome atual'],
                ['id' => 'sem-nome', 'admin' => '1', 'nome' => null],
                ['id' => 'sem-admin', 'admin' => '2', 'nome' => ''],
            ]);
            foreach (['nomeada', 'sem-nome', 'sem-admin'] as $indice => $mesa) {
                DB::table('medalhas')->insert([
                    'id' => $indice + 1,
                    'mesa_id' => $mesa,
                    'frase' => 'Frase antiga.',
                    'concedida_em' => '2026-10-01 01:02:03.456',
                ]);
            }

            $migracao = require database_path('migrations/2026_10_03_000001_gravar_nome_da_mesa_nas_medalhas.php');
            $migracao->up();
            $this->assertSame(['Nome atual', 'Mesa de Ana', 'Mesa'], DB::table('medalhas')->orderBy('id')->pluck('mesa_nome')->all());
            foreach (DB::table('medalhas')->get() as $medalha) {
                $this->assertSame('Frase antiga.', $medalha->frase);
                $this->assertSame('2026-10-01 01:02:03.456', $medalha->concedida_em);
            }
        } finally {
            foreach (array_reverse($temporarias) as $tabela) {
                DB::statement('DROP TEMPORARY TABLE IF EXISTS '.$tabela);
            }
        }
    }
}
