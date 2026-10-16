<?php

declare(strict_types=1);

namespace Tests\Feature\Inicio;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MesaNaHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\MapasLabirintoSeeder::class);
    }

    #[Test]
    public function mesaAtualApareceEmLinhaSemAsCadeiras(): void
    {
        [$mesa, $ana, $bruno] = $this->mesaComTres();

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('data-mesa-id="'.$mesa->id.'"', false)
            ->assertSee('Mesa visual')
            ->assertSee('Ana Lima · 3 pessoas')
            ->assertSee('Aguardando')
            ->assertSee('data-test="abrir-mesa"', false)
            ->assertSee(route('mesas.mostrar', $mesa->id), false)
            ->assertDontSee('data-cadeira', false)
            ->assertDontSee('data-test="janela-de-convite"', false);
        $this->actingAs($ana)->get(route('dashboard'))->assertOk()
            ->assertSee('Administrador');
    }

    #[Test]
    public function vezDaPessoaLevaDiretoAoJogoEMostraARodada(): void
    {
        [$mesa, $ana, $bruno] = $this->mesaComTres();
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 120, null, 3), $ana->identificadorNoDominio());
        $plataforma->iniciarPartida($mesa->id, $ana->identificadorNoDominio());

        $this->actingAs($ana)->get(route('dashboard'))->assertOk()
            ->assertSee('Sua vez')
            ->assertSee('Rodada 1 de 3')
            ->assertSee('data-test="jogar-pela-home"', false)
            ->assertSee(route('mesas.jogar', $mesa->id), false);
        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('Em jogo')
            ->assertSee('data-test="abrir-mesa"', false)
            ->assertDontSee('data-test="jogar-pela-home"', false);
    }

    #[Test]
    public function noParaleloQuemAindaNaoJogouTemAAcaoDeJogar(): void
    {
        [$mesa, $ana, $bruno] = $this->mesaComTres();
        $plataforma = app(Plataforma::class);
        $plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, 120, null, null, ModoExecucao::Paralelo), $ana->identificadorNoDominio());
        $plataforma->iniciarPartida($mesa->id, $ana->identificadorNoDominio());

        $this->actingAs($bruno)->get(route('dashboard'))->assertOk()
            ->assertSee('Rodada 1')
            ->assertDontSee('Rodada 1 de', false)
            ->assertSee('data-test="jogar-pela-home"', false);
    }

    #[Test]
    public function aInicioListaAsMesasSemManterPresenca(): void
    {
        [$mesa, $ana] = $this->mesaComTres();
        $outra = app(Plataforma::class)->criarMesa($ana->identificadorNoDominio(), 'Segunda mesa');

        // Estar sentado é estar na tela da mesa: a Início só lista.
        // O contador "N de 5" era do resumo da Home mobile, retirado na nova navegação.
        $this->actingAs($ana)->get(route('dashboard'))->assertOk()
            ->assertSee('Segunda mesa')
            ->assertSee('Mesa visual')
            ->assertDontSee('data-tempo-real-mesa', false)
            ->assertDontSee('data-tempo-real-presenca', false);
        $this->assertNotSame($mesa->id, $outra->id);
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
