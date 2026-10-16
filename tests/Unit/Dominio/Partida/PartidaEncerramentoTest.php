<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Partida;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\Dominio\Partida\MotivoEncerramento;
use App\Dominio\Partida\Origem;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoParticipacao;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Partida\SituacaoTentativa;
use App\Dominio\Partida\TipoEvento;
use App\Dominio\RegraViolada;
use Tests\Suporte\MontaPartidas;

final class PartidaEncerramentoTest extends TestCase
{
    use MontaPartidas;

    // Abandono

    #[Test]
    public function abandonoNaVezSemTentativaPassaAVez(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogar($partida, 'ana', 10);

        $partida->abandonar('bruno');

        $this->assertSame('carla', $partida->vezAtual());
        $this->assertSame(SituacaoParticipacao::Abandonada, $partida->situacaoDe('bruno'));
        $this->assertEventoRegistrado($partida, TipoEvento::AbandonouAPartida, Origem::Jogador);
    }

    #[Test]
    public function abandonoDuranteATentativaConsomeATentativa(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogar($partida, 'ana', 10);
        $partida->iniciarTentativa('bruno');

        $partida->abandonar('bruno');

        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'bruno')?->situacao);
        $this->assertSame('carla', $partida->vezAtual());
    }

    #[Test]
    public function quemAbandonouNaoJogaAsProximasRodadas(): void
    {
        $partida = $this->partidaIniciada();
        $partida->abandonar('carla');
        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 5]);

        $this->assertSame(['ana', 'bruno'], $partida->participantesDaRodada(2));
    }

    #[Test]
    public function adminNaoAbandonaApenasAPartida(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->abandonar('ana');
    }

    #[Test]
    public function partidaContinuaComDoisMesmoAbaixoDoMinimoParaComecar(): void
    {
        $partida = $this->partidaIniciada(configuracao: $this->configuracao(minimoJogadores: 3));

        $partida->abandonar('carla');

        $this->assertSame(SituacaoPartida::EmAndamento, $partida->situacao());
    }

    // W.O. e remoção

    #[Test]
    public function woQuandoTodosOsDemaisAbandonam(): void
    {
        $partida = $this->partidaIniciada();

        $partida->abandonar('bruno');
        $partida->abandonar('carla');

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::Desistencia, $partida->motivoEncerramento());
        $this->assertSame('ana', $partida->vencedor());
    }

    #[Test]
    public function remocaoDuranteAPartidaImpedeWo(): void
    {
        $partida = $this->partidaIniciada();

        $partida->removerParticipante('carla', por: 'ana');
        $partida->abandonar('bruno');

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::Remocoes, $partida->motivoEncerramento());
        $this->assertNull($partida->vencedor());
    }

    #[Test]
    public function remocaoNaConfiguracaoNaoContaParaWo(): void
    {
        $partida = Partida::criar($this->configuracao(), 'ana', $this->relogio);
        $partida->adicionarParticipante('bruno');
        $partida->adicionarParticipante('carla');
        $partida->removerParticipante('carla', por: 'ana');
        $partida->iniciar(por: 'ana');

        $partida->abandonar('bruno');

        $this->assertSame(MotivoEncerramento::Desistencia, $partida->motivoEncerramento());
        $this->assertSame('ana', $partida->vencedor());
    }

    #[Test]
    public function naoRemoveDuranteUmaTentativaEmAndamento(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogar($partida, 'ana', 10);
        $partida->iniciarTentativa('bruno');

        $this->expectException(RegraViolada::class);
        $partida->removerParticipante('bruno', por: 'ana');
    }

    #[Test]
    public function somenteAdminRemove(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->removerParticipante('carla', por: 'bruno');
    }

    #[Test]
    public function resultadosJaObtidosPermanecemAposARemocao(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogarFase($partida, ['ana' => 1, 'bruno' => 9, 'carla' => 5]);

        $partida->removerParticipante('bruno', por: 'ana');

        $this->assertSame(3, $partida->totalDe('bruno'));
        $this->assertSame(SituacaoParticipacao::Removida, $partida->situacaoDe('bruno'));
    }

    // Encerramento manual e abandono do admin

    #[Test]
    public function encerramentoManualSemTentativaEncerraNaHoraSemVencedor(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogarFase($partida, ['ana' => 10, 'bruno' => 5, 'carla' => 1]);

        $partida->encerrar(por: 'ana');

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::EncerradaPeloAdmin, $partida->motivoEncerramento());
        $this->assertNull($partida->vencedor());
        $this->assertSame(3, $partida->totalDe('ana'), 'O placar fica preservado.');
    }

    #[Test]
    public function encerramentoManualEsperaATentativaEmAndamentoTerminar(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');

        $partida->encerrar(por: 'ana');
        $this->assertSame(SituacaoPartida::EmAndamento, $partida->situacao());

        $partida->concluirTentativa('ana', resultado: 10);
        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(SituacaoTentativa::Concluida, $partida->tentativaDe(1, 'ana')?->situacao);
        $this->assertNull($partida->vencedor());
    }

    #[Test]
    public function encerramentoNaoPodeSerSolicitadoDuasVezes(): void
    {
        $partida = $this->partidaIniciada();
        $partida->iniciarTentativa('ana');
        $partida->encerrar(por: 'ana');

        $this->expectException(RegraViolada::class);
        $partida->encerrar(por: 'ana');
    }

    #[Test]
    public function somenteAdminEncerra(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->encerrar(por: 'bruno');
    }

    #[Test]
    public function abandonoDoAdminEncerraNaHoraSemVencedor(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogar($partida, 'ana', 10);
        $partida->iniciarTentativa('bruno');

        $partida->encerrarPorAbandonoDoAdmin();

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::AbandonoDoAdmin, $partida->motivoEncerramento());
        $this->assertNull($partida->vencedor());
        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'bruno')?->situacao);
    }

    #[Test]
    public function encerramentoDaMesaEncerraNaHoraSemVencedor(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogar($partida, 'ana', 10);
        $partida->iniciarTentativa('bruno');

        $partida->encerrarPorEncerramentoDaMesa();

        $this->assertSame(SituacaoPartida::Encerrada, $partida->situacao());
        $this->assertSame(MotivoEncerramento::EncerramentoDaMesa, $partida->motivoEncerramento());
        $this->assertNull($partida->vencedor());
        $this->assertSame(SituacaoTentativa::Interrompida, $partida->tentativaDe(1, 'bruno')?->situacao);
    }

    #[Test]
    public function partidaEncerradaNaoAceitaMaisAcoes(): void
    {
        $partida = $this->partidaIniciada();
        $partida->encerrar(por: 'ana');

        $this->expectException(RegraViolada::class);
        $partida->iniciarTentativa('ana');
    }

    // Entrada e retorno durante a partida

    #[Test]
    public function quemEntraDuranteAPartidaSoDisputaAPartirDaProximaRodadaNoFimDaOrdem(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogar($partida, 'ana', 10);

        $partida->admitir('davi', por: 'ana');
        $this->assertSame(['ana', 'bruno', 'carla'], $partida->ordem());
        $this->assertSame(['davi'], $partida->aguardandoProximaRodada());

        $this->jogarFase($partida, ['bruno' => 5, 'carla' => 1]);
        $this->assertSame(['ana', 'bruno', 'carla', 'davi'], $partida->participantesDaRodada(2));
        $this->assertSame(0, $partida->pontosNaRodada(1, 'davi'));
        $this->assertEventoRegistrado($partida, TipoEvento::EntrouNaPartida, Origem::Admin);
    }

    #[Test]
    public function quemAbandonouEVoltaEntraNoFimEMantemOsPontos(): void
    {
        $partida = $this->partidaIniciada();
        $this->jogarFase($partida, ['ana' => 1, 'bruno' => 9, 'carla' => 5]);

        $partida->abandonar('bruno');
        $partida->admitir('bruno', por: 'ana');
        $this->jogarFase($partida, ['ana' => 9, 'carla' => 5]);

        $this->assertSame(['ana', 'carla', 'bruno'], $partida->participantesDaRodada(3));
        $this->assertSame(3, $partida->totalDe('bruno'));
        $this->assertSame(0, $partida->pontosNaRodada(2, 'bruno'));
        $this->assertSame(SituacaoParticipacao::Ativa, $partida->situacaoDe('bruno'));
        $this->assertEventoRegistrado($partida, TipoEvento::RetornouAPartida, Origem::Admin);
    }

    #[Test]
    public function maximoDeJogadoresValeParaEntradaNoMeioDaPartida(): void
    {
        $partida = $this->partidaIniciada(configuracao: $this->configuracao(maximoJogadores: 3));

        $this->expectException(RegraViolada::class);
        $partida->admitir('davi', por: 'ana');
    }

    #[Test]
    public function somenteAdminAdmite(): void
    {
        $partida = $this->partidaIniciada();

        $this->expectException(RegraViolada::class);
        $partida->admitir('davi', por: 'bruno');
    }
}
