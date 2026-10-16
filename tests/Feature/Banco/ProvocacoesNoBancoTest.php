<?php

declare(strict_types=1);

namespace Tests\Feature\Banco;

use App\Dominio\Provocacao\Momento;
use App\Dominio\Provocacao\Perspectiva;
use App\Dominio\Provocacao\Provocador;
use App\Infra\Banco\PdoRepositorioDeProvocacoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Garante que os momentos do código e os da tabela andam juntos e que nenhum fica sem frase. */
final class ProvocacoesNoBancoTest extends TestCase
{
    use RefreshDatabase;

    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DB::connection()->getPdo();
    }

    #[Test]
    public function todoMomentoDoCodigoEstaCadastradoEOsDaTabelaExistemNoCodigo(): void
    {
        $naTabela = $this->pdo->query('SELECT codigo FROM momentos_de_provocacao ORDER BY codigo')->fetchAll(PDO::FETCH_COLUMN);
        $noCodigo = array_map(static fn (Momento $m): string => $m->value, Momento::cases());
        sort($noCodigo);

        $this->assertSame($noCodigo, $naTabela);
    }

    #[Test]
    public function todoMomentoTemPeloMenosUmaFraseUtilizavelComAsVariaveisQueOferece(): void
    {
        $provocador = new Provocador(new PdoRepositorioDeProvocacoes($this->pdo));
        $variaveis = $this->pdo->query('SELECT codigo, variaveis FROM momentos_de_provocacao')->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach (Momento::cases() as $momento) {
            $oferecidas = array_filter(explode(',', (string) $variaveis[$momento->value]));
            $valores = array_fill_keys($oferecidas, 'X');

            $frases = array_filter(array_map(
                static fn (Perspectiva $perspectiva): ?string => $provocador->sortear($momento, 2, $valores, $perspectiva),
                Perspectiva::cases(),
            ));

            $this->assertNotSame([], $frases, "O momento {$momento->value} ficou sem frase.");
            foreach ($frases as $frase) {
                $this->assertStringNotContainsString('{', $frase, "Variável sem valor em {$momento->value}.");
            }
        }
    }
}
