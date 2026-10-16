<?php

declare(strict_types=1);

namespace Tests\Feature\Amizade;

use App\Amizade\ConvitesDeAmizade;
use App\Convite\ConviteRecebido;
use App\Dominio\Plataforma\Plataforma;
use App\Models\ConviteDeAmizade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Convites entre contas sem envio de e-mail nem enumeração de destinatários. */
final class AmizadeTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->ana = User::factory()->create(['name' => 'Ana', 'email' => 'ana@exemplo.com']);
    }

    #[Test]
    public function envioAvisaSoAContaConvidadaEmTempoReal(): void
    {
        Event::fake([ConviteRecebido::class]);
        $bruno = User::factory()->create(['email' => 'bruno@exemplo.com']);

        app(ConvitesDeAmizade::class)->enviar($this->ana, 'bruno@exemplo.com');
        app(ConvitesDeAmizade::class)->enviar($this->ana, 'ninguem@exemplo.com');

        Event::assertDispatchedTimes(ConviteRecebido::class, 1);
        Event::assertDispatched(ConviteRecebido::class, fn (ConviteRecebido $evento): bool => $evento->pessoa === $bruno->identificadorNoDominio()
            && $evento->broadcastOn()->name === 'private-App.Models.User.'.$bruno->identificadorNoDominio()
            && $evento->broadcastAs() === 'convite.recebido');
    }

    #[Test]
    public function envio_nao_revela_conta_e_guarda_somente_identificador(): void
    {
        $bruno = User::factory()->create(['email' => 'bruno@exemplo.com']);
        foreach (['Bruno@Exemplo.com', 'ninguem@exemplo.com', 'ana@exemplo.com'] as $email) {
            $this->actingAs($this->ana)->post(route('amigos.convidar'), ['email' => $email])
                ->assertSessionHasNoErrors()->assertSessionHas('status', ConvitesDeAmizade::MENSAGEM_ENVIO);
        }
        $this->assertSame($bruno->id, ConviteDeAmizade::sole()->para);
        $this->assertFalse(Schema::hasColumn('convites_de_amizade', 'email'));
        $this->actingAs($this->ana)->get(route('amigos.index'))->assertOk()->assertDontSee('Convites enviados')->assertDontSee('bruno@exemplo.com');
        Notification::assertNothingSent();
    }

    #[Test]
    public function pendente_amigo_e_bloqueio_nao_criam_outro_convite(): void
    {
        $bruno = User::factory()->create();
        $servico = app(ConvitesDeAmizade::class);
        $servico->enviar($this->ana, $bruno->email);
        $this->actingAs($this->ana)->post(route('amigos.convidar'), ['email' => $bruno->email])->assertSessionHas('status', ConvitesDeAmizade::MENSAGEM_ENVIO);
        $this->assertDatabaseCount('convites_de_amizade', 1);
        $this->actingAs($bruno)->post(route('amigos.convites.aceitar', ConviteDeAmizade::sole()))->assertSessionHas('status', 'Amizade aceita.');
        $this->assertTrue($this->plataforma()->saoAmigos((string) $this->ana->id, (string) $bruno->id));
        $this->actingAs($this->ana)->post(route('amigos.convidar'), ['email' => $bruno->email])->assertSessionHas('status', ConvitesDeAmizade::MENSAGEM_ENVIO);
        $carla = User::factory()->create();
        $this->plataforma()->bloquear((string) $carla->id, (string) $this->ana->id);
        $this->post(route('amigos.convidar'), ['email' => $carla->email])->assertSessionHas('status', ConvitesDeAmizade::MENSAGEM_ENVIO);
        $this->assertDatabaseCount('convites_de_amizade', 1);
        Notification::assertNothingSent();
    }

    #[Test]
    public function somente_destinatario_pode_recusar_ou_aceitar_mesmo_depois_de_trocar_email(): void
    {
        $bruno = User::factory()->create();
        app(ConvitesDeAmizade::class)->enviar($this->ana, $bruno->email);
        $convite = ConviteDeAmizade::sole();
        $this->actingAs($this->ana)->post(route('amigos.convites.aceitar', $convite))->assertNotFound();
        $this->post(route('amigos.convites.recusar', $convite))->assertNotFound();
        $bruno->update(['email' => 'mudou@exemplo.com']);
        $this->actingAs($bruno)->get(route('amigos.index'))->assertSee('Convites recebidos')->assertSee('Ana');
        $this->post(route('amigos.convites.recusar', $convite))->assertSessionHas('status', 'Convite recusado.');
        $this->get(route('amigos.index'))->assertDontSee('Convites recebidos');
        $this->actingAs($this->ana)->get(route('amigos.index'))->assertDontSee('Recusado')->assertDontSee('Convites enviados');
    }

    #[Test]
    public function expirado_nao_pode_ser_aceito(): void
    {
        $bruno = User::factory()->create();
        app(ConvitesDeAmizade::class)->enviar($this->ana, $bruno->email);
        $this->travel(8)->days();
        $this->actingAs($bruno)->post(route('amigos.convites.aceitar', ConviteDeAmizade::sole()))->assertSessionHasErrors('amizade');
        $this->assertFalse($this->plataforma()->saoAmigos((string) $this->ana->id, (string) $bruno->id));
    }

    #[Test]
    public function links_antigos_estao_indisponiveis_mesmo_sem_login(): void
    {
        $this->get('/amizade/antigo')->assertOk()->assertSee('Convite indisponível');
        $this->post('/amizade/antigo')->assertOk()->assertSee('Convite indisponível');
    }

    #[Test]
    public function validacao_nao_guarda_email_de_terceiro_na_sessao(): void
    {
        $this->actingAs($this->ana)->post(route('amigos.convidar'), ['email' => 'invalido'])
            ->assertSessionHasErrors('email')->assertSessionMissing('_old_input.email');
        $this->assertDatabaseCount('convites_de_amizade', 0);
    }

    #[Test]
    public function envio_continua_limitado(): void
    {
        for ($pedido = 1; $pedido <= 3; $pedido++) {
            $this->actingAs($this->ana)->post(route('amigos.convidar'), ['email' => 'bruno@exemplo.com'])->assertSessionHasNoErrors();
        }
        $this->post(route('amigos.convidar'), ['email' => 'bruno@exemplo.com'])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.email');
        $this->assertDatabaseCount('convites_de_amizade', 0);
    }

    #[Test]
    public function desfazer_e_bloquear_pela_tela(): void
    {
        $bruno = User::factory()->create(['name' => 'Bruno', 'email' => 'bruno@exemplo.com']);
        $carla = User::factory()->create(['name' => 'Carla', 'email' => 'carla@exemplo.com']);
        $this->plataforma()->aceitarConviteDeAmizade((string) $this->ana->id, (string) $bruno->id);
        $this->plataforma()->aceitarConviteDeAmizade((string) $this->ana->id, (string) $carla->id);

        $this->actingAs($this->ana)->post(route('amigos.desfazer', (string) $bruno->id))->assertSessionHas('status', 'Amizade desfeita.');
        $this->actingAs($this->ana)->post(route('amigos.bloquear', (string) $carla->id))->assertSessionHas('status', 'Pessoa bloqueada.');

        $this->assertSame([], $this->plataforma()->amigosDe((string) $this->ana->id));
        $this->assertTrue($this->plataforma()->bloqueou((string) $this->ana->id, (string) $carla->id));
    }

    private function plataforma(): Plataforma
    {
        return app(Plataforma::class);
    }
}
