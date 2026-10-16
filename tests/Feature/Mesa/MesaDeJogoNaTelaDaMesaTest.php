<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MesaDeJogoNaTelaDaMesaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function membro_ve_as_cadeiras_com_voce_e_coroa_no_admin(): void
    {
        [$mesa, , $bruno] = $this->mesaComTres();

        $this->actingAs($bruno)->get(route('mesas.mostrar', $mesa->id))->assertOk()
            ->assertSee('data-test="mesa-de-jogo"', false)
            ->assertSee('data-cadeira="'.$bruno->identificadorNoDominio().'"', false)
            ->assertSee('Ana Lima, admin da mesa')
            ->assertSee('Mesa visual')
            // Quem não é admin não convida: a vaga não leva a lugar nenhum.
            ->assertDontSee('href="#convidar"', false)
            ->assertDontSee('Quem está aqui');
    }

    #[Test]
    public function admin_ve_convite_pendente_ea_vaga_leva_aos_convites_da_tela(): void
    {
        [$mesa, $ana] = $this->mesaComTres();
        $davi = User::factory()->create(['name' => 'Davi Souza']);
        $plataforma = app(Plataforma::class);
        $plataforma->solicitarAmizade($ana->identificadorNoDominio(), $davi->identificadorNoDominio());
        $plataforma->aceitarAmizade($davi->identificadorNoDominio(), $ana->identificadorNoDominio());
        $plataforma->convidar($mesa->id, $ana->identificadorNoDominio(), $davi->identificadorNoDominio());

        $html = $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))->assertOk()->getContent();

        $this->assertStringContainsString('Convite enviado para Davi Souza. Clique para ver o status e gerenciar o convite.', $html);
        $this->assertStringContainsString('data-cadeira="'.$davi->identificadorNoDominio().'"', $html);
        $this->assertStringContainsString('href="#convidar"', $html);
        $this->assertStringContainsString('id="convidar"', $html);
        $this->assertSame(1, substr_count($html, 'data-tipo="convite"'));
    }

    #[Test]
    public function a_presenca_continua_ligada_a_uma_mesa_so(): void
    {
        [$mesa, $ana] = $this->mesaComTres();

        $html = $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))->assertOk()->getContent();

        $this->assertStringContainsString('data-tempo-real-presenca', $html);
        // A mesa de jogo não declara outra ligação de tempo real além da tela.
        $this->assertSame(1, substr_count($html, 'data-tempo-real-mesa='));
    }

    #[Test]
    public function quem_esta_na_vez_e_encaminhado_para_tela_do_jogo(): void
    {
        [$mesa, $ana] = $this->mesaComTres();
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 120, null, null), $ana->identificadorNoDominio());
        $plataforma->iniciarPartida($mesa->id, $ana->identificadorNoDominio());

        $this->actingAs($ana)->get(route('mesas.mostrar', $mesa->id))->assertRedirect(route('mesas.jogo', $mesa->id));
        $this->get(route('mesas.jogo', $mesa->id))->assertOk()->assertSee('É a sua vez');
    }

    /** @return array{Mesa, User, User, User} */
    private function mesaComTres(): array
    {
        $ana = User::factory()->create(['name' => 'Ana Lima']);
        $bruno = User::factory()->create(['name' => 'Bruno Reis']);
        $carla = User::factory()->create(['name' => 'Carla Dias']);
        $plataforma = app(Plataforma::class);
        $mesa = $plataforma->criarMesa($ana->identificadorNoDominio(), 'Mesa visual');
        $plataforma->naMesa($mesa->id, function (Mesa $mesa) use ($bruno, $carla): void {
            $mesa->adicionarMembro($bruno->identificadorNoDominio());
            $mesa->adicionarMembro($carla->identificadorNoDominio());
        });

        return [$mesa, $ana, $bruno, $carla];
    }
}
