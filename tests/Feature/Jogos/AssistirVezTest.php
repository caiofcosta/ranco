<?php

declare(strict_types=1);

namespace Tests\Feature\Jogos;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Mesa\Tempo\MesaMudou;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AssistirVezTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function exige_conta_verificada_e_membro_ativo(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa();
        $url = route('mesas.assistir', $mesa->id);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        app(Plataforma::class)->abandonarMesa($mesa->id, $bruno->identificadorNoDominio());
        $this->actingAs($bruno)->get($url)->assertNotFound();
        $this->actingAs($ana)->get(route('mesas.assistir', 'inexistente'))->assertNotFound();
    }

    #[Test]
    public function sem_tentativa_e_paralelo_redirecionam_e_so_o_observador_ve_a_acao(): void
    {
        foreach ([ModoExecucao::Sequencial, ModoExecucao::Paralelo] as $modo) {
            [$mesa, $ana, $bruno] = $this->mesa($modo);
            $assistir = route('mesas.assistir', $mesa->id);
            $mostrar = route('mesas.mostrar', $mesa->id);
            $this->actingAs($bruno)->get($assistir)->assertRedirect($mostrar);
            $this->get(route('mesas.jogo', $mesa->id))->assertDontSee('data-test="assistir-vez"', false);
            if ($modo === ModoExecucao::Sequencial) {
                $this->get(route('mesas.jogo', $mesa->id))->assertSee('Vez de Ana');
            }
            $this->iniciar($mesa, $ana);
            $this->actingAs($ana)->get(route('mesas.jogo', $mesa->id))->assertDontSee('data-test="assistir-vez"', false);
            $this->get($assistir)->assertRedirect($mostrar);
            $this->actingAs($bruno);
            if ($modo === ModoExecucao::Paralelo) {
                $this->get($assistir)->assertRedirect($mostrar);
                $this->get(route('mesas.jogo', $mesa->id))->assertDontSee('data-test="assistir-vez"', false);
                $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertDontSee('data-transmissao=', false);
            } else {
                $this->get(route('mesas.jogo', $mesa->id))->assertSee('Vez de Ana')->assertSee('data-labirinto-assistir', false);
                $this->get($assistir)->assertOk()->assertSee('data-labirinto-assistir', false)
                    ->assertSee('entrada-assistir')->assertDontSee('data-labirinto-partida', false);
                $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertSee('Ana está jogando')->assertSee('data-transmissao=', false);
            }
        }
    }

    #[Test]
    public function inicio_notifica_mesa_e_pausa_mantem_transmissao_mas_conclusao_encerra(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa();
        Event::fake([MesaMudou::class]);
        $this->iniciar($mesa, $ana);
        Event::assertDispatched(MesaMudou::class, fn ($evento) => $evento->mesaId === $mesa->id);
        app(Plataforma::class)->pausarPartida($mesa->id, $ana->identificadorNoDominio());
        $this->actingAs($bruno)->get(route('mesas.assistir', $mesa->id))->assertOk();
        app(Plataforma::class)->naMesa($mesa->id, fn (Mesa $m) => $m->partidaAtiva()->concluirTentativa($ana->identificadorNoDominio(), 10000));
        $this->get(route('mesas.assistir', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
    }

    #[Test]
    public function local_transmite_na_conta_do_admin_sem_oferecer_assistir_ao_proprio_admin(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa(local: true);
        $local = app(Plataforma::class)->mesa($mesa->id)->partidaAtiva()->vezAtual();
        $this->iniciar($mesa, $ana);
        $resposta = $this->actingAs($ana)->get(route('mesas.jogar', $mesa->id))->assertOk();
        $this->assertSame($local, $resposta->viewData('transmissao')['participante']);
        $this->get(route('mesas.assistir', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->actingAs($bruno)->get(route('mesas.assistir', $mesa->id))->assertOk()->assertSee('Vez de Carla');
    }

    #[Test]
    public function sem_partida_ou_em_configuracao_nao_abre_transmissao(): void
    {
        $ana = User::factory()->create();
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio());
        $this->actingAs($ana)->get(route('mesas.assistir', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
        $plataforma->naMesa($mesa->id, fn (Mesa $m) => $m->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, null), $ana->identificadorNoDominio()));
        $this->get(route('mesas.assistir', $mesa->id))->assertRedirect(route('mesas.mostrar', $mesa->id));
    }

    #[Test]
    public function convidado_pendente_e_conta_invisivel_nao_assistem(): void
    {
        [$mesa, $ana, $bruno] = $this->mesa();
        $convidado = User::factory()->create();
        $p = app(Plataforma::class);
        $p->solicitarAmizade($ana->identificadorNoDominio(), $convidado->identificadorNoDominio());
        $p->aceitarAmizade($convidado->identificadorNoDominio(), $ana->identificadorNoDominio());
        $p->convidar($mesa->id, $ana->identificadorNoDominio(), $convidado->identificadorNoDominio());
        $this->iniciar($mesa, $ana);
        $this->actingAs($convidado)->get(route('mesas.assistir', $mesa->id))->assertNotFound();
        $bruno->forceFill(['invisivel' => true])->save();
        $this->actingAs($bruno)->get(route('mesas.assistir', $mesa->id))->assertRedirect(route('dashboard'));
    }

    private function iniciar(Mesa $mesa, User $pessoa): void
    {
        $resposta = $this->actingAs($pessoa)->get(route('mesas.jogar', $mesa->id))->assertOk();
        $this->post(route('mesas.jogar.iniciar', $mesa->id), collect(['jogada', 'partida', 'rodada', 'desempate'])->mapWithKeys(fn ($campo) => [$campo => $resposta->viewData($campo)])->all())->assertRedirect();
    }

    private function mesa(ModoExecucao $modo = ModoExecucao::Sequencial, bool $local = false): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio());
        $plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($ana, $bruno, $modo, $local): void {
            $mesa->adicionarMembro($bruno->identificadorNoDominio());
            $idLocal = $local ? $mesa->cadastrarParticipanteLocal('Carla', 'carla@example.test', $ana->identificadorNoDominio()) : null;
            $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, null, $modo), $ana->identificadorNoDominio());
            if ($local) {
                $partida->reordenar([$idLocal, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio()], $ana->identificadorNoDominio());
            }
            $partida->iniciar($ana->identificadorNoDominio());
        });

        return [$mesa, $ana, $bruno];
    }
}
