<?php

declare(strict_types=1);

namespace Tests\Feature\Inicio;

use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConvitesNaHomeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function homeMostraOsConvitesECriarMesaContinuaDisponivel(): void
    {
        ['bruno' => $bruno] = $this->brunoComConviteDaAna();

        $this->actingAs($bruno)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Convite pendente', false)
            ->assertSee('Ana', false)
            ->assertSee('Sexta sem produtividade', false)
            ->assertSee('data-test="aceitar-convite"', false)
            ->assertSee('data-test="recusar-convite"', false)
            ->assertSee('Nenhuma mesa ainda', false)
            ->assertSee('data-test="criar-mesa"', false);
    }

    #[Test]
    public function aceitarLevaATelaDaMesa(): void
    {
        ['bruno' => $bruno, 'convite' => $convite, 'mesaId' => $mesaId] = $this->brunoComConviteDaAna();

        $this->actingAs($bruno)
            ->post(route('inicio.convites.aceitar', $convite->id))
            ->assertRedirect(route('mesas.mostrar', $mesaId));

        $this->assertSame($mesaId, (app(Plataforma::class)->mesasAbertasDe($bruno->identificadorNoDominio())[0] ?? null)?->id);
        $this->actingAs($bruno)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-test="convites-na-home"', false);
    }

    #[Test]
    public function recusarSomeComOCard(): void
    {
        ['bruno' => $bruno, 'convite' => $convite] = $this->brunoComConviteDaAna();

        $this->actingAs($bruno)
            ->post(route('inicio.convites.recusar', $convite->id))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($bruno)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-test="convite-na-home"', false)
            ->assertDontSee('Sexta sem produtividade', false);
    }

    #[Test]
    public function aceitarNaoConsomeOLimiteDeMesasAdministradas(): void
    {
        ['bruno' => $bruno, 'convite' => $convite, 'mesaId' => $mesaId] = $this->brunoComConviteDaAna();
        $plataforma = app(Plataforma::class);
        for ($i = 0; $i < Plataforma::LIMITE_DE_MESAS; $i++) {
            $plataforma->criarMesa($bruno->identificadorNoDominio());
        }

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('data-test="aceitar-convite"', false)
            ->assertDontSee('data-test="convite-sem-vaga"', false)
            ->assertSee('data-test="limite-de-mesas"', false)
            ->assertDontSee('data-test="criar-mesa"', false);
        $this->actingAs($bruno)->post(route('inicio.convites.aceitar', $convite->id))
            ->assertRedirect(route('mesas.mostrar', $mesaId));
        $this->assertCount(Plataforma::LIMITE_DE_MESAS + 1, $plataforma->mesasAbertasDe($bruno->identificadorNoDominio()));
    }

    #[Test]
    public function cardMostraQuemConvidouEQuantasPessoasJaEstaoNaMesa(): void
    {
        ['bruno' => $bruno] = $this->brunoComConviteDaAna();

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('convidou você')
            ->assertSee('1 pessoa')
            ->assertDontSee('1 pessoas');
    }

    #[Test]
    public function comMesaAtualAceitarDeixaDeSerAAcaoPrincipal(): void
    {
        ['bruno' => $bruno] = $this->brunoComConviteDaAna();
        app(Plataforma::class)->criarMesa($bruno->identificadorNoDominio(), 'Do Bruno');

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertViewHas('aceitarEmDestaque', false)
            ->assertSee('data-test="aceitar-convite"', false);
    }

    #[Test]
    public function inicioDoConvidadoEntraNaPresencaDaMesaDoConvite(): void
    {
        ['bruno' => $bruno, 'mesaId' => $mesaId] = $this->brunoComConviteDaAna();

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('data-presenca-convite="'.$mesaId.'"', false);
    }

    /** @return array{ana: User, bruno: User, convite: \App\Dominio\Social\Convite, mesaId: string} */
    private function brunoComConviteDaAna(): array
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Sexta sem produtividade');
        $plataforma->solicitarAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $plataforma->aceitarAmizade($bruno->identificadorNoDominio(), $ana->identificadorNoDominio());
        $convite = $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());

        return ['ana' => $ana, 'bruno' => $bruno, 'convite' => $convite, 'mesaId' => $mesa->id];
    }
}
