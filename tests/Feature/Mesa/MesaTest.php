<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MesaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function criaComNomeEVaiParaATelaDaMesa(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana']);
        $this->actingAs($usuario);

        $resposta = $this->post(route('mesas.criar'), ['nome' => 'Clube do ranço']);
        $mesa = (app(Plataforma::class)->mesasAbertasDe($usuario->identificadorNoDominio())[0] ?? null);

        $this->assertNotNull($mesa);
        $this->assertSame('Clube do ranço', $mesa->nome());
        $resposta->assertRedirect(route('mesas.mostrar', $mesa->id));
        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Clube do ranço', false);
    }

    #[Test]
    public function formularioExigeNomeEMantemSugestoes(): void
    {
        $this->actingAs(User::factory()->create());

        $resposta = $this->get(route('mesas.formulario'))
            ->assertOk()
            ->assertSee('Sugestões', false)
            ->assertDontSee('placeholder="Opcional"', false);

        $documento = new \DOMDocument;
        @$documento->loadHTML($resposta->getContent());
        $consulta = new \DOMXPath($documento);

        $this->assertSame(
            1,
            $consulta->query('//input[@name="nome"][@required][@maxlength="40"]')->length,
        );
    }

    #[Test]
    public function rejeitaNomeAusenteVazioOuSomenteEspacosSemCriarMesa(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        foreach ([[], ['nome' => null], ['nome' => ''], ['nome' => " \t\n "]] as $dados) {
            $this->from(route('mesas.formulario'))
                ->post(route('mesas.criar'), $dados)
                ->assertRedirect(route('mesas.formulario'))
                ->assertSessionHasErrors(['nome' => 'Informe o nome da mesa.']);

            $this->assertSame(
                [],
                app(Plataforma::class)->mesasAbertasDe($usuario->identificadorNoDominio()),
            );

            $this->withCookie(config('session.cookie'), session()->getId())
                ->get(route('mesas.formulario'))
                ->assertOk()
                ->assertSee('Informe o nome da mesa.');
        }
    }

    #[Test]
    public function mantemOLimiteDeQuarentaCaracteres(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post(route('mesas.criar'), ['nome' => str_repeat('a', 41)])
            ->assertSessionHasErrors('nome');

        $this->assertSame(
            [],
            app(Plataforma::class)->mesasAbertasDe($usuario->identificadorNoDominio()),
        );

        $this->post(route('mesas.criar'), ['nome' => str_repeat('a', 40)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(
            str_repeat('a', 40),
            app(Plataforma::class)->mesasAbertasDe($usuario->identificadorNoDominio())[0]->nome(),
        );
    }

    #[Test]
    public function telaDaMesaSoParaMembros(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');

        $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))->assertNotFound();
        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))->assertOk()->assertSee('Grupo', false);
    }

    #[Test]
    public function adminRenomeiaAMesa(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $this->actingAs($ana);

        $this->patch(route('mesas.renomear', $mesa->id), ['nome' => 'Firma'])
            ->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->get(route('mesas.mostrar', $mesa->id))->assertSee('Firma', false);
        $this->get(route('dashboard'))->assertSee('Firma', false);
    }

    #[Test]
    public function cancelarSemPartidaVoltaAoInicioELiberaOsDemais(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));

        // Sem nenhuma partida concluída, encerrar é cancelar: volta ao início, sem resultado.
        $this->actingAs($ana)
            ->post(route('mesas.encerrar', $mesa->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', 'Mesa cancelada. Dessa vez não rolou!');

        $this->assertNull(($plataforma->mesasAbertasDe($bruno->identificadorNoDominio())[0] ?? null));
        $this->actingAs($bruno)->post(route('mesas.criar'), ['nome' => 'Do Bruno'])->assertRedirect();
        $this->assertNotNull(($plataforma->mesasAbertasDe($bruno->identificadorNoDominio())[0] ?? null));
    }

    #[Test]
    public function sairRedirecionaParaOInicio(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($bruno->identificadorNoDominio()));

        $this->actingAs($bruno)
            ->get(route('mesas.sair', $mesa->id))
            ->assertOk()
            ->assertSee('Sair da mesa é definitivo.', false);

        $this->actingAs($bruno)
            ->post(route('mesas.sair', $mesa->id))
            ->assertRedirect(route('dashboard'));

        $this->assertNull(($plataforma->mesasAbertasDe($bruno->identificadorNoDominio())[0] ?? null));
        $this->actingAs($bruno)->get(route('dashboard'))->assertSee('Nenhuma mesa ainda', false);
    }

    #[Test]
    public function cardDoInicioMostraONome(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana']);
        app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio(), 'Laboratório de rancor');
        $this->actingAs($usuario);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Laboratório de rancor', false)
            ->assertSee('Só você por enquanto', false)
            ->assertSee('Abrir mesa', false)
            ->assertSee('Suas mesas', false);
    }

    #[Test]
    public function adminCadastraParticipanteLocalComNomeEEMail(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $mesa = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Grupo');
        $this->actingAs($ana);

        $this->post(route('mesas.participantes-locais', $mesa->id), [
            'nome' => 'Zeca',
            'email' => 'zeca@exemplo.com',
        ])->assertRedirect(route('mesas.mostrar', $mesa->id));

        $this->get(route('mesas.mostrar', $mesa->id))
            ->assertOk()
            ->assertSee('Zeca', false)
            ->assertSee('Zeca, participante local', false);
    }
}
