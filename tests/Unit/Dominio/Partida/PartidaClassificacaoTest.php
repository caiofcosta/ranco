<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Partida;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\Dominio\Partida\Criterio;
use App\Dominio\Partida\MotivoEncerramento;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\TipoEvento;
use Tests\Suporte\MontaPartidas;

final class PartidaClassificacaoTest extends TestCase
{
    use MontaPartidas;

    // Classificação da rodada

    #[Test]
    public function pontosPersonalizadosRespeitamEmpateEMetaNoFimDaRodada(): void
    {
        $config = \App\Dominio\Jogos\CatalogoDeJogos::configuracao('labirinto', null, 10, null, pontosPorPosicao: [10, 6, 3, 1]);
        $partida = $this->partidaIniciada(['ana', 'bruno', 'carla', 'davi'], $config);
        $this->jogar($partida, 'ana', 100);
        $this->assertSame(SituacaoPartida::EmAndamento, $partida->situacao());
        $this->jogarFase($partida, ['bruno' => 100, 'carla' => 200, 'davi' => 300]);
        $this->assertSame(10, $partida->totalDe('ana'));
        $this->assertSame(10, $partida->totalDe('bruno'));
        $this->assertSame(6, $partida->totalDe('carla'));
        $this->assertSame(3, $partida->totalDe('davi'));
        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(['ana', 'bruno'], $partida->vencedores());
    }

