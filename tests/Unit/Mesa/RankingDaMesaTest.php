<?php

declare(strict_types=1);

namespace Tests\Unit\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Infra\Memoria\PlataformaEmMemoria;
use App\Mesa\RankingDaMesa;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class RankingDaMesaTest extends TestCase
{
    private Plataforma $plataforma;

    protected function setUp(): void
    {
        $this->plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-02 10:00:00')));
    }

    #[Test]
    public function somaTresDoisEUmETerceiroEQuartoValemOMesmo(): void
    {
        $mesa = $this->mesaCom('ana', 'bruno', 'carla', 'davi');
        $this->abrirPartida($mesa, 1);
        $this->jogarRodada($mesa->id, ['ana' => 1000, 'bruno' => 2000, 'carla' => 3000, 'davi' => 4000]);

        $this->assertSame([
            'ana' => 3,
            'bruno' => 2,
            'carla' => 1,
            'davi' => 1,
        ], $this->pontos($mesa->id));
    }

    #[Test]
    public function somaMaisDeUmaPartida(): void
    {
        $mesa = $this->mesaCom('ana', 'bruno');
        $this->abrirPartida($mesa, 1);
        $this->jogarRodada($mesa->id, ['ana' => 1000, 'bruno' => 2000]);
        $this->abrirPartida($mesa, 1);
        $this->jogarRodada($mesa->id, ['ana' => 3000, 'bruno' => 1000]);

        $this->assertSame(['ana' => 5, 'bruno' => 5], $this->pontos($mesa->id));
    }

    #[Test]
    public function quemAbandonouOuFoiRemovidoContinuaPontuando(): void
    {
        $mesa = $this->mesaCom('ana', 'bruno', 'carla');
        $this->abrirPartida($mesa, 2);
        $this->jogarRodada($mesa->id, ['ana' => 1000, 'bruno' => 2000, 'carla' => 3000]);
        $this->plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->partidaAtiva()->abandonar('carla'));
        $this->jogarRodada($mesa->id, ['ana' => 1000, 'bruno' => 4000]);

        $this->assertSame(['ana' => 3, 'bruno' => 2, 'carla' => 1], $this->pontos($mesa->id));

        $outra = $this->mesaCom('eva', 'felipe', 'gabi', 'heitor');
        $this->abrirPartida($outra, 2);
        $this->jogarRodada($outra->id, ['eva' => 1000, 'felipe' => 2000, 'gabi' => 3000, 'heitor' => 4000]);
        $this->plataforma->removerDaPartida($outra->id, 'heitor', 'eva');
        $this->jogarRodada($outra->id, ['eva' => 1000, 'felipe' => 2000, 'gabi' => 3000]);

        $this->assertSame(1, $this->pontos($outra->id)['heitor']);
        $this->assertNotNull($this->plataforma->mesa($outra->id)->partidas()[0]->vencedor());
    }

    #[Test]
    public function empateNoPrimeiroSomaTresParaCadaUmEOProximoFicaComDois(): void
    {
        $mesa = $this->mesaCom('ana', 'bruno', 'carla');
        $this->abrirPartida($mesa, 2);
        $this->plataforma->naMesa($mesa->id, static function (Mesa $m): void {
            $partida = $m->partidaAtiva();
            $partida->pularVez('ana', 'ana');
            $partida->iniciarTentativa('bruno');
            $partida->concluirTentativa('bruno', 1000);
            $partida->pularVez('carla', 'ana');
            $partida->abandonar('bruno');
            $partida->iniciarTentativa('ana');
            $partida->concluirTentativa('ana', 1000);
            $partida->iniciarTentativa('carla');
            $partida->concluirTentativa('carla', 2000);
        });

        $this->assertSame(['ana' => 3, 'bruno' => 3, 'carla' => 2], $this->pontos($mesa->id));
    }

    #[Test]
    public function empateNoTotalDoLimiteDaTresPontosParaCadaVencedor(): void
    {
        $mesa = $this->mesaCom('ana', 'bruno', 'carla');
        $this->abrirPartida($mesa, 1);
        $this->jogarRodada($mesa->id, ['ana' => 1000, 'bruno' => 1000, 'carla' => 2000]);

        $partida = $this->plataforma->mesa($mesa->id)->partidas()[0];
        $this->assertSame(['ana', 'bruno'], $partida->vencedores());
        $this->assertSame('ana', $partida->vencedor());
        $this->assertSame(['ana' => 3, 'bruno' => 3, 'carla' => 2], $this->pontos($mesa->id));
    }

    #[Test]
    public function semVencedorNaoEntraNoRanking(): void
    {
        $encerrada = $this->mesaCom('ana', 'bruno');
        $this->abrirPartida($encerrada, 1);
        $this->plataforma->encerrarPartida($encerrada->id, 'ana');

        $removida = $this->mesaCom('carla', 'davi');
        $this->abrirPartida($removida, 1);
        $this->plataforma->removerDaPartida($removida->id, 'davi', 'carla');

        $mesaFechada = $this->mesaCom('eva', 'felipe');
        $this->abrirPartida($mesaFechada, 1);
        $this->plataforma->encerrarMesa($mesaFechada->id, 'eva');

        $this->assertSame([], $this->pontos($encerrada->id));
        $this->assertSame([], $this->pontos($removida->id));
        $this->assertSame([], $this->pontos($mesaFechada->id));
        $this->assertNull($this->plataforma->mesa($encerrada->id)->partidas()[0]->vencedor());
        $this->assertSame(SituacaoPartida::Encerrada, $this->plataforma->mesa($removida->id)->partidas()[0]->situacao());
    }

    private function mesaCom(string ...$pessoas): Mesa
    {
        $admin = array_shift($pessoas);
        $mesa = $this->plataforma->criarMesa($admin, 'Grupo');
        foreach ($pessoas as $pessoa) {
            $this->plataforma->naMesa($mesa->id, static fn (Mesa $m) => $m->adicionarMembro($pessoa));
        }

        return $mesa;
    }

    private function abrirPartida(Mesa $mesa, int $limiteRodadas): void
    {
        $this->plataforma->criarPartida(
            $mesa->id,
            CatalogoDeJogos::configuracao('labirinto', null, null, $limiteRodadas),
            $mesa->admin(),
        );
        $this->plataforma->iniciarPartida($mesa->id, $mesa->admin());
    }

    /** @param array<string, int> $tempos */
    private function jogarRodada(string $mesaId, array $tempos): void
    {
        $this->plataforma->naMesa($mesaId, static function (Mesa $mesa) use ($tempos): void {
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

    /** @return array<string, int> */
    private function pontos(string $mesaId): array
    {
        return RankingDaMesa::pontos($this->plataforma->mesa($mesaId));
    }
}
