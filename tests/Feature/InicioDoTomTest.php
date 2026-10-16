<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\Provocacao\RepositorioDeProvocacoes;
use App\Infra\Memoria\RepositorioDeProvocacoesEmMemoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Suporte\ProvocacoesConhecidas;
use Tests\TestCase;

final class InicioDoTomTest extends TestCase
{
    use ProvocacoesConhecidas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usarProvocacoesConhecidas();
    }

    #[Test]
    public function semMesaMostraOEstadoVazio(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana']);
        $this->actingAs($usuario);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Início',
                'data-test="fala-do-tom"',
                'Sem mesa.',
                'Nenhuma mesa ainda',
            ], false)
            ->assertSee('E aí, Ana.', false)
            ->assertSee('data-test="criar-mesa"', false);
        $html = $this->get(route('dashboard'))->getContent();
        $this->assertStringNotContainsString('data-cadeira', $html);
        $this->assertStringNotContainsString('data-tipo="vaga"', $html);
    }

    #[Test]
    public function semFraseAAreaDoTomNaoApareceEATelaAbre(): void
    {
        $this->app->instance(RepositorioDeProvocacoes::class, new RepositorioDeProvocacoesEmMemoria([], []));
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-test="fala-do-tom"', false)
            ->assertSee('Nenhuma mesa ainda', false)
            ->assertSee('Início', false);
    }

    #[Test]
    public function mesaSemOutrosParticipantesMostraAFalaDeEspera(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana']);
        app(Plataforma::class)->criarMesa($usuario->identificadorNoDominio());
        $this->actingAs($usuario);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mesa sozinha.', false)
            ->assertSee('data-test="fala-do-tom"', false)
            // O SVG de corpo inteiro do Tom (class="sombra"/#FF684D) era da Home mobile, retirada na nova navegação.
            ->assertSee('data-test="abrir-mesa"', false)
            ->assertSee('Só você por enquanto', false)
            ->assertSee('Ana', false)
            ->assertDontSee('Nenhuma mesa ainda', false)
            ->assertDontSee('O que vamos arrumar hoje?', false);
    }

    #[Test]
    public function criarMesaSaiDoEstadoVazio(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post(route('mesas.criar'), ['nome' => 'Turma do Tom'])
            ->assertRedirect();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Nenhuma mesa ainda', false)
            ->assertSee('Só você por enquanto', false)
            ->assertSeeInOrder([
                'data-test="fala-do-tom"',
                'data-test="abrir-mesa"',
            ], false);
    }

    #[Test]
    public function convitePendenteMostraONomeDeQuemConvidou(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $bruno = User::factory()->create(['name' => 'Bruno']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio());
        $plataforma->solicitarAmizade($ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $plataforma->aceitarAmizade($bruno->identificadorNoDominio(), $ana->identificadorNoDominio());
        $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $bruno->identificadorNoDominio());
        $this->actingAs($bruno);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Convite de Ana.', false)
            ->assertDontSee('Convite de '.$ana->identificadorNoDominio(), false)
            ->assertSee('Nenhuma mesa ainda', false)
            ->assertSee('data-test="criar-mesa"', false)
            ->assertSee('data-test="convite-na-home"', false);
    }
}
