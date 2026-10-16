<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Http\Respostas\PedidoDeRecuperacaoRecebido;
use App\Models\User;
use App\Notifications\ConfirmacaoDeEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\ProvocacoesConhecidas;
use Tests\TestCase;

/** Recuperação de senha sem revelar quem tem conta, e o limite dos pedidos que enviam e-mail. */
final class PedidosDeEmailTest extends TestCase
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
    public function recuperacaoRespondeIgualComESemConta(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->post(route('password.email'), ['email' => 'ana@exemplo.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PedidoDeRecuperacaoRecebido::MENSAGEM);
        $this->post(route('password.email'), ['email' => 'ninguem@exemplo.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', PedidoDeRecuperacaoRecebido::MENSAGEM);

        Notification::assertSentTo($usuario, ResetPassword::class);
        Notification::assertCount(1);
    }

    #[Test]
    public function oQuartoPedidoDoMesmoEmailEsperaMesmoSemConta(): void
    {
        for ($pedido = 1; $pedido <= 3; $pedido++) {
            $this->post(route('password.email'), ['email' => 'ninguem@exemplo.com'])->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => 'ninguem@exemplo.com'])
            ->assertSessionHasErrors(['email' => 'Muitos pedidos de e-mail. Tente de novo em 15 minutos.'])
            ->assertSessionHas('provocacao', 'Calma: 15 minutos.');
    }

    #[Test]
    public function oLimiteDoEmailValeParaTodosOsPedidosQueEnviamEmail(): void
    {
        User::factory()->create(['email' => 'ana@exemplo.com']);

        $this->post(route('password.email'), ['email' => 'ana@exemplo.com']);
        $this->post(route('acesso-por-link.enviar'), ['email' => 'ana@exemplo.com']);
        $this->post(route('password.email'), ['email' => 'Ana@Exemplo.com']);

        $this->post(route('acesso-por-link.enviar'), ['email' => 'ana@exemplo.com'])
            ->assertSessionHasErrors(['email' => 'Muitos pedidos de e-mail. Tente de novo em 15 minutos.']);
    }

    #[Test]
    public function depoisDoDecimoPedidoNaMesmaHoraAOrigemEspera(): void
    {
        for ($pessoa = 1; $pessoa <= 10; $pessoa++) {
            $this->post(route('password.email'), ['email' => "pessoa{$pessoa}@exemplo.com"])->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => 'pessoa11@exemplo.com'])
            ->assertSessionHasErrors(['email' => 'Muitos pedidos de e-mail. Tente de novo em 60 minutos.']);
    }

    #[Test]
    public function oReenvioDaConfirmacaoTambemEntraNoLimite(): void
    {
        $usuario = User::factory()->unverified()->create(['email' => 'ana@exemplo.com']);
        $this->actingAs($usuario);

        for ($pedido = 1; $pedido <= 3; $pedido++) {
            $this->post(route('verification.send'))->assertSessionHasNoErrors();
        }

        $this->post(route('verification.send'))
            ->assertSessionHasErrors(['email' => 'Muitos pedidos de e-mail. Tente de novo em 15 minutos.']);
        Notification::assertSentToTimes($usuario, ConfirmacaoDeEmail::class, 3);
    }

    #[Test]
    public function pedidoRecusadoPorDadoInvalidoNaoGastaOLimite(): void
    {
        for ($pedido = 1; $pedido <= 4; $pedido++) {
            $this->post(route('password.email'), ['email' => 'isso-nao-e-email'])->assertSessionHasErrors('email');
        }

        $this->post(route('password.email'), ['email' => 'isso-nao-e-email@exemplo.com'])->assertSessionHasNoErrors();
    }

    #[Test]
    public function aMensagemDoLimiteApareceComAProvocacaoNaTela(): void
    {
        for ($pedido = 1; $pedido <= 3; $pedido++) {
            $this->post(route('password.email'), ['email' => 'ninguem@exemplo.com']);
        }

        $this->followingRedirects()
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'ninguem@exemplo.com'])
            ->assertSee('Muitos pedidos de e-mail. Tente de novo em 15 minutos.')
            ->assertSee('Calma: 15 minutos.');
    }
}
