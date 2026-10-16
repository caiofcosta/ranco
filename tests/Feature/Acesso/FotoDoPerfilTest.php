<?php

declare(strict_types=1);

namespace Tests\Feature\Acesso;

use App\Amizade\ConvitesDeAmizade;
use App\Dominio\Plataforma\Plataforma;
use App\Mesa\PessoasDaMesa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FotoDoPerfilTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function semFotoMostraSoAPrimeiraLetraInclusiveComAcento(): void
    {
        $usuario = User::factory()->make(['name' => '  Érica Silva']);
        $this->assertSame('É', $usuario->initials());
        $this->assertSame('É', PessoasDaMesa::iniciais($usuario->name));
        $html = Blade::render('<x-avatar :nome="$nome" />', ['nome' => $usuario->name]);
        $this->assertStringContainsString('<span>É</span>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    #[Test]
    public function fotoApareceNoMenuENoPerfilProprio(): void
    {
        $ana = $this->anaComFoto();
        $html = $this->actingAs($ana)->get(route('perfil.mostrar'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'src="'.$ana->google_avatar_url.'"'));
    }

    #[Test]
    public function amigoVeFotoNaHomeNoPerfilENoConviteDaMesa(): void
    {
        $ana = $this->anaComFoto();
        $bruno = User::factory()->create();
        $plataforma = app(Plataforma::class);
        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio());
        $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());

        $html = $this->actingAs($bruno)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'src="'.$ana->google_avatar_url.'"'));
        $this->get(route('perfil.amigo', $ana->identificadorNoDominio()))->assertOk()->assertSee($ana->google_avatar_url);

        $this->actingAs(User::factory()->create())
            ->get(route('perfil.amigo', $ana->identificadorNoDominio()))
            ->assertForbidden()->assertDontSee($ana->google_avatar_url);
    }

    #[Test]
    public function conviteDeAmizadeMostraFotoDeQuemConvidou(): void
    {
        Notification::fake();
        $ana = $this->anaComFoto();
        $bruno = User::factory()->create();
        app(ConvitesDeAmizade::class)->enviar($ana, $bruno->email);
        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()->assertSee($ana->google_avatar_url);
    }

    #[Test]
    public function mesaMostraFotosDeMembrosEConvitesPendentes(): void
    {
        $ana = $this->anaComFoto();
        $bruno = User::factory()->create(['google_avatar_url' => 'https://lh3.googleusercontent.com/bruno']);
        $plataforma = app(Plataforma::class);
        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio());
        $convite = $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))->assertOk()
            ->assertSee($ana->google_avatar_url)->assertSee($bruno->google_avatar_url);

        $plataforma->aceitarConvite($convite->id, $bruno->identificadorNoDominio());
        $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))->assertOk()
            ->assertSee($ana->google_avatar_url)->assertSee($bruno->google_avatar_url);
    }

    private function anaComFoto(): User
    {
        return User::factory()->create([
            'name' => 'Ana Silva',
            'google_avatar_url' => 'https://lh3.googleusercontent.com/ana',
        ]);
    }
}
