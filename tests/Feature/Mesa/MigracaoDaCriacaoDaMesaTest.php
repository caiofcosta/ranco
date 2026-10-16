<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Relogio;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

/** O backfill lê blobs gravados de verdade pela Plataforma; o que não for confiável fica NULL. */
final class MigracaoDaCriacaoDaMesaTest extends TestCase
{
    use RefreshDatabase;

    private RelogioFalso $relogio;

    private object $migracao;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-05T15:00:00+00:00'));
        $this->app->instance(Relogio::class, $this->relogio);
        $this->migracao = require database_path('migrations/2026_10_08_000006_adicionar_criada_em_as_mesas.php');
        $this->admin = User::factory()->create();
    }

    private function mesaSemCriadaEm(): string
    {
        $mesa = app(Plataforma::class)->criarMesa($this->admin->identificadorNoDominio());
        DB::table('mesas')->where('id', $mesa->id)->update(['criada_em' => null]);

        return $mesa->id;
    }

    private function criadaEm(string $id): ?string
    {
        return DB::table('mesas')->where('id', $id)->value('criada_em');
    }

    #[Test]
    public function aColunaFicaNulavelEIndexada(): void
    {
        $coluna = collect(Schema::getColumns('mesas'))->firstWhere('name', 'criada_em');

        $this->assertNotNull($coluna);
        $this->assertTrue($coluna['nullable']);
        $this->assertTrue(Schema::hasIndex('mesas', ['criada_em']));
    }

    #[Test]
    public function preencheACriacaoDeUmaMesaGravadaPelaPlataforma(): void
    {
        $id = $this->mesaSemCriadaEm();

        $this->assertSame(1, $this->migracao->preencherCriadaEm());
        $this->assertSame('2026-10-05 15:00:00.000', $this->criadaEm($id));
    }

    #[Test]
    public function usaADataDaMesaEnaoADaPartidaGuardadaNoEstado(): void
    {
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($this->admin->identificadorNoDominio());
        $this->relogio->avancar(3600);
        $plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', null, null, 1), $this->admin->identificadorNoDominio());
        DB::table('mesas')->where('id', $mesa->id)->update(['criada_em' => null]);

        $this->migracao->preencherCriadaEm();

        $this->assertSame('2026-10-05 15:00:00.000', $this->criadaEm($mesa->id));
    }

    #[Test]
    public function mesaAntigaSemDataNoEstadoFicaNula(): void
    {
        $id = $this->mesaSemCriadaEm();
        $estado = (string) DB::table('mesas')->where('id', $id)->value('estado');
        $antigo = preg_replace('/s:8:"criadaEm";O:17:"DateTimeImmutable":3:\{[^}]*\}/', 's:8:"criadaEm";N;', $estado, 1);
        $this->assertNotSame($estado, $antigo, 'O estado de teste deveria ter a data da mesa.');
        DB::table('mesas')->where('id', $id)->update(['estado' => $antigo]);

        $this->assertSame(0, $this->migracao->preencherCriadaEm());
        $this->assertNull($this->criadaEm($id));
    }

    #[Test]
    public function estadoIlegivelOuQueNaoEUmaMesaFicaNuloSemInterromper(): void
    {
        $estados = ['isso não é uma mesa serializada', serialize(['criadaEm' => new DateTimeImmutable('2026-10-05 10:00:00')]), serialize(new stdClass())];
        // Todas as mesas nascem antes de qualquer estado ser corrompido: criar mesa relê as abertas do admin.
        $ids = array_map(fn (): string => $this->mesaSemCriadaEm(), $estados);
        $valida = $this->mesaSemCriadaEm();
        foreach ($estados as $indice => $estado) {
            DB::table('mesas')->where('id', $ids[$indice])->update(['estado' => $estado]);
        }

        $this->assertSame(1, $this->migracao->preencherCriadaEm());
        foreach ($ids as $id) {
            $this->assertNull($this->criadaEm($id));
        }
        $this->assertSame('2026-10-05 15:00:00.000', $this->criadaEm($valida));
    }

    #[Test]
    public function dataPosteriorAUltimaGravacaoEImpossivelEFicaNula(): void
    {
        $id = $this->mesaSemCriadaEm();
        DB::table('mesas')->where('id', $id)->update(['atualizada_em' => '2026-10-05 14:00:00.000']); // antes das 15:00

        $this->assertSame(0, $this->migracao->preencherCriadaEm());
        $this->assertNull($this->criadaEm($id));
    }

    #[Test]
    public function naoSobrescreveCriadaEmJaPreenchida(): void
    {
        $id = $this->mesaSemCriadaEm();
        DB::table('mesas')->where('id', $id)->update(['criada_em' => '2026-01-01 00:00:00.000']);

        $this->assertSame(0, $this->migracao->preencherCriadaEm());
        $this->assertSame('2026-01-01 00:00:00.000', $this->criadaEm($id));
    }
}
