<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Conquista\Medalhas;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\MotivoEncerramento;
use App\Dominio\Plataforma\Plataforma;
use App\Mesa\RankingDaMesa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MedalhaERankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function vencerConcedeAMedalhaUmaVezSo(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComAnaEBruno();
        $this->vencerCom($mesa, $ana, 10000, $bruno, 20000);

        $medalhas = DB::table('medalhas')->where('mesa_id', $mesa->id)->orderBy('colocacao')->get();
        $this->assertCount(2, $medalhas);
        $this->assertSame($ana->identificadorNoDominio(), $medalhas[0]->pessoa);
        $this->assertSame(1, (int) $medalhas[0]->colocacao);
        $this->assertSame(0, (int) $medalhas[0]->empate);
        $this->assertSame('labirinto', $medalhas[0]->jogo);
        $this->assertContains($medalhas[0]->frase, [
            'Ganhou. Agora tenta disfarçar o sorriso.',
            'Venceu. A mesa inteira vai lembrar disso, e você também.',
            'Ganhou. Agora vai ouvir que foi sorte.',
        ]);
        $this->assertStringNotContainsString('{', (string) $medalhas[0]->frase);
        $this->assertSame($bruno->identificadorNoDominio(), $medalhas[1]->pessoa);
        $this->assertSame(2, (int) $medalhas[1]->colocacao);

        app(Plataforma::class)->mesa($mesa->id);
        app(Plataforma::class)->renomearMesa($mesa->id, 'Grupo novo', $ana->identificadorNoDominio());

        $this->assertSame(2, Medalhas::quantidade($mesa->id));
    }

    #[Test]
    public function vitoriaPorWoTambemConcedeMedalha(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComAnaEBruno();
        $this->abrir($mesa, $ana, 1);
        $id = DB::table('provocacoes')->insertGetId([
            'texto' => 'Frase só do W.O.',
            'intensidade' => 2,
            'perspectiva' => 'pessoa',
        ]);
        DB::table('provocacoes_temas')->insert(['provocacao_id' => $id, 'tema' => 'desistencia']);
        app(Plataforma::class)->naMesa(
            $mesa->id,
            static fn (Mesa $m) => $m->partidaAtiva()->abandonar($bruno->identificadorNoDominio()),
        );

        $partida = app(Plataforma::class)->mesa($mesa->id)->partidas()[0];
        $this->assertSame(MotivoEncerramento::Desistencia, $partida->motivoEncerramento());
        $this->assertSame($ana->identificadorNoDominio(), $partida->vencedor());

        $medalha = DB::table('medalhas')->where('mesa_id', $mesa->id)->first();
        $this->assertNotNull($medalha);
        $this->assertSame($ana->identificadorNoDominio(), $medalha->pessoa);
        $this->assertSame(1, (int) $medalha->colocacao);
        $this->assertSame(0, (int) $medalha->empate);
        $this->assertSame('labirinto', $medalha->jogo);
        $this->assertSame('Frase só do W.O.', $medalha->frase);
        $this->assertSame(1, DB::table('medalhas')->where('mesa_id', $mesa->id)->count());
    }

    #[Test]
    public function semVencedorNaoMedalhaNemPontua(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $encerrada] = $this->mesaComAnaEBruno();
        $this->abrir($encerrada, $ana, 1);
        app(Plataforma::class)->encerrarPartida($encerrada->id, $ana->identificadorNoDominio());

        ['ana' => $anaRemocao, 'mesa' => $removida] = $this->mesaComAnaEBruno();
        $this->abrir($removida, $anaRemocao, 1);
        app(Plataforma::class)->removerDaPartida($removida->id, $this->outroDe($removida, $anaRemocao), $anaRemocao->identificadorNoDominio());

        ['ana' => $anaMesa, 'mesa' => $fechada] = $this->mesaComAnaEBruno();
        $this->abrir($fechada, $anaMesa, 1);
        app(Plataforma::class)->encerrarMesa($fechada->id, $anaMesa->identificadorNoDominio());

        $this->assertSame(0, Medalhas::quantidade($encerrada->id));
        $this->assertSame(0, Medalhas::quantidade($removida->id));
        $this->assertSame(0, Medalhas::quantidade($fechada->id));
        $this->assertSame([], RankingDaMesa::pontos(app(Plataforma::class)->mesa($encerrada->id)));
        $this->assertSame([], RankingDaMesa::pontos(app(Plataforma::class)->mesa($removida->id)));
        $this->assertSame([], RankingDaMesa::pontos(app(Plataforma::class)->mesa($fechada->id)));
    }

    #[Test]
    public function aTelaMostraAFraseEAPontuacaoDepoisDeCadaPartida(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComAnaEBruno();
        $this->vencerCom($mesa, $ana, 10000, $bruno, 20000);
        $frase = (string) DB::table('medalhas')->where('mesa_id', $mesa->id)->where('colocacao', 1)->value('frase');

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Ana venceu.', false)
            ->assertDontSee($frase, false)
            ->assertSee('data-test="ranking-da-mesa"', false)
            ->assertSee('Ana', false)
            ->assertSee('3', false);
        // A frase fica só na modal do resultado.
        $this->get(route('mesas.mostrar', ['id' => $mesa->id, 'resultado' => 1]))
            ->assertOk()
            ->assertSee('data-test="modal-resultado-partida"', false)
            ->assertSee($frase, false);

        $this->vencerCom($mesa, $bruno, 10000, $ana, 30000);
        $frase = (string) DB::table('medalhas')->where('mesa_id', $mesa->id)->where('partida', 2)->where('colocacao', 1)->value('frase');

        $this->actingAs($ana)->get(route('mesas.mostrar', ['id' => $mesa->id, 'resultado' => 2]))
            ->assertOk()
            ->assertSee('Bruno venceu.', false)
            ->assertSee($frase, false)
            ->assertSee('5', false);
        $this->assertSame(4, Medalhas::quantidade($mesa->id));
        $this->assertSame([
            $ana->identificadorNoDominio() => 5,
            $bruno->identificadorNoDominio() => 5,
        ], RankingDaMesa::pontos(app(Plataforma::class)->mesa($mesa->id)));
    }

    #[Test]
    public function resultadosDaMesaMostramColocacaoNoRankingEVencedorNoHistorico(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaComAnaEBruno();
        $this->vencerCom($mesa, $ana, 10000, $bruno, 20000);

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSeeInOrder(['data-test="ranking-da-mesa"', '1º', 'Ana', '2º', 'Bruno'], false)
            ->assertSeeInOrder(['data-test="historico-da-mesa"', 'Partida 1 · Labirinto', 'Vencedor:', 'Ana'], false);
    }

    #[Test]
    public function participanteLocalRecebeMedalhaEPontua(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $local = '';
        $plataforma->naMesa($mesa->id, function (Mesa $m) use ($ana, &$local): void {
            $local = $m->cadastrarParticipanteLocal('Duda', 'duda@example.test', $ana->identificadorNoDominio());
        });
        $this->abrir($mesa, $ana, 1);
        $this->jogar($mesa->id, [
            $ana->identificadorNoDominio() => 90000,
            $local => 40000,
        ]);

        $medalha = DB::table('medalhas')->where('mesa_id', $mesa->id)->where('pessoa', $local)->first();
        $this->assertSame($local, $medalha->pessoa);
        $this->assertSame(1, (int) $medalha->colocacao);
        $this->assertNotNull($medalha->frase);

        $pontos = RankingDaMesa::pontos($plataforma->mesa($mesa->id));
        $this->assertSame(3, $pontos[$local]);
        $this->assertSame(2, $pontos[$ana->identificadorNoDominio()]);

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Duda venceu.', false)
            ->assertSee('Duda', false);
    }

    /** @return array{ana: User, bruno: User, mesa: Mesa} */
    private function mesaComAnaEBruno(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));

        return compact('ana', 'bruno', 'mesa');
    }

    private function abrir(Mesa $mesa, User $admin, int $limiteRodadas): void
    {
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida(
            $mesa->id,
            CatalogoDeJogos::configuracao('labirinto', null, null, $limiteRodadas),
            $admin->identificadorNoDominio(),
        );
        $plataforma->iniciarPartida($mesa->id, $admin->identificadorNoDominio());
    }

    private function vencerCom(Mesa $mesa, User $vencedor, int $tempoVencedor, User $outro, int $tempoOutro): void
    {
        $this->abrir($mesa, $this->adminDa($mesa), 1);
        $tempos = [
            $vencedor->identificadorNoDominio() => $tempoVencedor,
            $outro->identificadorNoDominio() => $tempoOutro,
        ];
        $this->jogar($mesa->id, $tempos);
    }

    /** @param array<string, int> $tempos */
    private function jogar(string $mesaId, array $tempos): void
    {
        app(Plataforma::class)->naMesa($mesaId, static function (Mesa $mesa) use ($tempos): void {
            $partida = $mesa->partidaAtiva();
            $rodada = $partida->rodadaAtual();
            while (
                $partida->situacao()->value === 'em_andamento'
                && $partida->rodadaAtual() === $rodada
                && $partida->vezAtual() !== null
            ) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });
    }

    private function adminDa(Mesa $mesa): User
    {
        return User::query()->findOrFail($mesa->admin());
    }

    private function outroDe(Mesa $mesa, User $admin): string
    {
        foreach (app(Plataforma::class)->mesa($mesa->id)->membrosAtivos() as $id) {
            if ($id !== $admin->identificadorNoDominio()) {
                return $id;
            }
        }

        $this->fail('A mesa não tem o outro jogador.');
    }
}
