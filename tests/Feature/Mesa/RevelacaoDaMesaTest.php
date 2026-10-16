<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RevelacaoDaMesaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function primeiraAberturaMarcaVistaESegundaNaoAnima(): void
    {
        [$mesa, $pessoas] = $this->preparar();
        $ana = $pessoas[0];
        $this->actingAs($ana)->get(route('dashboard'))->assertOk()->assertSee('resultado-pendente')
            ->assertViewHas('criarMesaEmDestaque', false);
        $this->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertViewHas('animar', true)
            ->assertSee('revelacao-', false)->assertSee('Ranking final')->assertSee('Rever a revelação');
        $this->assertDatabaseHas('revelacoes_vistas', ['usuario' => $ana->identificadorNoDominio(), 'mesa_id' => $mesa->id]);
        $this->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertViewHas('animar', false)
            ->assertDontSee('build/assets/revelacao-', false);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('resultado-pendente')
            ->assertViewHas('criarMesaEmDestaque', true);
        $this->actingAs($pessoas[1])->get(route('mesas.mostrar', $mesa->id))->assertViewHas('animar', true);
    }

    #[Test]
    public function reverNaoCriaNemAlteraRegistroEVistaEIdempotente(): void
    {
        [$mesa, $pessoas] = $this->preparar();
        $usuario = $pessoas[0]->identificadorNoDominio();
        $url = route('mesas.mostrar', ['id' => $mesa->id, 'rever' => 1]);
        $this->actingAs($pessoas[0])->get($url)->assertOk()->assertViewHas('animar', true);
        $this->assertDatabaseMissing('revelacoes_vistas', ['usuario' => $usuario, 'mesa_id' => $mesa->id]);
        $plataforma = app(Plataforma::class);
        $plataforma->marcarResultadoVisto($usuario, $mesa->id);
        $antes = DB::table('revelacoes_vistas')->where('usuario', $usuario)->first();
        $plataforma->marcarResultadoVisto($usuario, $mesa->id);
        $this->get($url)->assertOk()->assertViewHas('animar', true);
        $this->assertEquals([$antes], DB::table('revelacoes_vistas')->where('usuario', $usuario)->get()->all());
    }

    #[Test]
    public function semTrofeuNaoAnimaNemOfereceReverMesmoComQuery(): void
    {
        [$mesa, $pessoas] = $this->preparar(comPartida: false);
        $this->actingAs($pessoas[0])->get(route('mesas.mostrar', ['id' => $mesa->id, 'rever' => 1]))
            ->assertOk()->assertViewHas('animar', false)->assertDontSee('Rever a revelação');
        $this->get(route('dashboard'))->assertDontSee('resultado-pendente');
    }

    #[Test]
    public function saidosERemovidosRecebemPendenciaELocaisEEstranhosNao(): void
    {
        [$mesa, $pessoas, $local] = $this->preparar(encerrar: false, local: true);
        $plataforma = app(Plataforma::class);
        $plataforma->naMesa($mesa->id, static function (Mesa $mesa) use ($pessoas): void {
            $mesa->abandonar($pessoas[1]->identificadorNoDominio());
            $mesa->removerDaMesa($pessoas[2]->identificadorNoDominio(), $mesa->admin());
            $mesa->encerrar($mesa->admin());
        });
        foreach ([$pessoas[1], $pessoas[2]] as $pessoa) {
            $this->assertSame($mesa->id, $plataforma->resultadosPendentesDe($pessoa->identificadorNoDominio())[0]['id']);
            $this->actingAs($pessoa)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertViewHas('animar', true);
        }
        $this->assertSame([], $plataforma->resultadosPendentesDe($local));
        $plataforma->marcarResultadoVisto($local, $mesa->id);
        $this->assertDatabaseMissing('revelacoes_vistas', ['usuario' => $local]);
        $estranho = User::factory()->create();
        $this->actingAs($estranho)->get(route('mesas.mostrar', ['id' => $mesa->id, 'rever' => 1]))->assertNotFound();
        $this->assertSame([], $plataforma->resultadosPendentesDe($estranho->identificadorNoDominio()));
    }

    #[Test]
    public function rankingDensoIncluiQuemSaiuETrofeusEmpatados(): void
    {
        [$mesa, $pessoas] = $this->preparar(encerrar: false, empate: true);
        app(Plataforma::class)->naMesa($mesa->id, static function (Mesa $mesa) use ($pessoas): void {
            $mesa->abandonar($pessoas[1]->identificadorNoDominio());
            $mesa->encerrar($mesa->admin());
        });
        $resposta = $this->actingAs($pessoas[0])->get(route('mesas.mostrar', $mesa->id))->assertOk();
        // Conta só o ranking: o pódio agrupa empatados numa coluna só.
        $this->assertSame(2, substr_count($resposta->getContent(), '<li data-posicao="1"'));
        $this->assertSame(1, substr_count($resposta->getContent(), '<li data-posicao="2"'));
        $this->assertCount(3, $resposta->viewData('trofeus'));
        $this->assertEqualsCanonicalizing(['empate', 'empate', 'segundo'], array_column($resposta->viewData('trofeus'), 'tipo'));
        $this->assertContains('Bruno', array_column($resposta->viewData('ranking')->linhas, 'nome'));
    }

    #[Test]
    public function rankingUsaOSingularParaUmPonto(): void
    {
        [$mesa, $pessoas] = $this->preparar(encerrar: true);
        $pagina = $this->actingAs($pessoas[0])->get(route('mesas.mostrar', $mesa->id))->assertOk();

        $this->assertContains(1, array_column($pagina->viewData('ranking')->linhas, 'pontos'));
        $pagina->assertSee('1 ponto<', false)->assertDontSee('1 pontos');
    }

    #[Test]
    public function migracaoPreencheTodosOsMembrosAntigosMasNaoMesasAbertas(): void
    {
        [$antiga, $pessoas] = $this->preparar(local: true);
        [$aberta, $novas] = $this->preparar(encerrar: false);
        // RefreshDatabase ja criou a estrutura; executa o preenchimento real da migracao.
        Schema::partialMock()->shouldReceive('create')->once();
        Schema::shouldReceive('table')->once();
        $migracao = require database_path('migrations/2026_10_06_000001_criar_revelacoes_vistas.php');
        $migracao->up();
        $this->assertSame(count($antiga->membros()), DB::table('revelacoes_vistas')->where('mesa_id', $antiga->id)->count());
        $this->assertDatabaseMissing('revelacoes_vistas', ['mesa_id' => $aberta->id]);
        $this->actingAs($pessoas[0])->get(route('dashboard'))->assertDontSee('resultado-pendente');
        $this->get(route('mesas.mostrar', $antiga->id))->assertViewHas('animar', false)->assertSee('Rever a revelação');
        app(Plataforma::class)->encerrarMesa($aberta->id, $novas[0]->identificadorNoDominio());
        $this->actingAs($novas[0])->get(route('dashboard'))->assertSee('resultado-pendente');
    }

    #[Test]
    public function pendenciasOrdenadasSemLimiteENomePadraoDaMesa(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $ids = [];
        foreach (range(1, 4) as $indice) {
            [$mesa] = $this->preparar(admin: $ana);
            DB::table('mesas')->where('id', $mesa->id)->update(['encerrada_em' => '2026-10-0'.$indice.' 12:00:00.000']);
            $ids[] = $mesa->id;
        }
        $antiga = app(\App\Dominio\Plataforma\RepositorioDeMesas::class)->buscar($ids[0]);
        app(\App\Dominio\Plataforma\RepositorioDeMesas::class)->salvar($antiga);
        $resposta = $this->actingAs($ana)->get(route('dashboard'))->assertOk();
        $this->assertSame(array_reverse($ids), array_column($resposta->viewData('resultados'), 'id'));
        $this->assertSame(4, substr_count($resposta->getContent(), 'data-test="resultado-pendente"'));
        $this->assertSame('Mesa de Ana', $resposta->viewData('resultados')[0]['nome']);
    }

    private function preparar(bool $encerrar = true, bool $comPartida = true, bool $local = false, bool $empate = false, ?User $admin = null): array
    {
        $pessoas = [$admin ?? User::factory()->create(['name' => 'Ana']), User::factory()->create(['name' => 'Bruno']), User::factory()->create(['name' => 'Carla'])];
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($pessoas[0]->identificadorNoDominio());
        $localId = null;
        $mesa = $plataforma->naMesa($mesa->id, static function (Mesa $mesa) use ($pessoas, $comPartida, $local, $empate, &$localId): void {
            foreach (array_slice($pessoas, 1) as $pessoa) {
                $mesa->adicionarMembro($pessoa->identificadorNoDominio());
            }
            if ($local) {
                $localId = $mesa->cadastrarParticipanteLocal('Zeca', 'zeca@example.test', $mesa->admin());
            }
            if ($comPartida) {
                $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, 1), $mesa->admin());
                $partida->iniciar($mesa->admin());
                foreach ($mesa->membrosAtivos() as $indice => $pessoa) {
                    $vez = $partida->vezAtual();
                    $partida->iniciarTentativa($vez);
                    $partida->concluirTentativa($vez, $empate && $indice < 2 ? 5000 : 5000 + $indice * 1000);
                }
            }
        });
        if ($encerrar) {
            $plataforma->encerrarMesa($mesa->id, $mesa->admin());
        }
        return [$mesa, $pessoas, $localId];
    }
}
