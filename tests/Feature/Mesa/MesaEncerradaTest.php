<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MesaEncerradaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function consultaContinuaExigindoLoginEEmailVerificado(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        app(Plataforma::class)->naMesa(
            $mesa->id,
            fn (Mesa $mesa) => $mesa->encerrar($ana->identificadorNoDominio()),
        );

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertRedirect(route('login'));

        $ana->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function mesaAbertaMantemATelaEOAcessoSomenteParaMembrosAtivos(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $estranho = User::factory()->create();

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()->assertViewIs('mesa.mostrar')
            ->assertSee('Renomear')->assertSee('Jogar neste aparelho')->assertDontSee('Adicionar participante local');

        $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()->assertViewIs('mesa.mostrar')
            ->assertDontSee('Jogar neste aparelho');

        $this->actingAs($estranho)->get(route('mesas.mostrar', $mesa->id))
            ->assertNotFound();

        app(Plataforma::class)->abandonarMesa(
            $mesa->id,
            $bruno->identificadorNoDominio(),
        );
        $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))
            ->assertNotFound();

        app(Plataforma::class)->naMesa(
            $mesa->id,
            function (Mesa $mesa) use ($ana, $bruno): void {
                $mesa->adicionarMembro($bruno->identificadorNoDominio());
                $mesa->removerDaMesa(
                    $bruno->identificadorNoDominio(),
                    $ana->identificadorNoDominio(),
                );
            },
        );
        $this->get(route('mesas.mostrar', $mesa->id))->assertNotFound();
    }

    #[Test]
    public function mesaEncerradaPodeSerVistaPorAtivosAbandonadosERemovidos(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $carla = User::factory()->create(['name' => 'Carla']);
        $davi = User::factory()->create(['name' => 'Davi']);
        $estranho = User::factory()->create();

        app(Plataforma::class)->naMesa(
            $mesa->id,
            function (Mesa $mesa) use ($ana, $carla, $davi): void {
                $mesa->adicionarMembro($carla->identificadorNoDominio());
                $mesa->adicionarMembro($davi->identificadorNoDominio());
                $mesa->abandonar($carla->identificadorNoDominio());
                $mesa->removerDaMesa(
                    $davi->identificadorNoDominio(),
                    $ana->identificadorNoDominio(),
                );
                $mesa->encerrar($ana->identificadorNoDominio());
            },
        );

        foreach ([$ana, $bruno, $carla, $davi] as $usuario) {
            $this->actingAs($usuario)->get(route('mesas.mostrar', $mesa->id))
                ->assertOk()->assertViewIs('mesa.encerrada')
                ->assertSee('Mesa encerrada')->assertSee('Grupo')
                ->assertSee('Carla')->assertSee('Saiu da mesa')
                ->assertSee('Davi')->assertSee('Removido da mesa')
                ->assertSee('Nenhuma partida nesta mesa.');
        }

        $this->actingAs($estranho)->get(route('mesas.mostrar', $mesa->id))
            ->assertNotFound();
        $this->actingAs($ana)->get(route('mesas.mostrar', 'inexistente'))
            ->assertNotFound();
    }

    #[Test]
    public function abandonoDoAdminTambemPermiteConsultarOResumo(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        app(Plataforma::class)->abandonarMesa(
            $mesa->id,
            $ana->identificadorNoDominio(),
        );

        foreach ([$ana, $bruno] as $usuario) {
            $this->actingAs($usuario)->get(route('mesas.mostrar', $mesa->id))
                ->assertOk()->assertViewIs('mesa.encerrada')
                ->assertSee('Ana')->assertSee('Saiu da mesa');
        }
    }

    #[Test]
    public function resumoMostraTodasAsPartidasComPlacaresEVencedorLocal(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();

        app(Plataforma::class)->naMesa(
            $mesa->id,
            function (Mesa $mesa) use ($ana, $bruno): void {
                $admin = $ana->identificadorNoDominio();
                $local = $mesa->cadastrarParticipanteLocal(
                    'Zeca',
                    'zeca@example.test',
                    $admin,
                );

                foreach ([1, null] as $indice => $limite) {
                    $partida = $mesa->novaPartida(
                        CatalogoDeJogos::configuracao('labirinto', 120, null, $limite),
                        $admin,
                    );
                    $partida->iniciar($admin);
                    $tempos = $indice === 0
                        ? [15000, 12000, 9000]
                        : [9000, 12000, 15000];

                    foreach (
                        [$admin, $bruno->identificadorNoDominio(), $local]
                        as $posicao => $participante
                    ) {
                        $partida->iniciarTentativa($participante);
                        $partida->concluirTentativa($participante, $tempos[$posicao]);
                    }
                }

                $mesa->encerrar($admin);
            },
        );

        $resposta = $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()->assertViewIs('mesa.encerrada')
            ->assertSee('Partida 1')->assertSee('Partida 2')
            ->assertSee('Vencedor:')->assertSee('Zeca')
            ->assertSee('Sem vencedor')->assertSee('Participante local');

        $partidas = $resposta->viewData('partidas');
        $this->assertCount(2, $partidas);
        $this->assertSame('Zeca', $partidas[0]['vencedor']);
        $this->assertNull($partidas[1]['vencedor']);
        $semId = static fn (array $placar): array => array_map(static fn (array $linha): array => array_diff_key($linha, ['id' => true]), $placar);
        $this->assertEqualsCanonicalizing([
            ['nome' => 'Ana', 'pontos' => 1, 'colocacao' => 3, 'empate' => false],
            ['nome' => 'Bruno', 'pontos' => 2, 'colocacao' => 2, 'empate' => false],
            ['nome' => 'Zeca', 'pontos' => 3, 'colocacao' => 1, 'empate' => false],
        ], $semId($partidas[0]['placar']));
        $this->assertEqualsCanonicalizing([
            ['nome' => 'Ana', 'pontos' => 3, 'colocacao' => null, 'empate' => false],
            ['nome' => 'Bruno', 'pontos' => 2, 'colocacao' => null, 'empate' => false],
            ['nome' => 'Zeca', 'pontos' => 1, 'colocacao' => null, 'empate' => false],
        ], $semId($partidas[1]['placar']));
    }

    #[Test]
    public function resumoNaoOfereceAcoesERequisicoesDeEscritaContinuamNegadas(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $plataforma = app(Plataforma::class);
        $plataforma->naMesa(
            $mesa->id,
            fn (Mesa $mesa) => $mesa->encerrar($ana->identificadorNoDominio()),
        );

        foreach ([$ana, $bruno] as $usuario) {
            $this->actingAs($usuario)->get(route('mesas.mostrar', $mesa->id))
                ->assertOk()
                ->assertDontSee('action="'.url('/mesas/'.$mesa->id), false)
                ->assertDontSee('Renomear')
                ->assertDontSee('Convidar amigos')
                ->assertDontSee('Jogar neste aparelho')
                ->assertDontSee('Nova partida')
                ->assertDontSee('Jogar minha vez')
                ->assertDontSee('Pausar')
                ->assertDontSee('Encerrar mesa')
                ->assertDontSee('Sair da mesa');

            $this->patch(route('mesas.renomear', $mesa->id), ['nome' => 'Outro'])
                ->assertNotFound();

            foreach ([
                'mesas.participantes-locais',
                'mesas.encerrar',
                'mesas.sair',
                'mesas.convites.criar',
                'mesas.partidas.criar',
                'mesas.partida.iniciar',
                'mesas.partida.pausar',
                'mesas.partida.despausar',
                'mesas.partida.pular',
                'mesas.partida.admitir',
                'mesas.partida.remover',
                'mesas.partida.encerrar',
                'mesas.jogar.iniciar',
                'mesas.jogar.resultado',
            ] as $rota) {
                $this->post(route($rota, $mesa->id))->assertNotFound();
            }

            $this->post(route('mesas.convites.cancelar', [$mesa->id, 'inexistente']))
                ->assertNotFound();

            foreach (['mesas.encerrar', 'mesas.sair', 'mesas.partida.encerrar', 'mesas.jogar'] as $rota) {
                $this->get(route($rota, $mesa->id))->assertNotFound();
            }
        }

        $mesa = $plataforma->mesa($mesa->id);
        $this->assertSame(SituacaoMesa::Encerrada, $mesa->situacao());
        $this->assertSame('Grupo', $mesa->nome());
        $this->assertCount(2, $mesa->membros());
        $this->assertSame([], $mesa->partidas());
    }

    /** @return array{Mesa, User, User} */
    private function prepararMesa(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa(
            $mesa->id,
            fn (Mesa $mesa) => $mesa->adicionarMembro($bruno->identificadorNoDominio()),
        );

        return [$mesa, $ana, $bruno];
    }
}
