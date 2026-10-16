<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Conquista;

use App\Dominio\Conquista\PremiadosDoRanking;
use App\Dominio\Mesa\SituacaoMesa;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PremiadosDoRankingTest extends TestCase
{
    #[Test]
    public function maiorPontuacaoGanhaVitoriaMesmoForaDaPrimeiraLinha(): void
    {
        $this->assertSame([
            ['pessoa' => 'ana', 'tipo' => 'vitoria'],
            ['pessoa' => 'bruno', 'tipo' => 'segundo'],
        ], PremiadosDoRanking::de(
            SituacaoMesa::Encerrada,
            [['id' => 'bruno', 'nome' => 'Bruno', 'pontos' => 3], ['id' => 'ana', 'nome' => 'Ana', 'pontos' => 5]],
        ));
    }

    #[Test]
    public function cadaEmpatadoNoTopoGanhaUmTrofeuInteiro(): void
    {
        $this->assertSame([
            ['pessoa' => 'ana', 'tipo' => 'empate'],
            ['pessoa' => 'bruno', 'tipo' => 'empate'],
            ['pessoa' => 'carla', 'tipo' => 'segundo'],
        ], PremiadosDoRanking::de(SituacaoMesa::Encerrada, [
            ['id' => 'ana', 'nome' => 'Ana', 'pontos' => 5],
            ['id' => 'bruno', 'nome' => 'Bruno', 'pontos' => 5],
            ['id' => 'carla', 'nome' => 'Carla', 'pontos' => 2],
        ]));
    }

    #[Test]
    public function segundoETerceiroSaoPelaColocacaoDensa(): void
    {
        $this->assertSame([
            ['pessoa' => 'ana', 'tipo' => 'vitoria'],
            ['pessoa' => 'bruno', 'tipo' => 'segundo'],
            ['pessoa' => 'carla', 'tipo' => 'terceiro'],
        ], PremiadosDoRanking::de(SituacaoMesa::Encerrada, [
            ['id' => 'ana', 'nome' => 'Ana', 'pontos' => 9],
            ['id' => 'bruno', 'nome' => 'Bruno', 'pontos' => 6],
            ['id' => 'carla', 'nome' => 'Carla', 'pontos' => 3],
            ['id' => 'dani', 'nome' => 'Dani', 'pontos' => 1],
        ]));
    }

    #[Test]
    public function empateNoSegundoDaUmTrofeuDeSegundoParaCadaUm(): void
    {
        $this->assertSame([
            ['pessoa' => 'ana', 'tipo' => 'vitoria'],
            ['pessoa' => 'bruno', 'tipo' => 'segundo'],
            ['pessoa' => 'carla', 'tipo' => 'segundo'],
        ], PremiadosDoRanking::de(SituacaoMesa::Encerrada, [
            ['id' => 'ana', 'nome' => 'Ana', 'pontos' => 9],
            ['id' => 'bruno', 'nome' => 'Bruno', 'pontos' => 6],
            ['id' => 'carla', 'nome' => 'Carla', 'pontos' => 6],
        ]));
    }

    #[Test]
    public function empateNoPrimeiroNaoOcupaOSegundo(): void
    {
        $this->assertSame([
            ['pessoa' => 'ana', 'tipo' => 'empate'],
            ['pessoa' => 'bruno', 'tipo' => 'empate'],
            ['pessoa' => 'carla', 'tipo' => 'segundo'],
        ], PremiadosDoRanking::de(SituacaoMesa::Encerrada, [
            ['id' => 'ana', 'nome' => 'Ana', 'pontos' => 9],
            ['id' => 'bruno', 'nome' => 'Bruno', 'pontos' => 9],
            ['id' => 'carla', 'nome' => 'Carla', 'pontos' => 4],
        ]));
    }

    #[Test]
    public function empateNoTerceiroDaUmTrofeuDeTerceiroParaCadaUm(): void
    {
        $this->assertSame([
            ['pessoa' => 'ana', 'tipo' => 'vitoria'],
            ['pessoa' => 'bruno', 'tipo' => 'segundo'],
            ['pessoa' => 'carla', 'tipo' => 'terceiro'],
            ['pessoa' => 'dani', 'tipo' => 'terceiro'],
        ], PremiadosDoRanking::de(SituacaoMesa::Encerrada, [
            ['id' => 'ana', 'nome' => 'Ana', 'pontos' => 9],
            ['id' => 'bruno', 'nome' => 'Bruno', 'pontos' => 6],
            ['id' => 'carla', 'nome' => 'Carla', 'pontos' => 3],
            ['id' => 'dani', 'nome' => 'Dani', 'pontos' => 3],
        ]));
    }

    #[Test]
    public function mesaAbertaOuSemPontosNaoPremia(): void
    {
        $linha = ['id' => 'ana', 'nome' => 'Ana', 'pontos' => 3];
        $this->assertSame([], PremiadosDoRanking::de(SituacaoMesa::Aberta, [$linha]));
        $this->assertSame([], PremiadosDoRanking::de(SituacaoMesa::Encerrada, []));
        $linha['pontos'] = 0;
        $this->assertSame([], PremiadosDoRanking::de(SituacaoMesa::Encerrada, [$linha]));
    }
}
