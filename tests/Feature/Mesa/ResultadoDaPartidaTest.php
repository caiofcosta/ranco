<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Livewire\Mesa\MostrarMesa;
use App\Mesa\HistoricoDaMesa;
use App\Mesa\ResultadoDaPartida;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ResultadoDaPartidaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
    }

    #[Test]
    public function classificacao_usa_colocacao_pontos_da_partida_e_medalhas_gravadas(): void
    {
        [$mesa, $ids] = $this->mesa(['Ana', 'Bruno', 'Carla', 'Diego']);
        $this->jogarRodada($mesa, [$ids['Ana'] => 10000, $ids['Bruno'] => 20000, $ids['Carla'] => 30000, $ids['Diego'] => 40000]);

        $resultado = $this->resultado($mesa, $ids['Ana']);

        $this->assertSame('Labirinto', $resultado->jogo);
        $this->assertSame(1, $resultado->numero);
        $this->assertSame(['Ana', 'Bruno', 'Carla', 'Diego'], array_column($resultado->classificacao, 'nome'));
        $this->assertSame([1, 2, 3, 4], array_column($resultado->classificacao, 'colocacao'));
        $this->assertSame([3, 2, 1, 0], array_column($resultado->classificacao, 'pontos'));
        $this->assertSame('images/medalhas/medalha-ouro.png', $resultado->classificacao[0]['medalha']['arquivo']);
        $this->assertSame('images/medalhas/medalha-prata.png', $resultado->classificacao[1]['medalha']['arquivo']);
        $this->assertSame('images/medalhas/medalha-bronze.png', $resultado->classificacao[2]['medalha']['arquivo']);
        $this->assertNull($resultado->classificacao[3]['medalha']);
        $this->assertSame('Ana', $resultado->partida->vencedorNome);
        $this->assertNotNull($resultado->partida->fraseDaMedalha);
    }

    #[Test]
    public function rodadas_mostram_posicao_tempo_e_pontos_gravados(): void
    {
        [$mesa, $ids] = $this->mesa(['Ana', 'Bruno', 'Carla'], limite: 2);
        $this->jogarRodada($mesa, [$ids['Ana'] => 30000, $ids['Bruno'] => 10000, $ids['Carla'] => 20000]);
        $this->jogarRodada($mesa, [$ids['Ana'] => 10000, $ids['Bruno'] => 30000, $ids['Carla'] => 20000]);

        $rodadas = $this->resultado($mesa, $ids['Ana'])->rodadas;

        $this->assertSame([1, 2], array_column($rodadas, 'numero'));
        $this->assertSame(['Bruno', 'Carla', 'Ana'], array_column($rodadas[0]['linhas'], 'nome'));
        $this->assertSame([1, 2, 3], array_column($rodadas[0]['linhas'], 'posicao'));
        $this->assertSame(['10,000 s', '20,000 s', '30,000 s'], array_column($rodadas[0]['linhas'], 'resultado'));
        $this->assertSame([3, 2, 1], array_column($rodadas[0]['linhas'], 'pontos'));
        $this->assertSame(['Ana', 'Carla', 'Bruno'], array_column($rodadas[1]['linhas'], 'nome'));
    }

    #[Test]
    public function empate_divide_a_colocacao_e_usa_a_medalha_dupla(): void
    {
        [$mesa, $ids] = $this->mesa(['Ana', 'Bruno', 'Carla']);
        $this->jogarRodada($mesa, [$ids['Ana'] => 10000, $ids['Bruno'] => 10000, $ids['Carla'] => 20000]);

        $resultado = $this->resultado($mesa, $ids['Ana']);
        $linhas = $resultado->classificacao;

        $this->assertSame([1, 1, 2], array_column($linhas, 'colocacao'));
        $this->assertSame([true, true, false], array_column($linhas, 'empate'));
        $this->assertSame('images/medalhas/medalha-dupla-ouro.png', $linhas[0]['medalha']['arquivo']);
        $this->assertSame('images/medalhas/medalha-dupla-ouro.png', $linhas[1]['medalha']['arquivo']);
        $this->assertSame('images/medalhas/medalha-prata.png', $linhas[2]['medalha']['arquivo']);
        $this->assertTrue($resultado->partida->variosVencedores);
    }

    #[Test]
    public function partida_sem_vencedor_nao_tem_colocacao_nem_medalha(): void
    {
        [$mesa, $ids] = $this->mesa(['Ana', 'Bruno']);
        app(Plataforma::class)->encerrarPartida($mesa->id, $ids['Ana']);

        $resultado = $this->resultado($mesa, $ids['Ana']);

        $this->assertNull($resultado->partida->vencedorNome);
        $this->assertSame([null, null], array_column($resultado->classificacao, 'colocacao'));
        $this->assertSame([null, null], array_column($resultado->classificacao, 'medalha'));
        $this->assertSame(['Não jogou', 'Não jogou'], array_column($resultado->rodadas[0]['linhas'], 'resultado'));
    }

    #[Test]
    public function modal_tem_a_versao_do_celular_e_mantem_a_do_desktop(): void
    {
        [$mesa, $ids, $usuarios] = $this->mesa(['Ana', 'Bruno', 'Carla']);
        $this->jogarRodada($mesa, [$ids['Ana'] => 10000, $ids['Bruno'] => 10000, $ids['Carla'] => 20000]);

        $this->actingAs($usuarios['Bruno']);
        Livewire::test(MostrarMesa::class, ['id' => $mesa->id, 'resultado' => 1])
            ->assertSee('data-test="resultado-celular"', false)
            ->assertSee('Fim de jogo!')->assertSee('Labirinto · Partida 1')
            ->assertSee('Ana e Bruno venceram.')->assertSee('data-test="provocacao-do-resultado"', false)
            ->assertSee('Resultado da partida')->assertSee('Pontos recebidos')
            ->assertSee('+3 pts')->assertSee('+2 pts')->assertSee('· empate')
            ->assertSee('medalha-dupla-ouro.png')->assertSee('Ver rodadas')->assertSee('Rodada 1')->assertSee('10,000 s')
            ->assertSee('Fim da partida')->assertSee('Resultado final')->assertSee('3 pontos')
            ->assertDontSee('data-test="frase-da-medalha"', false);
    }

    private function resultado(Mesa $mesa, string $usuario): ResultadoDaPartida
    {
        $atual = app(Plataforma::class)->mesa($mesa->id);
        $resultado = ResultadoDaPartida::de($atual, $usuario, 1, HistoricoDaMesa::de($atual));
        $this->assertNotNull($resultado);

        return $resultado;
    }

    /**
     * @param  list<string>  $nomes  O primeiro é o admin.
     * @return array{Mesa, array<string, string>, array<string, User>}
     */
    private function mesa(array $nomes, int $limite = 1): array
    {
        $usuarios = [];
        $ids = [];
        foreach ($nomes as $nome) {
            $usuarios[$nome] = User::factory()->create(['name' => $nome]);
            $ids[$nome] = $usuarios[$nome]->identificadorNoDominio();
        }
        $admin = $ids[$nomes[0]];
        $mesa = app(Plataforma::class)->criarMesa($admin);
        app(Plataforma::class)->naMesa($mesa->id, static function (Mesa $m) use ($ids, $admin, $limite): void {
            foreach ($ids as $id) {
                if ($id !== $admin) {
                    $m->adicionarMembro($id);
                }
            }
            $m->novaPartida(CatalogoDeJogos::configuracao('labirinto', 120, null, $limite), $admin)->iniciar($admin);
        });

        return [$mesa, $ids, $usuarios];
    }

    /** @param array<string, int> $tempos */
    private function jogarRodada(Mesa $mesa, array $tempos): void
    {
        app(Plataforma::class)->naMesa($mesa->id, static function (Mesa $m) use ($tempos): void {
            $partida = $m->partidaAtiva();
            $rodada = $partida->rodadaAtual();
            while ($partida->situacao()->value === 'em_andamento' && $partida->rodadaAtual() === $rodada && $partida->vezAtual() !== null) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });
    }
}
