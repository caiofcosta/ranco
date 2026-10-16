<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Convite\ConviteRecebido;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use App\Notifications\ConviteDeMesa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConviteNaMesaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function conviteDeMesaAvisaAContaConvidadaEmTempoReal(): void
    {
        Event::fake([ConviteRecebido::class]);
        Notification::fake();
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaDaAnaComAmizade();

        $this->actingAs($ana)->post(route('mesas.convites.criar', $mesa->id), ['amigo' => $bruno->identificadorNoDominio()]);

        Event::assertDispatched(ConviteRecebido::class, fn (ConviteRecebido $evento): bool => $evento->pessoa === $bruno->identificadorNoDominio());
    }

    #[Test]
    public function adminConvidaUmaAmizadeEMandaOEmail(): void
    {
        Notification::fake();
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaDaAnaComAmizade();
        $this->actingAs($ana);

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Quem vai jogar?', false)
            ->assertSee('Bruno', false)
            ->assertSee('data-test="convidar-amigo"', false);

        $this->post(route('mesas.convites.criar', $mesa->id), ['amigo' => $bruno->identificadorNoDominio()])
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->assertCount(1, app(Plataforma::class)->convitesPendentesDaMesa($mesa->id));
        Notification::assertSentTo($bruno, ConviteDeMesa::class);

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Cancelar', false)
            ->assertSee('href="#convidar"', false)
            ->assertSee('Convite enviado', false)
            ->assertSee('data-status-convite="pendente"', false)
            ->assertDontSee('data-test="convidar-amigo"', false);
    }

    #[Test]
    public function naoApareceQuemNaoEAmizadeQuemJaEstaNaMesaOuQuemJaTemConvite(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $dora = User::factory()->create(['name' => 'Dora']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $this->tornarAmigos($ana, $bruno);
        $this->tornarAmigos($ana, $carla);
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));
        $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $carla->identificadorNoDominio());

        $this->actingAs($ana)
            ->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Carla', false)
            ->assertDontSee('Dora', false)
            ->assertDontSee('data-test="convidar-amigo"', false);
    }

    #[Test]
    public function quemNaoEAdminNaoConvida(): void
    {
        Notification::fake();
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaDaAnaComAmizade();
        $plataforma = app(Plataforma::class);
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));
        $carla = User::factory()->create(['name' => 'Carla']);
        $this->tornarAmigos($bruno, $carla);

        $this->actingAs($bruno)
            ->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertDontSee('Convidar amigos', false);

        $this->actingAs($bruno)
            ->post(route('mesas.convites.criar', $mesa->id), ['amigo' => $carla->identificadorNoDominio()])
            ->assertNotFound();

        Notification::assertNothingSent();
        $this->assertSame([], $plataforma->convitesPendentesDaMesa($mesa->id));
    }

    #[Test]
    public function adminCancelaOConvitePendente(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'mesa' => $mesa] = $this->mesaDaAnaComAmizade();
        $convite = app(Plataforma::class)->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());

        $this->actingAs($ana)
            ->post(route('mesas.convites.cancelar', [$mesa->id, $convite->id]))
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->assertSame([], app(Plataforma::class)->convitesPendentesDaMesa($mesa->id));
        $this->actingAs($ana)
            ->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Bruno', false)
            ->assertSee('data-test="convidar-amigo"', false)
            ->assertDontSee('data-test="cancelar-convite"', false);
    }

    /** @return array{ana: User, bruno: User, mesa: \App\Dominio\Mesa\Mesa} */
    private function mesaDaAnaComAmizade(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $this->tornarAmigos($ana, $bruno);

        return compact('ana', 'bruno', 'mesa');
    }

    private function tornarAmigos(User $de, User $para): void
    {
        $plataforma = app(Plataforma::class);
        $plataforma->solicitarAmizade($de->identificadorNoDominio(), $para->identificadorNoDominio());
        $plataforma->aceitarAmizade($para->identificadorNoDominio(), $de->identificadorNoDominio());
    }
}
