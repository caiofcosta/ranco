<?php

declare(strict_types=1);

namespace Tests\Feature\Amizade;

use App\Dominio\Plataforma\Plataforma;
use App\Livewire\Amizade\StatusDosAmigos;
use App\Mesa\Tempo\MesaAoVivo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InvisivelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function configuracoesLigamEDesligamOInvisivel(): void
    {
        $ana = User::factory()->create();
        $this->actingAs($ana);

        $componente = Livewire::test('pages::settings.visibilidade-form')->set('invisivel', true)->assertHasNoErrors();
        $this->assertSame(1, (int) DB::table('users')->where('id', $ana->id)->value('invisivel'));

        $componente->set('invisivel', false);
        $this->assertSame(0, (int) DB::table('users')->where('id', $ana->id)->value('invisivel'));
    }

    #[Test]
    public function quemEstaJogandoNaoFicaInvisivelMasTerMesaNaListaNaoImpede(): void
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio());
        $this->actingAs($ana);

        Livewire::test('pages::settings.visibilidade-form')
            ->assertDontSee('Termine sua jogada')
            ->set('invisivel', true)
            ->assertHasNoErrors()
            ->set('invisivel', false);

        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $plataforma->aceitarConvite($plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio())->id, $bruno->identificadorNoDominio());
        $plataforma->criarPartida($mesa->id, \App\Dominio\Jogos\CatalogoDeJogos::configuracao('labirinto', null, null, null, \App\Dominio\Partida\ModoExecucao::Paralelo), $ana->identificadorNoDominio());
        $plataforma->iniciarPartida($mesa->id, $ana->identificadorNoDominio());
        $plataforma->iniciarTentativa($mesa->id, $ana->identificadorNoDominio(), static fn (\App\Dominio\Mesa\Mesa $m): array => [$m->partidaAtiva(), $ana->identificadorNoDominio()]);

        Livewire::test('pages::settings.visibilidade-form')
            ->assertSee('Termine sua jogada e saia da mesa para ficar invisível.')
            ->set('invisivel', true)
            ->assertHasErrors('invisivel')
            ->assertSet('invisivel', false);
        $this->assertSame(0, (int) DB::table('users')->where('id', $ana->id)->value('invisivel'));
    }

    #[Test]
    public function ficarInvisivelTiraDaMesaAAbaQueJaEstavaAberta(): void
    {
        $avisadas = new \ArrayObject();
        $this->app->instance(\App\Dominio\Plataforma\NotificadorDeMesa::class, new class($avisadas) implements \App\Dominio\Plataforma\NotificadorDeMesa
        {
            public function __construct(private \ArrayObject $avisadas) {}

            public function mesaMudou(string $mesaId): void
            {
                $this->avisadas[] = $mesaId;
            }
        });
        $this->app->forgetInstance(Plataforma::class);
        $ana = User::factory()->create();
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio());
        $this->actingAs($ana);
        $aba = Livewire::test(\App\Livewire\Mesa\MostrarMesa::class, ['id' => $mesa->id])->assertOk();
        $avisadas->exchangeArray([]);

        app(Plataforma::class)->ficarInvisivel($ana->identificadorNoDominio());

        $this->assertSame([$mesa->id], $avisadas->getArrayCopy());
        // O aviso chega à aba aberta como atualização da mesa: ela sai para o Início.
        $aba->call('atualizarMesa')->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function ficarInvisivelAvisaAsAbasDaPropriaContaInclusiveAInicioDoConvidado(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Mesa\Tempo\PessoaFicouInvisivel::class]);
        [$bruno] = $this->convidadoComMesa();
        $this->actingAs($bruno)->get(route('dashboard'))
            ->assertSee('<meta name="pessoa-id" content="'.$bruno->identificadorNoDominio().'"', false)
            ->assertSee('data-presenca-convite', false);

        app(Plataforma::class)->ficarInvisivel($bruno->identificadorNoDominio());

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Mesa\Tempo\PessoaFicouInvisivel::class,
            fn ($evento): bool => $evento->pessoa === $bruno->identificadorNoDominio()
                && $evento->broadcastOn()->name === 'private-App.Models.User.'.$bruno->identificadorNoDominio()
                && $evento->broadcastAs() === 'pessoa.ficou-invisivel',
        );
        $this->get(route('dashboard'))->assertDontSee('data-presenca-convite', false);
    }

    #[Test]
    public function invisivelNaoAbreATelaDaMesa(): void
    {
        $ana = User::factory()->create();
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio());
        app(Plataforma::class)->ficarInvisivel($ana->identificadorNoDominio());

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors(['mesa' => 'Fique visível para entrar na mesa.']);
        $this->assertNull(MesaAoVivo::autorizada($ana, $mesa->id));
    }

    #[Test]
    public function amigosVeemQuemEstaInvisivelOfflineESemVisto(): void
    {
        $ana = User::factory()->create();
        $fabi = User::factory()->create(['name' => 'Fabi']);
        $plataforma = app(Plataforma::class);
        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $fabi->identificadorNoDominio());
        $plataforma->ficarInvisivel($fabi->identificadorNoDominio());
        DB::table('users')->where('id', $fabi->id)->update(['visto_em' => now('UTC')->format('Y-m-d H:i:s.v')]);

        $this->actingAs($ana);
        Livewire::test(StatusDosAmigos::class)
            ->assertSee('0 online · 1 offline')
            ->assertSee('Fabi')
            ->assertDontSee('online agora')
            ->assertDontSee('ainda não apareceu')
            ->assertDontSee('visto há');
    }

    #[Test]
    public function inicioDeQuemEstaInvisivelNaoOfereceCriarMesaNemAceitar(): void
    {
        [$bruno] = $this->convidadoComMesa();
        app(Plataforma::class)->ficarInvisivel($bruno->identificadorNoDominio());

        $this->actingAs($bruno)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Você está invisível')
            ->assertSee('data-test="ficar-visivel"', false)
            ->assertSee('data-test="recusar-convite"', false)
            ->assertSee('Fique visível.')
            ->assertDontSee('data-test="criar-mesa"', false)
            ->assertDontSee('data-test="aceitar-convite"', false)
            ->assertDontSee('data-presenca-convite', false);
    }

    #[Test]
    public function aceitarConvitePelaInicioMostraAMensagem(): void
    {
        [$bruno, $convite] = $this->convidadoComMesa();
        app(Plataforma::class)->ficarInvisivel($bruno->identificadorNoDominio());

        $this->actingAs($bruno)->post(route('inicio.convites.aceitar', $convite->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors(['convite' => 'Fique visível para interagir com outras pessoas.']);
    }

    #[Test]
    public function invisivelNaoEntraNaPresencaDaMesaDoConvite(): void
    {
        [$bruno, $convite] = $this->convidadoComMesa();
        $this->assertTrue(MesaAoVivo::presencaAutorizada($bruno, $convite->mesaId));

        app(Plataforma::class)->ficarInvisivel($bruno->identificadorNoDominio());

        $this->assertFalse(MesaAoVivo::presencaAutorizada($bruno, $convite->mesaId));
    }

    #[Test]
    public function invisivelNaoEnviaConviteDeAmizadePorEmail(): void
    {
        Notification::fake();
        $ana = User::factory()->create();
        app(Plataforma::class)->ficarInvisivel($ana->identificadorNoDominio());

        $this->actingAs($ana)->post(route('amigos.convidar'), ['email' => 'alguem@exemplo.test'])
            ->assertSessionHasErrors(['email' => 'Fique visível para interagir com outras pessoas.']);
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('convites_de_amizade')->where('de', $ana->identificadorNoDominio())->count());
    }

    /** @return array{0: User, 1: \App\Dominio\Social\Convite} */
    private function convidadoComMesa(): array
    {
        $ana = User::factory()->create();
        $bruno = User::factory()->create();
        $plataforma = app(Plataforma::class);
        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio());

        return [$bruno, $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio())];
    }
}