    #[Test]
    public function abaixoDoPrimeiroOEmpatePermaneceEmRankingDenso(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno', 'carla', 'davi']);

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 8, 'carla' => 8, 'davi' => 5]);

        $this->assertSame(1, $partida->posicaoNaRodada(1, 'ana'));
        $this->assertSame(2, $partida->posicaoNaRodada(1, 'bruno'));
        $this->assertSame(2, $partida->posicaoNaRodada(1, 'carla'));
        $this->assertSame(3, $partida->posicaoNaRodada(1, 'davi'));
        $this->assertSame(0, $partida->desempateAtual());
    }

    #[Test]
    public function pontosSeguemAPosicaoEMesmaPosicaoTemMesmaPontuacao(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno', 'carla', 'davi']);

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 8, 'carla' => 8, 'davi' => 5]);

        $this->assertSame(3, $partida->pontosNaRodada(1, 'ana'));
        $this->assertSame(2, $partida->pontosNaRodada(1, 'bruno'));
        $this->assertSame(2, $partida->pontosNaRodada(1, 'carla'));
        $this->assertSame(1, $partida->pontosNaRodada(1, 'davi'));
    }

    #[Test]
    public function emJogoDeTempoOMenorResultadoVence(): void
    {
        $partida = $this->partidaIniciada(configuracao: $this->configuracao(criterio: Criterio::MenorMelhor));

        $this->jogarFase($partida, ['ana' => 42_000, 'bruno' => 31_500, 'carla' => 55_000]);

        $this->assertSame(1, $partida->posicaoNaRodada(1, 'bruno'));
        $this->assertSame(2, $partida->posicaoNaRodada(1, 'ana'));
        $this->assertSame(3, $partida->posicaoNaRodada(1, 'carla'));
    }

    #[Test]
    public function vezPuladaValeZeroPontosESemPosicao(): void
    {
        $partida = $this->partidaIniciada();

        $this->jogar($partida, 'ana', 10);
        $partida->pularVez('bruno', por: 'ana');
        $this->jogar($partida, 'carla', 5);

        $this->assertNull($partida->posicaoNaRodada(1, 'bruno'));
        $this->assertSame(0, $partida->pontosNaRodada(1, 'bruno'));
        $this->assertSame(2, $partida->posicaoNaRodada(1, 'carla'));
    }

    #[Test]
    public function emJogoDePontosATentativaInterrompidaConcorreComOProgresso(): void
    {
        $partida = $this->partidaIniciada();

        $partida->iniciarTentativa('ana');
        $partida->registrarProgresso('ana', 50);
        $partida->interromperTentativa('ana');
        $this->jogarFase($partida, ['bruno' => 40, 'carla' => 30]);

        $this->assertSame(1, $partida->posicaoNaRodada(1, 'ana'));
        $this->assertSame(3, $partida->pontosNaRodada(1, 'ana'));
    }

    #[Test]
    public function emJogoDeTempoQuemNaoTerminouFicaSemPosicao(): void
    {
        $partida = $this->partidaIniciada(configuracao: $this->configuracao(criterio: Criterio::MenorMelhor));

        $partida->iniciarTentativa('ana');
        $partida->registrarProgresso('ana', 1_000);
        $partida->interromperTentativa('ana');
        $this->jogarFase($partida, ['bruno' => 40_000, 'carla' => 30_000]);

        $this->assertNull($partida->posicaoNaRodada(1, 'ana'));
        $this->assertSame(0, $partida->pontosNaRodada(1, 'ana'));
        $this->assertSame(1, $partida->posicaoNaRodada(1, 'carla'));
    }

    #[Test]
    public function identificadoresNumericosFuncionam(): void
    {
        $partida = $this->partidaIniciada(['10', '20', '30']);

        $this->jogarFase($partida, ['10' => 5, '20' => 9, '30' => 7]);

        $this->assertSame(1, $partida->posicaoNaRodada(1, '20'));
        $this->assertSame(3, $partida->totalDe('20'));
        $this->assertSame('10', $partida->vezAtual());
    }

    #[Test]
    public function empateNoPrimeiroDivideAColocacaoSemDesempate(): void
    {
        $partida = $this->partidaIniciada();

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 10, 'carla' => 5]);

        $this->assertSame(0, $partida->desempateAtual());
        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame(1, $partida->posicaoNaRodada(1, 'ana'));
        $this->assertSame(1, $partida->posicaoNaRodada(1, 'bruno'));
        $this->assertSame(2, $partida->posicaoNaRodada(1, 'carla'));
        $this->assertSame(3, $partida->pontosNaRodada(1, 'ana'));
        $this->assertSame(3, $partida->pontosNaRodada(1, 'bruno'));
        $this->assertSame(2, $partida->pontosNaRodada(1, 'carla'));
        foreach ($partida->eventos() as $evento) {
            $this->assertNotSame(TipoEvento::DesempateIniciado, $evento->tipo);
        }
    }

    #[Test]
    public function classificacaoFinalEmpataNoSegundoEOproximoFicaEmTerceiro(): void
    {
        $partida = $this->partidaIniciada(
            ['ana', 'bruno', 'carla', 'davi'],
            $this->configuracao(limiteRodadas: 1),
        );

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 8, 'carla' => 8, 'davi' => 5]);

        $this->assertSame([
            ['pessoa' => 'ana', 'colocacao' => 1, 'empate' => false],
            ['pessoa' => 'bruno', 'colocacao' => 2, 'empate' => true],
            ['pessoa' => 'carla', 'colocacao' => 2, 'empate' => true],
            ['pessoa' => 'davi', 'colocacao' => 3, 'empate' => false],
        ], $partida->classificacao());
    }

    #[Test]
    public function classificacaoFinalEmpataNoTerceiro(): void
    {
        $partida = $this->partidaIniciada(
            ['ana', 'bruno', 'carla', 'davi'],
            $this->configuracao(limiteRodadas: 1),
        );

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 8, 'carla' => 4, 'davi' => 4]);

        $this->assertSame([
            ['pessoa' => 'ana', 'colocacao' => 1, 'empate' => false],
            ['pessoa' => 'bruno', 'colocacao' => 2, 'empate' => false],
            ['pessoa' => 'carla', 'colocacao' => 3, 'empate' => true],
            ['pessoa' => 'davi', 'colocacao' => 3, 'empate' => true],
        ], $partida->classificacao());
    }

    #[Test]
    public function doisParticipantesFicamEmPrimeiroESegundo(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno'], $this->configuracao(limiteRodadas: 1));

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 4]);

        $this->assertSame([
            ['pessoa' => 'ana', 'colocacao' => 1, 'empate' => false],
            ['pessoa' => 'bruno', 'colocacao' => 2, 'empate' => false],
        ], $partida->classificacao());
    }

    #[Test]
    public function woColocaOVencedorSozinhoEmPrimeiro(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno'], $this->configuracao(limiteRodadas: 1));

        $partida->abandonar('bruno');

        $this->assertSame(MotivoEncerramento::Desistencia, $partida->motivoEncerramento());
        $this->assertSame(['ana'], $partida->vencedores());
        $this->assertSame('ana', $partida->vencedor());
        $this->assertSame([
            ['pessoa' => 'ana', 'colocacao' => 1, 'empate' => false],
            ['pessoa' => 'bruno', 'colocacao' => 2, 'empate' => false],
        ], $partida->classificacao());
    }

    #[Test]
    public function empateNoPrimeiroDaClassificacaoQuandoQuemSaiuTemAMesmaPontuacao(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno', 'carla'], $this->configuracao(limiteRodadas: 2));

        $partida->pularVez('ana', 'ana');
        $this->jogar($partida, 'bruno', 10);
        $partida->pularVez('carla', 'ana');
        $partida->abandonar('bruno');
        $this->jogarFase($partida, ['ana' => 10, 'carla' => 4]);

        $this->assertSame(['ana', 'bruno'], $partida->vencedores());
        $this->assertSame('ana', $partida->vencedor());
        $this->assertSame([
            ['pessoa' => 'ana', 'colocacao' => 1, 'empate' => true],
            ['pessoa' => 'bruno', 'colocacao' => 1, 'empate' => true],
            ['pessoa' => 'carla', 'colocacao' => 2, 'empate' => false],
        ], $partida->classificacao());
    }

    #[Test]
    public function quemSaiuComTotalMaiorFicaSozinhoEmPrimeiro(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno', 'carla'], $this->configuracao(limiteRodadas: 1));

        $this->jogar($partida, 'ana', 5);
        $this->jogar($partida, 'bruno', 10);
        $partida->abandonar('bruno');
        $this->jogar($partida, 'carla', 1);

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::Normal, $partida->motivoEncerramento());
        $this->assertSame(['bruno'], $partida->vencedores());
        $this->assertSame('bruno', $partida->vencedor());
        $this->assertSame([
            ['pessoa' => 'bruno', 'colocacao' => 1, 'empate' => false],
            ['pessoa' => 'ana', 'colocacao' => 2, 'empate' => false],
            ['pessoa' => 'carla', 'colocacao' => 3, 'empate' => false],
        ], $partida->classificacao());
    }

    #[Test]
    public function semVencedorNaoTemClassificacao(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno'], $this->configuracao(limiteRodadas: 1));

        $partida->encerrar('ana');

        $this->assertSame([], $partida->vencedores());
        $this->assertNull($partida->vencedor());
        $this->assertSame([], $partida->classificacao());
    }

    // Término

    #[Test]
    public function metaDePontosEncerraAoFimDaRodada(): void
    {
        $partida = $this->partidaIniciada(configuracao: $this->configuracao(metaPontos: 5));

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 5, 'carla' => 1]);
        $this->assertSame(SituacaoPartida::EmAndamento, $partida->situacao());

        $this->jogar($partida, 'ana', 10);
        $this->assertSame(SituacaoPartida::EmAndamento, $partida->situacao(), 'A meta só é avaliada no fim da rodada.');

        $this->jogarFase($partida, ['bruno' => 5, 'carla' => 1]);
        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::Normal, $partida->motivoEncerramento());
        $this->assertSame('ana', $partida->vencedor());
    }

    #[Test]
    public function limiteDeRodadasEncerraComOMaiorTotal(): void
    {
        $partida = $this->partidaIniciada(configuracao: $this->configuracao(limiteRodadas: 2));

        $this->jogarFase($partida, ['ana' => 1, 'bruno' => 9, 'carla' => 5]);
        $this->jogarFase($partida, ['ana' => 9, 'bruno' => 8, 'carla' => 1]);

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame('bruno', $partida->vencedor());
        $this->assertSame(5, $partida->totalDe('bruno'));
    }

    #[Test]
    public function metaELimiteDeRodadasNaoCoexistemNaMesmaPartida(): void
    {
        $this->expectException(\App\Dominio\RegraViolada::class);

        $this->configuracao(metaPontos: 100, limiteRodadas: 1);
    }

    #[Test]
    public function empateNaMetaDivideOPrimeiroSemProrrogar(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno', 'carla'], $this->configuracao(metaPontos: 3));

        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 10, 'carla' => 1]);

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::Normal, $partida->motivoEncerramento());
        $this->assertSame(1, $partida->rodadaAtual());
        $this->assertSame(['ana', 'bruno'], $partida->vencedores());
        $this->assertSame('ana', $partida->vencedor());
        $this->assertSame([
            ['pessoa' => 'ana', 'colocacao' => 1, 'empate' => true],
            ['pessoa' => 'bruno', 'colocacao' => 1, 'empate' => true],
            ['pessoa' => 'carla', 'colocacao' => 2, 'empate' => false],
        ], $partida->classificacao());
    }

    #[Test]
    public function empateNoLimiteDeRodadasDivideOPrimeiroSemProrrogar(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno', 'carla'], $this->configuracao(limiteRodadas: 2));

        $this->jogarFase($partida, ['ana' => 9, 'bruno' => 1, 'carla' => 1]);
        $this->jogarFase($partida, ['ana' => 1, 'bruno' => 9, 'carla' => 1]);

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(2, $partida->rodadaAtual());
        $this->assertSame(['ana', 'bruno'], $partida->vencedores());
        $this->assertSame('ana', $partida->vencedor());
        $this->assertSame([
            ['pessoa' => 'ana', 'colocacao' => 1, 'empate' => true],
            ['pessoa' => 'bruno', 'colocacao' => 1, 'empate' => true],
            ['pessoa' => 'carla', 'colocacao' => 2, 'empate' => false],
        ], $partida->classificacao());
    }

    #[Test]
    public function partidaAntigaComUmVencedorContinuaLegivel(): void
    {
        $partida = $this->partidaIniciada(['ana', 'bruno'], $this->configuracao(limiteRodadas: 1));
        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 1]);
        $lida = $this->lerComoAntiga($partida, 'ana');

        $this->assertSame(['ana'], $lida->vencedores());
        $this->assertSame('ana', $lida->vencedor());
        $this->assertSame('ana', $lida->classificacao()[0]['pessoa']);

        $contradiz = $this->lerComoAntiga($partida, 'bruno');
        $this->assertSame(['ana'], $contradiz->vencedores());

        $semVencedor = $this->partidaIniciada(['ana', 'bruno'], $this->configuracao(limiteRodadas: 1));
        $semVencedor->encerrar('ana');
        $lidaSem = $this->lerComoAntiga($semVencedor, null);

        $this->assertSame([], $lidaSem->vencedores());
        $this->assertNull($lidaSem->vencedor());
        $this->assertSame([], $lidaSem->classificacao());
    }

    private function lerComoAntiga(Partida $partida, ?string $vencedor): Partida
    {
        $dados = $partida->__serialize();
        unset($dados['vencedores']);
        $dados['vencedor'] = $vencedor;

        $lida = (new \ReflectionClass(Partida::class))->newInstanceWithoutConstructor();
        $lida->__unserialize($dados);

        return $lida;
    }
}
