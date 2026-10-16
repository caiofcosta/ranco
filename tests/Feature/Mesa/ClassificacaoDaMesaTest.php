<?php

declare(strict_types=1);

namespace Tests\Feature\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Plataforma\Plataforma;
use App\Mesa\ClassificacaoDaMesa;
use App\Models\User;
use Database\Seeders\MapasLabirintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClassificacaoDaMesaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MapasLabirintoSeeder::class);
    }

    #[Test]
    public function semPartidasMostraTodosOsMembrosComZeroESemResultado(): void
    {
        [$mesa] = $this->mesaCom('Ana', 'Bruno Silva');

        $classificacao = ClassificacaoDaMesa::de($this->mesa($mesa));

        $this->assertFalse($classificacao->temResultado);
        $this->assertSame(['Ana', 'Bruno Silva'], array_column($classificacao->linhas, 'nome'));
        $this->assertSame(['Ana', 'Bruno'], array_column($classificacao->linhas, 'compacto'));
        $this->assertSame([0, 0], array_column($classificacao->linhas, 'pontos'));
        $this->assertSame([false, false], array_column($classificacao->linhas, 'primeiro'));
    }

    #[Test]
    public function reaproveitaOsPontosDoRankingComPosicaoDensaELiderNoEmpate(): void
    {
        [$mesa, $usuarios] = $this->mesaCom('Ana', 'Bruno', 'Carla');
        // Ana e Bruno empatam em primeiro (mesmo tempo); Carla fica atrás. Ninguém zera.
        $this->vencer($mesa, $usuarios, ['Ana' => 10000, 'Bruno' => 10000, 'Carla' => 20000]);

        $classificacao = ClassificacaoDaMesa::de($this->mesa($mesa));

        $this->assertTrue($classificacao->temResultado);
        $this->assertSame(['Ana', 'Bruno', 'Carla'], array_column($classificacao->linhas, 'nome'));
        $this->assertSame([3, 3, 2], array_column($classificacao->linhas, 'pontos'));
        $this->assertSame([1, 1, 2], array_column($classificacao->linhas, 'posicao'));
        $this->assertSame([true, true, false], array_column($classificacao->linhas, 'primeiro'));
    }

    #[Test]
    public function incluiMembroQueAindaNaoPontuouComZeroNoFim(): void
    {
        [$mesa, $usuarios] = $this->mesaCom('Ana', 'Bruno', 'Carla');
        $this->vencer($mesa, $usuarios, ['Ana' => 10000, 'Bruno' => 20000, 'Carla' => 30000]);
        // Davi entra só depois da partida: aparece com zero, em último.
        $davi = User::factory()->create(['name' => 'Davi']);
        app(Plataforma::class)->naMesa($mesa, static fn (Mesa $m) => $m->adicionarMembro($davi->identificadorNoDominio()));

        $classificacao = ClassificacaoDaMesa::de($this->mesa($mesa));

        $this->assertCount(4, $classificacao->linhas);
        $ultima = $classificacao->linhas[3];
        $this->assertSame('Davi', $ultima['nome']);
        $this->assertSame(0, $ultima['pontos']);
        $this->assertSame(4, $ultima['posicao']);
        $this->assertFalse($ultima['primeiro']);
        $this->assertSame([3, 2, 1, 0], array_column($classificacao->linhas, 'pontos'));
        $this->assertSame([1, 2, 3, 4], array_column($classificacao->linhas, 'posicao'));
    }

    /**
     * @return array{0: string, 1: array<string, User>}
     */
    private function mesaCom(string ...$nomes): array
    {
        $usuarios = [];
        foreach ($nomes as $nome) {
            $usuarios[$nome] = User::factory()->create(['name' => $nome]);
        }
        $plataforma = app(Plataforma::class);
        $admin = $usuarios[$nomes[0]];
        $mesa = $plataforma->criarMesa($admin->identificadorNoDominio(), 'Grupo');
        foreach (array_slice($nomes, 1) as $nome) {
            $plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($usuarios[$nome]->identificadorNoDominio()));
        }

        return [$mesa->id, $usuarios];
    }

    private function mesa(string $id): Mesa
    {
        return app(Plataforma::class)->mesa($id);
    }

    /**
     * @param  array<string, User>  $usuarios
     * @param  array<string, int>  $temposPorNome
     */
    private function vencer(string $mesaId, array $usuarios, array $temposPorNome): void
    {
        $plataforma = app(Plataforma::class);
        $admin = $plataforma->mesa($mesaId)->admin();
        $plataforma->criarPartida($mesaId, CatalogoDeJogos::configuracao('labirinto', null, null, 1), $admin);
        $plataforma->iniciarPartida($mesaId, $admin);

        $tempos = [];
        foreach ($temposPorNome as $nome => $tempo) {
            $tempos[$usuarios[$nome]->identificadorNoDominio()] = $tempo;
        }
        $plataforma->naMesa($mesaId, static function (Mesa $mesa) use ($tempos): void {
            $partida = $mesa->partidaAtiva();
            $rodada = $partida->rodadaAtual();
            while (
                $partida->situacao()->value === 'em_andamento'
                && $partida->rodadaAtual() === $rodada
                && $partida->vezAtual() !== null
            ) {
                $vez = $partida->vezAtual();
                $partida->iniciarTentativa($vez);
                $partida->concluirTentativa($vez, $tempos[$vez]);
            }
        });
    }
}
