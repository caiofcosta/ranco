<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\ProvocacoesConhecidas;
use Tests\TestCase;

final class LoginComProvocacoesTest extends TestCase
{
    use ProvocacoesConhecidas;
    use RefreshDatabase;

    private const PAUSA = 'Muitas tentativas. Tente de novo em 15 minutos.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->usarProvocacoesConhecidas();
        User::factory()->create(['email' => 'ana@exemplo.com']);
    }

    #[Test]
    public function primeiroErroTrazAMensagemTecnicaEUmaProvocacao(): void
    {
        $this->entrar('ana@exemplo.com', 'errada!!')
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.'])
            ->assertSessionHas('provocacao', 'Erro leve.');

        $this->assertGuest();
    }

    #[Test]
    public function aProvocacaoApareceNaTelaJuntoDoErro(): void
    {
        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), ['email' => 'ana@exemplo.com', 'password' => 'errada!!'])
            ->assertSee('E-mail ou senha incorretos.')
            ->assertSee('Erro leve.');
    }

    #[Test]
    public function aProvocacaoEscalaACadaErro(): void
    {
        $this->entrar('ana@exemplo.com', 'errada!!');

        $this->entrar('ana@exemplo.com', 'errada!!')->assertSessionHas('provocacao', 'Erro médio.');
        $this->entrar('ana@exemplo.com', 'errada!!')->assertSessionHas('provocacao', 'Erro número 3.');
        $this->entrar('ana@exemplo.com', 'errada!!')->assertSessionHas('provocacao', 'Erro número 4.');
    }

    #[Test]
    public function noQuintoErroVemAPausaDeQuinzeMinutos(): void
    {
        $this->errarVezes('ana@exemplo.com', 4);

        $this->entrar('ana@exemplo.com', 'errada!!')
            ->assertSessionHasErrors(['email' => self::PAUSA])
            ->assertSessionHas('provocacao', 'Pausa de 15 minutos.')
            ->assertSessionHas('pausa_em_minutos', 15);
    }

    #[Test]
    public function duranteAPausaNemASenhaCertaEntra(): void
    {
        $this->errarVezes('ana@exemplo.com', 5);

        $this->entrar('ana@exemplo.com', 'password')->assertSessionHasErrors(['email' => self::PAUSA]);

        $this->assertGuest();
    }

    #[Test]
    public function depoisDaPausaDaParaEntrar(): void
    {
        $this->errarVezes('ana@exemplo.com', 5);

        $this->travel(15)->minutes();

        $this->entrar('ana@exemplo.com', 'password')->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    #[Test]
    public function entrarCertoZeraOsErros(): void
    {
        $this->errarVezes('ana@exemplo.com', 4);
        $this->entrar('ana@exemplo.com', 'password');
        $this->post(route('logout'));

        $this->entrar('ana@exemplo.com', 'errada!!')->assertSessionHas('provocacao', 'Erro leve.');
    }

    #[Test]
    public function emailSemContaRecebeAMesmaRespostaEAMesmaPausa(): void
    {
        $this->entrar('ninguem@exemplo.com', 'chute!!!')
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.'])
            ->assertSessionHas('provocacao', 'Erro leve.');

        $this->errarVezes('ninguem@exemplo.com', 3);

        $this->entrar('ninguem@exemplo.com', 'chute!!!')->assertSessionHasErrors(['email' => self::PAUSA]);
    }

    #[Test]
    public function limitePorOrigemPegaQuemTentaVariosEmails(): void
    {
        for ($pessoa = 1; $pessoa <= 19; $pessoa++) {
            $this->entrar("pessoa{$pessoa}@exemplo.com", 'chute!!!')
                ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        }

        $this->entrar('pessoa20@exemplo.com', 'chute!!!')->assertSessionHasErrors(['email' => self::PAUSA]);
        $this->entrar('ana@exemplo.com', 'password')->assertSessionHasErrors(['email' => self::PAUSA]);
        $this->assertGuest();
    }

    private function entrar(string $email, string $senha): TestResponse
    {
        return $this->post(route('login.store'), ['email' => $email, 'password' => $senha]);
    }

    private function errarVezes(string $email, int $vezes): void
    {
        for ($vez = 1; $vez <= $vezes; $vez++) {
            $this->entrar($email, 'errada!!');
        }
    }
}
