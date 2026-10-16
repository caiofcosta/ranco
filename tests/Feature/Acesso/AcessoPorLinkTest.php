<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Http\Controllers\Acesso\AcessoPorLinkController;
use App\Models\User;
use App\Notifications\LinkDeAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\ProvocacoesConhecidas;
use Tests\TestCase;

final class AcessoPorLinkTest extends TestCase
{
    use ProvocacoesConhecidas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usarProvocacoesConhecidas();
        Notification::fake();
    }

    #[Test]
    public function aTelaAbre(): void
    {
        $this->get(route('acesso-por-link'))->assertOk()->assertSee('Entrar sem senha.');
    }

    #[Test]
    public function oLinkEEnviadoParaQuemTemConta(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->post(route('acesso-por-link.enviar'), ['email' => 'Ana@Exemplo.com'])
            ->assertSessionHas('status', AcessoPorLinkController::MENSAGEM_ENVIADO);

        Notification::assertSentTo($usuario, LinkDeAcesso::class);
    }

    #[Test]
    public function emailSemContaRecebeAMesmaRespostaENenhumEmail(): void
    {
        $this->post(route('acesso-por-link.enviar'), ['email' => 'ninguem@exemplo.com'])
            ->assertSessionHas('status', AcessoPorLinkController::MENSAGEM_ENVIADO);

        Notification::assertNothingSent();
    }

    #[Test]
    public function oLinkEntraEValeUmaUnicaVez(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com']);
        $link = $this->pedirLink($usuario);

        $this->get($link)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($usuario);

        $this->post(route('logout'));
        $this->get($link)->assertRedirect(route('login'))->assertSessionHasErrors(['email' => 'Este link já foi usado. Peça outro.']);
        $this->assertGuest();
    }

    #[Test]
    public function oLinkVenceEmTrintaMinutos(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com']);
        $link = $this->pedirLink($usuario);

        $this->travel(31)->minutes();

        $this->get($link)->assertRedirect(route('login'))->assertSessionHasErrors(['email' => 'Este link venceu ou não é válido. Peça outro.']);
        $this->assertGuest();
    }

    #[Test]
    public function contaNaoConfirmadaTemOEmailConfirmadoEASenhaDescartada(): void
    {
        $usuario = User::factory()->unverified()->create(['email' => 'ana@exemplo.com']);

        $this->get($this->pedirLink($usuario));

        $usuario->refresh();
        $this->assertTrue($usuario->hasVerifiedEmail());
        $this->assertNull($usuario->password, 'A senha pode ter sido definida por quem cadastrou o e-mail de outra pessoa.');
    }

    #[Test]
    public function oQuartoPedidoEmQuinzeMinutosEspera(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com']);
        for ($pedido = 1; $pedido <= 3; $pedido++) {
            $this->post(route('acesso-por-link.enviar'), ['email' => 'ana@exemplo.com']);
        }

        $this->post(route('acesso-por-link.enviar'), ['email' => 'ana@exemplo.com'])
            ->assertSessionHasErrors(['email' => 'Muitos pedidos de e-mail. Tente de novo em 15 minutos.'])
            ->assertSessionHas('provocacao', 'Calma: 15 minutos.');

        Notification::assertSentToTimes($usuario, LinkDeAcesso::class, 3);
    }

    private function pedirLink(User $usuario): string
    {
        $this->post(route('acesso-por-link.enviar'), ['email' => $usuario->email]);

        $link = null;
        Notification::assertSentTo($usuario, LinkDeAcesso::class, function (LinkDeAcesso $notificacao) use (&$link): bool {
            $link = $notificacao->url;

            return true;
        });

        return (string) $link;
    }
}
