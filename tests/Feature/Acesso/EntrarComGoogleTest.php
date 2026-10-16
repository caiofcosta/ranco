<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ContaGoogle;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class EntrarComGoogleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function semClientIdOBotaoSomeEAsRotasNaoExistem(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get(route('login'))->assertOk()->assertDontSee('Continuar com Google');
        $this->get(route('google.redirecionar'))->assertNotFound();
        $this->get(route('google.retorno'))->assertNotFound();
    }

    #[Test]
    public function comClientIdOBotaoAparece(): void
    {
        $this->configurarGoogle();

        $this->get(route('login'))->assertOk()->assertSee('Continuar com Google');
        $this->get(route('register'))->assertOk()->assertSee('Continuar com Google');
    }

    #[Test]
    public function primeiraVezCriaAContaJaConfirmadaESemSenha(): void
    {
        $this->configurarGoogle();
        $this->googleDevolve($this->conta('g-1', 'Ana@Exemplo.com'));

        $this->get(route('google.retorno'))->assertRedirect(route('dashboard', absolute: false));

        $usuario = User::where('email', 'ana@exemplo.com')->sole();
        $this->assertAuthenticatedAs($usuario);
        $this->assertSame('g-1', $usuario->google_id);
        $this->assertTrue($usuario->hasVerifiedEmail());
        $this->assertNull($usuario->password);
    }

    #[Test]
    public function mesmoEmailConfirmadoEAMesmaContaEMantemASenha(): void
    {
        $this->configurarGoogle();
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com']);
        $this->googleDevolve($this->conta('g-1', 'ana@exemplo.com'));

        $this->get(route('google.retorno'));

        $this->assertAuthenticatedAs($usuario);
        $this->assertSame(1, User::count());
        $usuario->refresh();
        $this->assertSame('g-1', $usuario->google_id);
        $this->assertTrue(Hash::check('password', (string) $usuario->password));
    }

    #[Test]
    public function contaNaoConfirmadaComOMesmoEmailPerdeASenha(): void
    {
        $this->configurarGoogle();
        $usuario = User::factory()->unverified()->create(['email' => 'ana@exemplo.com']);
        $this->googleDevolve($this->conta('g-1', 'ana@exemplo.com'));

        $this->get(route('google.retorno'));

        $this->assertAuthenticatedAs($usuario);
        $usuario->refresh();
        $this->assertTrue($usuario->hasVerifiedEmail());
        $this->assertNull($usuario->password, 'A senha pode ter sido definida por quem cadastrou o e-mail de outra pessoa.');
    }

    #[Test]
    public function emailNaoVerificadoNoGoogleNaoEntra(): void
    {
        $this->configurarGoogle();
        $this->googleDevolve($this->conta('g-1', 'ana@exemplo.com', verificado: false));

        $this->get(route('google.retorno'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'O e-mail da sua conta Google não está verificado.']);

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    #[Test]
    public function falhaNoGoogleVoltaParaOLogin(): void
    {
        $this->configurarGoogle();
        $google = Mockery::mock(Provider::class);
        $google->shouldReceive('user')->andThrow(new RuntimeException('Pedido recusado.'));
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($google);

        $this->get(route('google.retorno'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Não deu para entrar com o Google. Tente de novo.']);

        $this->assertGuest();
    }

    private function configurarGoogle(): void
    {
        config(['services.google.client_id' => 'cliente-de-teste', 'services.google.client_secret' => 'segredo-de-teste']);
    }

    #[Test]
    public function fotoDoGoogleAcompanhaCadaLoginInclusiveQuandoDeixaDeVir(): void
    {
        $this->configurarGoogle();
        foreach (['https://lh3.googleusercontent.com/ana', 'https://lh3.googleusercontent.com/ana-nova', null] as $foto) {
            \Illuminate\Support\Facades\Auth::logout();
            $this->googleDevolve($this->conta('g-1', 'ana@exemplo.com', foto: $foto));
            $this->get(route('google.retorno'))->assertRedirect(route('dashboard', absolute: false));
            $this->assertSame($foto, User::where('email', 'ana@exemplo.com')->sole()->google_avatar_url);
        }
        $this->assertSame(1, User::count());
    }

    #[Test]
    public function fotoInvalidaNaoImpedeOLoginENaoESalva(): void
    {
        $this->configurarGoogle();
        $this->googleDevolve($this->conta('g-1', 'ana@exemplo.com', foto: 'javascript:alert(1)'));
        $this->get(route('google.retorno'))->assertRedirect(route('dashboard', absolute: false));
        $this->assertNull(User::sole()->google_avatar_url);
        $this->assertAuthenticated();
    }

    private function conta(string $id, string $email, bool $verificado = true, ?string $foto = null): ContaGoogle
    {
        return (new ContaGoogle())
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verificado])
            ->map(['id' => $id, 'name' => 'Ana', 'email' => $email, 'avatar' => $foto]);
    }

    private function googleDevolve(ContaGoogle $conta): void
    {
        $google = Mockery::mock(Provider::class);
        $google->shouldReceive('user')->andReturn($conta);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($google);
    }
}
