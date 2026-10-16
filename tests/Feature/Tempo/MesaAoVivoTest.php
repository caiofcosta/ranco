<?php

declare(strict_types=1);

namespace Tests\Feature\Tempo;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\NotificadorDeMesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Relogio;
use App\Mesa\Tempo\MesaAoVivo;
use App\Mesa\Tempo\MesaMudou;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Suporte\RelogioFalso;
use Tests\TestCase;

final class MesaAoVivoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function membroDeMesaAbertaTemAcesso(): void
    {
        [$mesa, , $bruno] = $this->prepararMesa();

        $this->assertInstanceOf(Mesa::class, MesaAoVivo::autorizada($bruno, $mesa->id));
    }

    #[Test]
    public function quemNaoEMembroNaoTemAcesso(): void
    {
        [$mesa] = $this->prepararMesa();

        $this->assertNull(MesaAoVivo::autorizada(User::factory()->create(), $mesa->id));
    }

    #[Test]
    public function mesaEncerradaNaoTemAtualizacaoAoVivo(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $this->assertNull(MesaAoVivo::autorizada($ana, $mesa->id));
    }

    #[Test]
    public function mesaInexistenteNaoTemAcesso(): void
    {
        $this->assertNull(MesaAoVivo::autorizada(User::factory()->create(), 'nao-existe'));
    }

    #[Test]
    public function convidadoPendenteEntraSoNaPresencaEnquantoOConviteValer(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $davi = User::factory()->create(['name' => 'Davi']);
        $plataforma = app(Plataforma::class);
        $plataforma->solicitarAmizade($ana->identificadorNoDominio(), $davi->identificadorNoDominio());
        $plataforma->aceitarAmizade($davi->identificadorNoDominio(), $ana->identificadorNoDominio());
        $this->assertFalse(MesaAoVivo::presencaAutorizada($davi, $mesa->id));

        $convite = $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $davi->identificadorNoDominio());

        $this->assertTrue(MesaAoVivo::presencaAutorizada($davi, $mesa->id));
        // Atualizações da mesa continuam só para quem está nela.
        $this->assertNull(MesaAoVivo::autorizada($davi, $mesa->id));
        $this->assertFalse(MesaAoVivo::presencaAutorizada(User::factory()->create(), $mesa->id));

        $plataforma->cancelarConvite($convite->id, $ana->identificadorNoDominio());
        $this->assertFalse(MesaAoVivo::presencaAutorizada($davi, $mesa->id));
    }

    #[Test]
    public function membroContinuaComPresencaEMesaEncerradaNaoTem(): void
    {
        [$mesa, $ana, $bruno] = $this->prepararMesa();
        $this->assertTrue(MesaAoVivo::presencaAutorizada($bruno, $mesa->id));

        app(Plataforma::class)->encerrarMesa($mesa->id, $ana->identificadorNoDominio());

        $this->assertFalse(MesaAoVivo::presencaAutorizada($bruno, $mesa->id));
    }

    #[Test]
    public function alteracaoDaMesaDisparaOSinalParaAMesa(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        Event::fake([MesaMudou::class]);

        app(Plataforma::class)->renomearMesa($mesa->id, 'Novo nome', $ana->identificadorNoDominio());

        Event::assertDispatched(MesaMudou::class, fn (MesaMudou $evento): bool => $evento->mesaId === $mesa->id);
    }

    #[Test]
    public function sinalUsaCanalPrivadoDaMesaSemDados(): void
    {
        $evento = new MesaMudou('abc');

        $this->assertInstanceOf(PrivateChannel::class, $evento->broadcastOn());
        $this->assertSame('private-mesa.abc', $evento->broadcastOn()->name);
        $this->assertSame('mesa.mudou', $evento->broadcastAs());
        $this->assertSame([], $evento->broadcastWith());
    }

    #[Test]
    public function falhaNoAvisoNaoTransformaAAcaoGravadaEmErro(): void
    {
        Exceptions::fake();
        $this->app->instance(NotificadorDeMesa::class, new class implements NotificadorDeMesa
        {
            public function mesaMudou(string $mesaId): void
            {
                throw new RuntimeException('Reverb fora do ar.');
            }
        });
        $this->app->forgetInstance(Plataforma::class);
        [$mesa, $ana] = $this->prepararMesa();

        app(Plataforma::class)->renomearMesa($mesa->id, 'Novo nome', $ana->identificadorNoDominio());

        $this->assertSame('Novo nome', app(Plataforma::class)->mesa($mesa->id)->nome());
        Exceptions::assertReported(RuntimeException::class);
    }

    #[Test]
    public function aceitarConviteAvisaAMesa(): void
    {
        [$mesa, $ana] = $this->prepararMesa();
        $carla = User::factory()->create(['name' => 'Carla']);
        $plataforma = app(Plataforma::class);
        $plataforma->solicitarAmizade($ana->identificadorNoDominio(), $carla->identificadorNoDominio());
        $plataforma->aceitarAmizade($carla->identificadorNoDominio(), $ana->identificadorNoDominio());
        $convite = $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $carla->identificadorNoDominio());
        Event::fake([MesaMudou::class]);

        $plataforma->aceitarConvite($convite->id, $carla->identificadorNoDominio());

        Event::assertDispatched(MesaMudou::class, fn (MesaMudou $evento): bool => $evento->mesaId === $mesa->id);
    }

    #[Test]
    public function prazoVencidoAvisaUmaVezELeituraSemMudancaNaoAvisa(): void
    {
        $relogio = new RelogioFalso(new DateTimeImmutable('2026-10-04 12:00:00'));
        $this->app->instance(Relogio::class, $relogio);
        [$mesa, $ana] = $this->prepararMesa();
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 60, null, null), $ana->identificadorNoDominio());
        $plataforma->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        Event::fake([MesaMudou::class]);

        $plataforma->mesa($mesa->id);
        Event::assertNotDispatched(MesaMudou::class);

        $relogio->avancar(61);
        $plataforma->mesa($mesa->id);
        $plataforma->mesa($mesa->id);

        Event::assertDispatchedTimes(MesaMudou::class, 1);
    }

    /** @return array{0: Mesa, 1: User, 2: User} */
    private function prepararMesa(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        app(Plataforma::class)->naMesa($mesa->id, static fn (Mesa $mesa) => $mesa->adicionarMembro($bruno->identificadorNoDominio()));

        return [$mesa, $ana, $bruno];
    }
}
