<?php

declare(strict_types=1);

namespace Tests\Feature\Inicio;

use App\Amizade\AmigosOnline;
use App\Amizade\ConvitesDeAmizade;
use App\Dominio\Plataforma\Plataforma;
use App\Models\ConviteDeAmizade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AmizadesNaHomeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function semAmizadesMostraUmaProvocacaoDoProvocador(): void
    {
        $frases = DB::table('provocacoes')
            ->join('provocacoes_temas', 'provocacoes.id', '=', 'provocacoes_temas.provocacao_id')
            ->where('provocacoes_temas.tema', 'amizade_vazia')
            ->pluck('texto')
            ->all();
        $this->assertNotEmpty($frases);

        $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
            ->assertSee('data-test="provocacao-sem-amizades"', false)
            ->assertSee('data-test="convidar-amigos"', false)
            ->getContent();

        $this->assertTrue(collect($frases)->contains(fn (string $frase): bool => str_contains($html, e($frase))));
    }

    #[Test]
    public function listaAsAmizadesComOnlinePrimeiroELinkParaOPerfil(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bia = User::factory()->create(['name' => 'Bia Offline']);
        $fabi = User::factory()->create(['name' => 'Fabi Online']);
        $plataforma = app(Plataforma::class);
        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $bia->identificadorNoDominio());
        $plataforma->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $fabi->identificadorNoDominio());
        app(AmigosOnline::class)->registrar($fabi, new \DateTimeImmutable());

        $this->actingAs($ana)->get(route('dashboard'))->assertOk()
            ->assertSee('1 online · 1 offline')
            ->assertSeeInOrder(['Fabi Online', 'online agora', 'Bia Offline', 'ainda não apareceu'])
            ->assertSee(route('perfil.amigo', $fabi->identificadorNoDominio()), false)
            ->assertDontSee('data-test="provocacao-sem-amizades"', false);
    }

    #[Test]
    public function aColunaSeAtualizaSozinhaEAProvocacaoNaoTroca(): void
    {
        $relogio = new \Tests\Suporte\RelogioFalso(new \DateTimeImmutable('2026-10-05 12:00:00'));
        $this->app->instance(\App\Dominio\Relogio::class, $relogio);
        $ana = User::factory()->create(['name' => 'Ana']);
        $fabi = User::factory()->create(['name' => 'Fabi Online']);
        app(Plataforma::class)->aceitarConviteDeAmizade($ana->identificadorNoDominio(), $fabi->identificadorNoDominio());
        app(AmigosOnline::class)->registrar($fabi, $relogio->agora());
        $this->actingAs($ana);

        $coluna = \Livewire\Livewire::test(\App\Livewire\Amizade\AmizadesNaInicio::class)
            ->assertSee('1 online · 0 offline')
            ->assertSeeHtml('wire:poll.45s');

        $relogio->avancar(121);
        $coluna->call('$refresh')->assertSee('0 online · 1 offline');

        app(Plataforma::class)->desfazerAmizade($ana->identificadorNoDominio(), $fabi->identificadorNoDominio());
        $provocacao = $coluna->call('$refresh')->get('provocacao');
        $this->assertNotNull($provocacao);
        $coluna->call('$refresh')->assertSet('provocacao', $provocacao)->assertSee($provocacao);
    }

    #[Test]
    public function conviteDeAmizadeRecebidoApareceEAceitarVoltaParaAInicio(): void
    {
        Notification::fake();
        $ana = User::factory()->create(['name' => 'Ana Convida']);
        $bruno = User::factory()->create(['email' => 'bruno@exemplo.test']);
        app(ConvitesDeAmizade::class)->enviar($ana, 'bruno@exemplo.test');

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('Ana Convida')
            ->assertSee('quer jogar com você')
            ->assertSee('data-test="aceitar-amizade"', false)
            ->assertDontSee('data-test="provocacao-sem-amizades"', false);

        $this->from(route('dashboard'))->post(route('amigos.convites.aceitar', ConviteDeAmizade::sole()))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Amizade aceita.');
        $this->assertTrue(app(Plataforma::class)->saoAmigos($ana->identificadorNoDominio(), $bruno->identificadorNoDominio()));
    }

    #[Test]
    public function invisivelVeOConviteDeAmizadeMasNaoOAceitaPelaInicio(): void
    {
        Notification::fake();
        $ana = User::factory()->create(['name' => 'Ana Convida']);
        $bruno = User::factory()->create(['email' => 'bruno@exemplo.test']);
        app(ConvitesDeAmizade::class)->enviar($ana, 'bruno@exemplo.test');
        app(Plataforma::class)->ficarInvisivel($bruno->identificadorNoDominio());

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('Ana Convida')
            ->assertSee('Fique visível para aceitar.')
            ->assertDontSee('data-test="aceitar-amizade"', false)
            ->assertSee('data-test="recusar-amizade"', false);
    }
}
