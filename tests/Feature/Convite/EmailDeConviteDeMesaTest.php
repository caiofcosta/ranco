<?php

declare(strict_types=1);

namespace Tests\Feature\Convite;

use App\Convite\ConvidarParaMesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use App\Notifications\ConviteDeMesa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EmailDeConviteDeMesaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function convidarEnviaOEmailComQuemChamouAMesaEOBotao(): void
    {
        Notification::fake();
        ['renata' => $renata, 'convidado' => $convidado, 'convite' => $convite] = $this->convidar();

        Notification::assertSentTo($convidado, ConviteDeMesa::class, function (ConviteDeMesa $email) use ($renata, $convidado, $convite): bool {
            $this->assertSame('Renata', $email->nomeConvidante);
            $this->assertSame('Sexta sem produtividade', $email->nomeMesa);
            $this->assertSame($convidado->email, $email->emailDestinatario);
            $this->assertNotSame('', $email->mensagemTom);
            $this->assertStringContainsString($convite->id, $email->urlConvite);
            $this->assertStringContainsString('signature=', $email->urlConvite);

            $html = (string) $email->toMail($convidado)->render();
            $this->assertStringContainsString('Renata te chamou para “Sexta sem produtividade”', $email->toMail($convidado)->subject);
            $this->assertStringContainsString(ConviteDeMesa::PREHEADER, $html);
            $this->assertStringContainsString('Renata te convidou para uma mesa.', $html);
            $this->assertStringContainsString('Sexta sem produtividade', $html);
            $this->assertStringContainsString('Entrar na mesa', $html);
            $this->assertStringContainsString($email->urlConvite, $html);
            $this->assertStringContainsString('Ainda não tem conta?', $html);
            $this->assertStringContainsString('tom-email.png', $html);
            $this->assertStringNotContainsString('<script', $html);

            return true;
        });
    }

    #[Test]
    public function convidarPelaPlataformaNaoMandaEmail(): void
    {
        Notification::fake();
        $renata = User::factory()->create(['name' => 'Renata']);
        $convidado = User::factory()->create(['name' => 'Caio']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($renata->identificadorNoDominio(), 'Sexta sem produtividade');
        $this->tornarAmigos($renata, $convidado);
        $plataforma->convidar($mesa->id, $renata->identificadorNoDominio(), $convidado->identificadorNoDominio());

        Notification::assertNothingSent();
    }

    /** @return array{renata: User, convidado: User, convite: \App\Dominio\Social\Convite} */
    private function convidar(): array
    {
        $renata = User::factory()->create(['name' => 'Renata']);
        $convidado = User::factory()->create(['name' => 'Caio', 'email' => 'caio@exemplo.com']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($renata->identificadorNoDominio(), 'Sexta sem produtividade');
        $this->tornarAmigos($renata, $convidado);
        $convite = app(ConvidarParaMesa::class)->executar($mesa->id, $renata, $convidado);

        return compact('renata', 'convidado', 'convite');
    }

    private function tornarAmigos(User $de, User $para): void
    {
        $plataforma = app(Plataforma::class);
        $plataforma->solicitarAmizade($de->identificadorNoDominio(), $para->identificadorNoDominio());
        $plataforma->aceitarAmizade($para->identificadorNoDominio(), $de->identificadorNoDominio());
    }
}
