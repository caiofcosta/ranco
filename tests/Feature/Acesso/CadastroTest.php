<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Models\User;
use App\Notifications\ConfirmacaoDeEmail;
use App\Notifications\LinkDeAcesso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\ProvocacoesConhecidas;
use Tests\TestCase;

/** Cadastro que não revela quem tem conta, e o link de confirmação que já entra na conta. */
final class CadastroTest extends TestCase
{
    use ProvocacoesConhecidas;
    use RefreshDatabase;

    private const SENHA = 'senha-boa-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->usarProvocacoesConhecidas();
        Notification::fake();
    }

    #[Test]
    public function emailNovoCriaAContaSemEntrarEMandaOLinkDeConfirmacao(): void
    {
        $this->cadastrar('Ana@Exemplo.com')->assertRedirect(route('cadastro.confira-seu-email'));

        $this->assertGuest();
        $usuario = User::where('email', 'ana@exemplo.com')->sole();
        $this->assertFalse($usuario->hasVerifiedEmail());
        Notification::assertSentTo($usuario, ConfirmacaoDeEmail::class);
    }

    #[Test]
    public function emailComContaNaoCriaNemAlteraNadaEMandaUmLinkDeAcesso(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->cadastrar('ana@exemplo.com', 'outra-senha')->assertRedirect(route('cadastro.confira-seu-email'));

        $this->assertGuest();
        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('password', (string) $usuario->refresh()->password), 'A senha da conta não muda.');
        Notification::assertSentTo($usuario, LinkDeAcesso::class, static fn (LinkDeAcesso $link): bool => $link->pediuCadastro);
        Notification::assertNotSentTo($usuario, ConfirmacaoDeEmail::class);
    }

    #[Test]
    public function aTelaDepoisDoCadastroEAMesmaComOuSemConta(): void
    {
        User::factory()->create(['email' => 'ana@exemplo.com']);

        $comConta = $this->telaDepoisDoCadastro('ana@exemplo.com');
        $semConta = $this->telaDepoisDoCadastro('bia@exemplo.com');

        $this->assertStringContainsString('Confira seu e-mail.', $comConta);
        $this->assertSame($comConta, $semConta);
    }

    #[Test]
    public function oLinkDeConfirmacaoConfirmaEntraEMantemASenha(): void
    {
        $this->cadastrar('ana@exemplo.com');
        $usuario = User::where('email', 'ana@exemplo.com')->sole();

        $this->get($this->linkDeConfirmacao($usuario))->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($usuario);
        $usuario->refresh();
        $this->assertTrue($usuario->hasVerifiedEmail());
        $this->assertTrue(Hash::check(self::SENHA, (string) $usuario->password));
    }

    #[Test]
    public function oLinkDeConfirmacaoFuncionaUmaVezSo(): void
    {
        $this->cadastrar('ana@exemplo.com');
        $link = $this->linkDeConfirmacao(User::where('email', 'ana@exemplo.com')->sole());

        $this->get($link);
        $this->post(route('logout'));

        $this->get($link)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Este link já foi usado. Entre com seu e-mail e senha.']);
        $this->assertGuest();
    }

    #[Test]
    public function oLinkDeConfirmacaoVenceEmQuarentaEOitoHoras(): void
    {
        $this->cadastrar('ana@exemplo.com');
        $link = $this->linkDeConfirmacao(User::where('email', 'ana@exemplo.com')->sole());

        $this->travel(49)->hours();

        $this->get($link)->assertRedirect(route('login'))->assertSessionHasErrors([
            'email' => 'Este link de confirmação venceu ou não é válido. Entre com seu e-mail e senha para receber outro.',
        ]);
        $this->assertGuest();
    }

    #[Test]
    public function aTelaConfiraSeuEmailSoAbreDepoisDoCadastro(): void
    {
        $this->get(route('cadastro.confira-seu-email'))->assertRedirect(route('register'));

        $this->cadastrar('ana@exemplo.com');

        $this->get(route('cadastro.confira-seu-email'))->assertOk()->assertSee('ana@exemplo.com');
    }

    #[Test]
    public function oReenvioRepeteOLinkDoCadastro(): void
    {
        $existente = User::factory()->create(['email' => 'ana@exemplo.com']);
        $this->cadastrar('ana@exemplo.com');
        $this->post(route('cadastro.reenviar-link'))->assertSessionHas('status', 'Enviamos outro link.');
        Notification::assertSentToTimes($existente, LinkDeAcesso::class, 2);

        $this->cadastrar('bia@exemplo.com');
        $nova = User::where('email', 'bia@exemplo.com')->sole();
        $this->post(route('cadastro.reenviar-link'));
        Notification::assertSentToTimes($nova, ConfirmacaoDeEmail::class, 2);
    }

    #[Test]
    public function cadastroRecusadoNaoGastaOLimiteDeEmails(): void
    {
        for ($tentativa = 1; $tentativa <= 4; $tentativa++) {
            $this->post(route('register.store'), [...$this->dados('ana@exemplo.com'), 'password_confirmation' => 'diferente'])
                ->assertSessionHasErrors('password');
        }

        $this->cadastrar('ana@exemplo.com')->assertSessionHasNoErrors()->assertRedirect(route('cadastro.confira-seu-email'));
    }

    #[Test]
    public function oQuartoCadastroComOMesmoEmailEmQuinzeMinutosEspera(): void
    {
        User::factory()->create(['email' => 'ana@exemplo.com']);
        for ($pedido = 1; $pedido <= 3; $pedido++) {
            $this->cadastrar('ana@exemplo.com');
        }

        $this->from(route('register'))
            ->post(route('register.store'), $this->dados('ana@exemplo.com'))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => 'Muitos pedidos de e-mail. Tente de novo em 15 minutos.'])
            ->assertSessionHas('provocacao', 'Calma: 15 minutos.');
    }

    #[Test]
    public function oReenvioTambemEntraNoLimite(): void
    {
        $this->cadastrar('ana@exemplo.com');
        $this->post(route('cadastro.reenviar-link'));
        $this->post(route('cadastro.reenviar-link'));

        $this->from(route('cadastro.confira-seu-email'))
            ->post(route('cadastro.reenviar-link'))
            ->assertSessionHasErrors(['email' => 'Muitos pedidos de e-mail. Tente de novo em 15 minutos.']);
    }

    private function cadastrar(string $email, string $senha = self::SENHA): TestResponse
    {
        return $this->post(route('register.store'), $this->dados($email, $senha));
    }

    /** @return array<string, string> */
    private function dados(string $email, string $senha = self::SENHA): array
    {
        return ['name' => 'Ana', 'email' => $email, 'password' => $senha, 'password_confirmation' => $senha];
    }

    /**
     * O conteúdo da página que aparece depois do cadastro, com o e-mail digitado trocado por um
     * marcador. Fica de fora o que está fora do <main>: o Livewire injeta os próprios scripts só na
     * primeira página de cada processo de teste.
     */
    private function telaDepoisDoCadastro(string $email): string
    {
        $tela = (string) $this->followingRedirects()->post(route('register.store'), $this->dados($email))->assertOk()->getContent();

        return str_replace($email, '{email}', Str::between($tela, '<main', '</main>'));
    }

    private function linkDeConfirmacao(User $usuario): string
    {
        $link = null;
        Notification::assertSentTo($usuario, ConfirmacaoDeEmail::class, function (ConfirmacaoDeEmail $notificacao) use (&$link): bool {
            $link = $notificacao->url;

            return true;
        });

        return (string) $link;
    }
}
