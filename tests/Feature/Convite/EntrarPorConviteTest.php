<?php

declare(strict_types=1);

namespace Tests\Feature\Convite;

use App\Convite\LinkDoConvite;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Social\Convite;
use App\Models\User;
use App\Notifications\ConfirmacaoDeEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EntrarPorConviteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function visitanteEMandadoAoLoginEVoltaDepoisDeEntrar(): void
    {
        ['convidado' => $convidado, 'convite' => $convite, 'mesaId' => $mesaId] = $this->mesaComConvite();
        $url = LinkDoConvite::assinado($convite->id);

        $this->get($url)->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => $convidado->email,
            'password' => 'password',
        ])->assertRedirect($url);

        $this->get($url)
            ->assertOk()
            ->assertSee('Renata te convidou para uma mesa.', false)
            ->assertSee('Sexta sem produtividade', false);

        $this->post(LinkDoConvite::aceitar($convite->id))->assertRedirect(route('mesas.mostrar', $mesaId));
        $this->assertSame($mesaId, (app(Plataforma::class)->mesasAbertasDe($convidado->identificadorNoDominio())[0] ?? null)?->id);
    }

    #[Test]
    public function depoisDoCadastroOLinkDeConfirmacaoVoltaAoConvite(): void
    {
        Notification::fake();
        ['convite' => $convite] = $this->mesaComConvite();
        $url = LinkDoConvite::assinado($convite->id);

        $this->get($url)->assertRedirect(route('login'));
        $this->post(route('register.store'), [
            'name' => 'Nova',
            'email' => 'nova@exemplo.com',
            'password' => 'senha-boa-123',
            'password_confirmation' => 'senha-boa-123',
        ])->assertRedirect(route('cadastro.confira-seu-email'));

        $nova = User::where('email', 'nova@exemplo.com')->sole();
        $link = null;
        Notification::assertSentTo($nova, ConfirmacaoDeEmail::class, function (ConfirmacaoDeEmail $email) use (&$link): bool {
            $link = $email->url;

            return true;
        });

        $this->get((string) $link)->assertRedirect($url);
    }

    #[Test]
    public function contaErradaNaoEntraNaMesa(): void
    {
        ['convite' => $convite] = $this->mesaComConvite();
        $outra = User::factory()->create(['name' => 'Outra']);

        $this->actingAs($outra)
            ->get(LinkDoConvite::assinado($convite->id))
            ->assertOk()
            ->assertSee('Este convite não é para esta conta.', false);

        $this->actingAs($outra)
            ->post(LinkDoConvite::aceitar($convite->id))
            ->assertRedirect();
        $this->assertNull((app(Plataforma::class)->mesasAbertasDe($outra->identificadorNoDominio())[0] ?? null));
    }

    #[Test]
    public function assinaturaInvalidaNaoAbre(): void
    {
        ['convidado' => $convidado, 'convite' => $convite] = $this->mesaComConvite();

        $this->actingAs($convidado)
            ->get(route('convites.entrar', $convite->id))
            ->assertForbidden();
    }

    #[Test]
    public function conviteJaAceitoLevaParaAMesa(): void
    {
        ['convidado' => $convidado, 'convite' => $convite, 'mesaId' => $mesaId] = $this->mesaComConvite();
        app(Plataforma::class)->aceitarConvite($convite->id, $convidado->identificadorNoDominio());

        $this->actingAs($convidado)
            ->get(LinkDoConvite::assinado($convite->id))
            ->assertRedirect(route('mesas.mostrar', $mesaId));
    }

    #[Test]
    public function conviteCanceladoNaoEntra(): void
    {
        ['renata' => $renata, 'convidado' => $convidado, 'convite' => $convite] = $this->mesaComConvite();
        app(Plataforma::class)->cancelarConvite($convite->id, $renata->identificadorNoDominio());

        $this->actingAs($convidado)
            ->get(LinkDoConvite::assinado($convite->id))
            ->assertOk()
            ->assertSee('Este convite não vale mais.', false);
    }

    /** @return array{renata: User, convidado: User, convite: Convite, mesaId: string} */
    private function mesaComConvite(): array
    {
        $renata = User::factory()->create(['name' => 'Renata']);
        $convidado = User::factory()->create(['name' => 'Caio']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($renata->identificadorNoDominio(), 'Sexta sem produtividade');
        $plataforma->solicitarAmizade($renata->identificadorNoDominio(), $convidado->identificadorNoDominio());
        $plataforma->aceitarAmizade($convidado->identificadorNoDominio(), $renata->identificadorNoDominio());
        $convite = $plataforma->convidar($mesa->id, $renata->identificadorNoDominio(), $convidado->identificadorNoDominio());

        return ['renata' => $renata, 'convidado' => $convidado, 'convite' => $convite, 'mesaId' => $mesa->id];
    }
}
