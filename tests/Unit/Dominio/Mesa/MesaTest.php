<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Mesa;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Mesa\SituacaoNaMesa;
use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\MotivoEncerramento;
use App\Dominio\Partida\SituacaoParticipacao;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\RegraViolada;
use Tests\Suporte\RelogioFalso;

final class MesaTest extends TestCase
{
    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-01 10:00:00'));
    }

    #[Test]
    public function lugaresNaoExpulsamPessoasENaoPermitemLotacaoMaior(): void
    {
        $mesa = Mesa::criar('lugares', 'ana', $this->relogio);
        $mesa->adicionarMembro('bia');
        $mesa->adicionarMembro('caio');
        try {
            $mesa->configurarLugares(2, 'ana');
            $this->fail('Não deve esconder pessoas sentadas.');
        } catch (RegraViolada) {
            $this->assertCount(3, $mesa->membrosAtivos());
        }
        $mesa->configurarLugares(3, 'ana');
        foreach (['remoto', 'local'] as $tipo) {
            try {
                $tipo === 'remoto'
                    ? $mesa->adicionarMembro('dani')
                    : $mesa->cadastrarParticipanteLocal('Dani', 'dani@example.test', 'ana');
                $this->fail('Não deve exceder a lotação.');
            } catch (RegraViolada) {
                $this->assertCount(3, $mesa->membrosAtivos());
            }
        }
        $restaurada = unserialize(serialize($mesa));
        $this->assertSame(3, $restaurada->quantidadeLugares());
    }

    #[Test]
    public function criadorDaMesaEOAdminEPrimeiroMembro(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio);

        $this->assertSame('ana', $mesa->admin());
        $this->assertSame(['ana'], $mesa->membrosAtivos());
        $this->assertSame(SituacaoMesa::Aberta, $mesa->situacao());
        $this->assertSame('', $mesa->nome());
    }

    #[Test]
    public function criaComNomeESoOAdminRenomeia(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio, 'Família (infelizmente)');

        $this->assertSame('Família (infelizmente)', $mesa->nome());

        $mesa->renomear('Clube do ranço', por: 'ana');
        $this->assertSame('Clube do ranço', $mesa->nome());

        $this->expectException(RegraViolada::class);
        $mesa->renomear('Outro nome', por: 'bruno');
    }

    #[Test]
    public function nomeVazioEPermitidoEAcimaDeQuarentaNao(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio, '   ');
        $this->assertSame('', $mesa->nome());

        $this->expectException(RegraViolada::class);
        $mesa->renomear(str_repeat('a', 41), por: 'ana');
    }

    #[Test]
    public function mesaEncerradaMantemONomeENaoRenomeia(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio, 'Grupo do churrasco');
        $mesa->encerrar(por: 'ana');

        $this->assertSame('Grupo do churrasco', $mesa->nome());
        $this->expectException(RegraViolada::class);
        $mesa->renomear('Outro', por: 'ana');
    }

    #[Test]
    public function ordemDaPartidaNasceDaOrdemDeEntradaNaMesa(): void
    {
        $mesa = $this->mesaCom('carla', 'bruno');

        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');

        $this->assertSame(['ana', 'carla', 'bruno'], $partida->ordem());
    }

    #[Test]
    public function somenteUmaPartidaAtivaPorMesa(): void
    {
        $mesa = $this->mesaCom('bruno');
        $mesa->novaPartida($this->configuracao(), por: 'ana');

        $this->expectException(RegraViolada::class);
        $mesa->novaPartida($this->configuracao(), por: 'ana');
    }

    #[Test]
    public function depoisDeEncerrarAPartidaOAdminAbreOutraEOHistoricoFica(): void
    {
        $mesa = $this->mesaCom('bruno');
        $primeira = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $primeira->iniciar(por: 'ana');

        $mesa->encerrarPartida(por: 'ana');
        $segunda = $mesa->novaPartida($this->configuracao(), por: 'ana');

        $this->assertSame([$primeira, $segunda], $mesa->partidas());
        $this->assertSame(SituacaoPartida::Encerrada, $primeira->situacao());
        $this->assertSame($segunda, $mesa->partidaAtiva());
        $this->assertSame(['ana', 'bruno'], $mesa->membrosAtivos(), 'Encerrar a partida não tira ninguém da mesa.');
    }

    #[Test]
    public function somenteAdminCriaPartida(): void
    {
        $mesa = $this->mesaCom('bruno');

        $this->expectException(RegraViolada::class);
        $mesa->novaPartida($this->configuracao(), por: 'bruno');
    }

    #[Test]
    public function quemEntraNaMesaDuranteAPartidaSoDisputaQuandoAdmitidoNaProximaRodada(): void
    {
        $mesa = $this->mesaCom('bruno');
        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $partida->iniciar(por: 'ana');

        $mesa->adicionarMembro('carla');
        $this->assertFalse($partida->estaNaDisputa('carla'));

        $mesa->admitirNaPartida('carla', por: 'ana');
        $this->assertSame(['carla'], $partida->aguardandoProximaRodada());
    }

    #[Test]
    public function removerDaPartidaMantemNaMesa(): void
    {
        $mesa = $this->mesaCom('bruno', 'carla');
        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $partida->iniciar(por: 'ana');

        $mesa->removerDaPartida('carla', por: 'ana');

        $this->assertSame(SituacaoParticipacao::Removida, $partida->situacaoDe('carla'));
        $this->assertTrue($mesa->membroAtivo('carla'));
    }

    #[Test]
    public function removerDaMesaTambemRemoveDaPartidaAtiva(): void
    {
        $mesa = $this->mesaCom('bruno', 'carla');
        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $partida->iniciar(por: 'ana');

        $mesa->removerDaMesa('carla', por: 'ana');

        $this->assertSame(SituacaoNaMesa::Removida, $mesa->situacaoDe('carla'));
        $this->assertSame(SituacaoParticipacao::Removida, $partida->situacaoDe('carla'));
    }

    #[Test]
    public function abandonarSoAPartidaPermaneceNaMesa(): void
    {
        $mesa = $this->mesaCom('bruno', 'carla');
        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $partida->iniciar(por: 'ana');

        $mesa->abandonarPartida('carla');

        $this->assertSame(SituacaoParticipacao::Abandonada, $partida->situacaoDe('carla'));
        $this->assertTrue($mesa->membroAtivo('carla'));
    }

    #[Test]
    public function abandonarAMesaSaiTambemDaPartida(): void
    {
        $mesa = $this->mesaCom('bruno', 'carla');
        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $partida->iniciar(por: 'ana');

        $mesa->abandonar('carla');

        $this->assertSame(SituacaoNaMesa::Abandonada, $mesa->situacaoDe('carla'));
        $this->assertSame(SituacaoParticipacao::Abandonada, $partida->situacaoDe('carla'));
        $this->assertSame(SituacaoMesa::Aberta, $mesa->situacao());
    }

    #[Test]
    public function adminAbandonaAMesaEEncerraMesaEPartidaSemVencedor(): void
    {
        $mesa = $this->mesaCom('bruno', 'carla');
        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $partida->iniciar(por: 'ana');

        $mesa->abandonar('ana');

        $this->assertSame(SituacaoMesa::Encerrada, $mesa->situacao());
        $this->assertSame(MotivoEncerramento::AbandonoDoAdmin, $partida->motivoEncerramento());
        $this->assertNull($partida->vencedor());
        $this->assertFalse($mesa->membroAtivo('bruno'), 'Os demais ficam livres para entrar em outra mesa.');
    }

    #[Test]
    public function adminEncerraAMesaSemAbandonoESemVencedor(): void
    {
        $mesa = $this->mesaCom('bruno', 'carla');
        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');
        $partida->iniciar(por: 'ana');

        $mesa->encerrar(por: 'ana');

        $this->assertSame(SituacaoMesa::Encerrada, $mesa->situacao());
        $this->assertSame(MotivoEncerramento::EncerramentoDaMesa, $partida->motivoEncerramento());
        $this->assertNull($partida->vencedor());
        $this->assertSame(SituacaoNaMesa::Ativa, $mesa->situacaoDe('ana'));
        $this->assertFalse($mesa->membroAtivo('ana'));
        $this->assertFalse($mesa->membroAtivo('bruno'));
    }

    #[Test]
    public function somenteAdminEncerraAMesa(): void
    {
        $mesa = $this->mesaCom('bruno');

        $this->expectException(RegraViolada::class);
        $mesa->encerrar(por: 'bruno');
    }

    #[Test]
    public function mesaEncerradaNaoAceitaNovosMembros(): void
    {
        $mesa = $this->mesaCom('bruno');
        $mesa->abandonar('ana');

        $this->expectException(RegraViolada::class);
        $mesa->adicionarMembro('carla');
    }

    #[Test]
    public function quemSaiuEVoltaParaAMesaEntraNoFimDaOrdemDeEntrada(): void
    {
        $mesa = $this->mesaCom('bruno', 'carla');
        $mesa->abandonar('bruno');

        $mesa->adicionarMembro('bruno');

        $this->assertSame(['ana', 'carla', 'bruno'], $mesa->membrosAtivos());
    }

    // Participante local

    #[Test]
    public function adminCadastraParticipanteLocalQueEntraNaOrdem(): void
    {
        $mesa = $this->mesaCom();
        $local = $mesa->cadastrarParticipanteLocal('Bruno', 'bruno@exemplo.com', por: 'ana');

        $partida = $mesa->novaPartida($this->configuracao(), por: 'ana');

        $this->assertSame(['ana', $local], $partida->ordem());
    }

    #[Test]
    public function participanteLocalExigeEmailValido(): void
    {
        $mesa = $this->mesaCom();

        $this->expectException(RegraViolada::class);
        $mesa->cadastrarParticipanteLocal('Bruno', '', por: 'ana');
    }

    #[Test]
    public function somenteAdminCadastraParticipanteLocal(): void
    {
        $mesa = $this->mesaCom('bruno');

        $this->expectException(RegraViolada::class);
        $mesa->cadastrarParticipanteLocal('Carla', 'carla@exemplo.com', por: 'bruno');
    }

    // Apoio

    private function mesaCom(string ...$membros): Mesa
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio);
        foreach ($membros as $membro) {
            $mesa->adicionarMembro($membro);
        }

        return $mesa;
    }

    private function configuracao(): ConfiguracaoPartida
    {
        return new ConfiguracaoPartida(minimoJogadores: 2, maximoJogadores: 6, prazoVezSegundos: 300);
    }
}
