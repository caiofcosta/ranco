<?php

declare(strict_types=1);

namespace Tests\Feature\Inicio;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Social\Convite;
use App\Mesa\Tempo\MesasDaPessoaMudaram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SuasMesasTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function titulosEhSuasMesasESemADuplicacaoDoDependeDeVoce(): void
    {
        $bruno = User::factory()->create(['name' => 'Bruno']);

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('Suas mesas', false)
            ->assertDontSee('Você administra', false)
            ->assertDontSee('Depende de você', false);
    }

    #[Test]
    public function convitesVemPrimeiroDepoisAdministradasEParticipacoesComOPapelDeCada(): void
    {
        ['bruno' => $bruno, 'mesaDaAna' => $mesaDaAna] = $this->cenarioComConviteMesaAdministradaEParticipacao();

        $html = $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Convite da Carla', 'Do Bruno', 'Da Ana'])
            ->assertSeeInOrder(['Convite pendente', 'Administrador', 'Participante'])
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-mesa-id="'.$mesaDaAna->id.'"'));
    }

    #[Test]
    public function endpointDoBlocoDevolveSoAListaParaAtualizarAHome(): void
    {
        ['bruno' => $bruno] = $this->cenarioComConviteMesaAdministradaEParticipacao();

        $html = $this->actingAs($bruno)->get(route('inicio.suas-mesas'))->assertOk()
            ->assertSee('data-suas-mesas', false)
            ->assertSee('Do Bruno', false)
            ->getContent();

        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('inicio-tom', $html);
    }

    #[Test]
    public function endpointDoBlocoExigeLogin(): void
    {
        $this->get(route('inicio.suas-mesas'))->assertRedirect();
    }

    #[Test]
    public function participarEmMesasDeTerceirosNaoConsomeOLimiteDeCriarMesa(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $this->amigos($ana, $bruno);
        for ($i = 0; $i < Plataforma::LIMITE_DE_MESAS; $i++) {
            $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Da Ana '.$i);
            $convite = $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
            $plataforma->aceitarConvite($convite->id, $bruno->identificadorNoDominio());
        }

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('data-test="criar-mesa"', false)
            ->assertDontSee('data-test="limite-de-mesas"', false);
        $plataforma->criarMesa($bruno->identificadorNoDominio(), 'Do Bruno');

        $this->assertSame(Plataforma::LIMITE_DE_MESAS - 1, $plataforma->vagasDeMesa($bruno->identificadorNoDominio()));
    }

    #[Test]
    public function aceitarERecusarAvisamSoQuemRecebeOConvite(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'convite' => $daAna] = $this->cenarioComDoisConvites();
        $plataforma = app(Plataforma::class);
        Event::fake([MesasDaPessoaMudaram::class]);

        $plataforma->aceitarConvite($daAna->id, $bruno->identificadorNoDominio());
        $daCarla = $plataforma->convitesPendentesPara($bruno->identificadorNoDominio())[0];
        $plataforma->recusarConvite($daCarla->id, $bruno->identificadorNoDominio());

        Event::assertDispatchedTimes(MesasDaPessoaMudaram::class, 2);
        Event::assertDispatched(MesasDaPessoaMudaram::class, fn (MesasDaPessoaMudaram $evento): bool => $evento->pessoa === $bruno->identificadorNoDominio());
        Event::assertNotDispatched(MesasDaPessoaMudaram::class, fn (MesasDaPessoaMudaram $evento): bool => $evento->pessoa === $ana->identificadorNoDominio());
    }

    #[Test]
    public function cancelarAvisaOConvidadoERemoverAvisaQuemSaiu(): void
    {
        ['ana' => $ana, 'bruno' => $bruno, 'carla' => $carla, 'mesaDaAna' => $mesaDaAna, 'convite' => $daCarla] = $this->cenarioComConviteMesaAdministradaEParticipacao();
        $plataforma = app(Plataforma::class);
        Event::fake([MesasDaPessoaMudaram::class]);

        $plataforma->cancelarConvite($daCarla->id, $carla->identificadorNoDominio());
        Event::assertDispatched(MesasDaPessoaMudaram::class, fn (MesasDaPessoaMudaram $evento): bool => $evento->pessoa === $bruno->identificadorNoDominio());

        $plataforma->removerDaMesa($mesaDaAna->id, $bruno->identificadorNoDominio(), $ana->identificadorNoDominio());
        Event::assertDispatchedTimes(MesasDaPessoaMudaram::class, 2);
    }

    #[Test]
    public function ficarVisivelAvisaAPropriaPessoa(): void
    {
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $plataforma->ficarInvisivel($bruno->identificadorNoDominio());
        Event::fake([MesasDaPessoaMudaram::class]);

        $plataforma->ficarVisivel($bruno->identificadorNoDominio());

        Event::assertDispatched(MesasDaPessoaMudaram::class, fn (MesasDaPessoaMudaram $evento): bool => $evento->pessoa === $bruno->identificadorNoDominio());
    }

    /**
     * Bruno tem um convite da Carla, administra "Do Bruno" e participa de "Da Ana".
     *
     * @return array{ana: User, bruno: User, carla: User, mesaDaAna: Mesa, convite: Convite}
     */
    private function cenarioComConviteMesaAdministradaEParticipacao(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $plataforma = app(Plataforma::class);
        $this->amigos($ana, $bruno);
        $this->amigos($carla, $bruno);

        $mesaDaAna = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Da Ana');
        $conviteDaAna = $plataforma->convidar($mesaDaAna->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $plataforma->aceitarConvite($conviteDaAna->id, $bruno->identificadorNoDominio());
        $plataforma->criarMesa($bruno->identificadorNoDominio(), 'Do Bruno');
        $mesaDaCarla = $plataforma->criarMesa($carla->identificadorNoDominio(), 'Convite da Carla');
        $convite = $plataforma->convidar($mesaDaCarla->id, $carla->identificadorNoDominio(), $bruno->identificadorNoDominio());

        return ['ana' => $ana, 'bruno' => $bruno, 'carla' => $carla, 'mesaDaAna' => $mesaDaAna, 'convite' => $convite];
    }

    /**
     * Bruno tem dois convites pendentes: um da Ana e outro da Carla, para mesas diferentes.
     *
     * @return array{ana: User, bruno: User, carla: User, convite: Convite}
     */
    private function cenarioComDoisConvites(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $carla = User::factory()->create(['name' => 'Carla']);
        $plataforma = app(Plataforma::class);
        $this->amigos($ana, $bruno);
        $this->amigos($carla, $bruno);

        $mesaDaAna = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Da Ana');
        $convite = $plataforma->convidar($mesaDaAna->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $mesaDaCarla = $plataforma->criarMesa($carla->identificadorNoDominio(), 'Da Carla');
        $plataforma->convidar($mesaDaCarla->id, $carla->identificadorNoDominio(), $bruno->identificadorNoDominio());

        return ['ana' => $ana, 'bruno' => $bruno, 'carla' => $carla, 'convite' => $convite];
    }

    private function amigos(User $de, User $para): void
    {
        $plataforma = app(Plataforma::class);
        $plataforma->solicitarAmizade($de->identificadorNoDominio(), $para->identificadorNoDominio());
        $plataforma->aceitarAmizade($para->identificadorNoDominio(), $de->identificadorNoDominio());
    }
}
