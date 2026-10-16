<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Plataforma;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\SituacaoMesa;
use App\Dominio\Mesa\SituacaoReconhecimento;
use App\Dominio\Partida\SituacaoPartida;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\CodigoDaRegra;
use App\Dominio\RegraViolada;
use App\Dominio\Social\SituacaoConvite;
use App\Infra\Memoria\PlataformaEmMemoria;
use Tests\Suporte\RelogioFalso;

final class PlataformaTest extends TestCase
{
    private Plataforma $plataforma;

    protected function setUp(): void
    {
        $this->plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-01 10:00:00')));
    }

    // Mesa única

    #[Test]
    public function criarMesaContaParaOLimiteDeCinco(): void
    {
        for ($i = 0; $i < Plataforma::LIMITE_DE_MESAS; $i++) {
            $this->plataforma->criarMesa('ana');
        }

        $this->assertSame(0, $this->plataforma->vagasDeMesa('ana'));
        try {
            $this->plataforma->criarMesa('ana');
            $this->fail('A sexta mesa deveria ser recusada.');
        } catch (RegraViolada $erro) {
            $this->assertSame(CodigoDaRegra::LimiteDeMesas, $erro->codigo);
        }
        $this->assertCount(Plataforma::LIMITE_DE_MESAS, $this->plataforma->mesasAbertasDe('ana'));
    }

    #[Test]
    public function depoisDeAbandonarAMesaOUsuarioFicaLivre(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->plataforma->abandonarMesa($mesa->id, 'ana');

        $outra = $this->plataforma->criarMesa('ana');

        $this->assertSame($outra, ($this->plataforma->mesasAbertasDe('ana')[0] ?? null));
    }

    // Convite de amizade por e-mail

    #[Test]
    public function aceitarConviteDeAmizadeTornaOsDoisAmigos(): void
    {
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');

        $this->assertTrue($this->plataforma->saoAmigos('ana', 'bruno'));
        $this->assertSame(['bruno'], $this->plataforma->amigosDe('ana'));
        $this->assertSame(['ana'], $this->plataforma->amigosDe('bruno'));
        $this->assertSame([], $this->plataforma->pedidosDeAmizadeRecebidos('bruno'));
    }

    #[Test]
    public function aceitarConviteDeQuemJaEAmizadeNaoFazNada(): void
    {
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');

        $this->assertSame(['bruno'], $this->plataforma->amigosDe('ana'));
    }

    #[Test]
    public function conviteDeAmizadeRespeitaOBloqueio(): void
    {
        $this->plataforma->bloquear('bruno', 'ana');

        $this->expectException(RegraViolada::class);
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');
    }

    #[Test]
    public function ninguemAceitaOProprioConviteDeAmizade(): void
    {
        $this->expectException(RegraViolada::class);
        $this->plataforma->aceitarConviteDeAmizade('ana', 'ana');
    }

    // Amizade e convite

    #[Test]
    public function conviteExigeAmizadeAceita(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->plataforma->solicitarAmizade('ana', 'bruno');

        $this->expectException(RegraViolada::class);
        $this->plataforma->convidar($mesa->id, 'ana', 'bruno');
    }

    #[Test]
    public function aceitarAmizadeNaoColocaNinguemNaMesa(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');

        $this->assertFalse($mesa->membroAtivo('bruno'));
        $this->assertNull(($this->plataforma->mesasAbertasDe('bruno')[0] ?? null));
    }

    #[Test]
    public function conviteAceitoColocaAPessoaNaMesa(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');
        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');

        $this->plataforma->aceitarConvite($convite->id, 'bruno');

        $this->assertSame(SituacaoConvite::Aceito, $convite->situacao);
        $this->assertSame($mesa, ($this->plataforma->mesasAbertasDe('bruno')[0] ?? null));
    }

    #[Test]
    public function listaConvitesPendentesDaMesa(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');
        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');

        $this->assertSame([$convite], $this->plataforma->convitesPendentesDaMesa($mesa->id));
    }

    #[Test]
    public function somenteOAdminConvida(): void
    {
        $mesa = $this->mesaDaAnaCom('bruno');
        $this->tornarAmigos('bruno', 'carla');

        $this->expectException(RegraViolada::class);
        $this->plataforma->convidar($mesa->id, 'bruno', 'carla');
    }

    #[Test]
    public function aceitarConviteNaoConsomeOLimiteDeMesasAdministradas(): void
    {
        for ($i = 0; $i < Plataforma::LIMITE_DE_MESAS; $i++) {
            $this->plataforma->criarMesa('bruno');
        }
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');
        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');

        $this->plataforma->aceitarConvite($convite->id, 'bruno');

        $this->assertContains($mesa, $this->plataforma->mesasAbertasDe('bruno'));
        $this->assertSame(0, $this->plataforma->vagasDeMesa('bruno'));
    }

    #[Test]
    public function desfazerAmizadeCancelaConvitesPendentes(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');
        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');

        $this->plataforma->desfazerAmizade('bruno', 'ana');

        $this->assertSame(SituacaoConvite::Cancelado, $convite->situacao);
    }

    #[Test]
    public function desfazerAmizadeNaoRemoveParticipacaoExistente(): void
    {
        $mesa = $this->mesaDaAnaCom('bruno');

        $this->plataforma->desfazerAmizade('ana', 'bruno');

        $this->assertTrue($mesa->membroAtivo('bruno'));
    }

    #[Test]
    public function novoConviteDepoisDeDesfazerExigeNovaAmizade(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');
        $this->plataforma->desfazerAmizade('ana', 'bruno');

        $this->expectException(RegraViolada::class);
        $this->plataforma->convidar($mesa->id, 'ana', 'bruno');
    }

    // Bloqueio

    #[Test]
    public function bloqueioCancelaConvitesPendentesNasDuasDirecoes(): void
    {
        $mesaDaAna = $this->plataforma->criarMesa('ana');
        $mesaDoBruno = $this->plataforma->criarMesa('bruno');
        $this->tornarAmigos('ana', 'bruno');
        $daAna = $this->plataforma->convidar($mesaDaAna->id, 'ana', 'bruno');
        $doBruno = $this->plataforma->convidar($mesaDoBruno->id, 'bruno', 'ana');

        $this->plataforma->bloquear('ana', 'bruno');

        $this->assertSame(SituacaoConvite::Cancelado, $daAna->situacao);
        $this->assertSame(SituacaoConvite::Cancelado, $doBruno->situacao);
    }

    #[Test]
    public function bloqueadoNaoEnviaPedidoDeAmizade(): void
    {
        $this->plataforma->bloquear('ana', 'bruno');

        $this->expectException(RegraViolada::class);
        $this->plataforma->solicitarAmizade('bruno', 'ana');
    }

    #[Test]
    public function bloqueadoNaoEnviaConvite(): void
    {
        $mesaDoBruno = $this->plataforma->criarMesa('bruno');
        $this->tornarAmigos('ana', 'bruno');
        $this->plataforma->bloquear('ana', 'bruno');

        $this->expectException(RegraViolada::class);
        $this->plataforma->convidar($mesaDoBruno->id, 'bruno', 'ana');
    }

    #[Test]
    public function bloqueioNaoRemoveParticipacaoExistente(): void
    {
        $mesa = $this->mesaDaAnaCom('bruno');

        $this->plataforma->bloquear('ana', 'bruno');

        $this->assertTrue($mesa->membroAtivo('bruno'));
    }

    // Encerramento da mesa

    #[Test]
    public function mesaEncerradaCancelaConvitesPendentes(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');
        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');

        $this->plataforma->abandonarMesa($mesa->id, 'ana');

        $this->assertSame(SituacaoConvite::Cancelado, $convite->situacao);
    }

    #[Test]
    public function adminAbandonarAMesaLiberaOsDemais(): void
    {
        $mesa = $this->mesaDaAnaCom('bruno');

        $this->plataforma->abandonarMesa($mesa->id, 'ana');

        $this->assertNull(($this->plataforma->mesasAbertasDe('bruno')[0] ?? null));
        $this->plataforma->criarMesa('bruno');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function encerrarAMesaLiberaOsDemaisParaCriarOutra(): void
    {
        $mesa = $this->mesaDaAnaCom('bruno');

        $this->plataforma->encerrarMesa($mesa->id, 'ana');

        $this->assertNull(($this->plataforma->mesasAbertasDe('bruno')[0] ?? null));
        $this->plataforma->criarMesa('bruno');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function encerrarAMesaCancelaConvitesPendentes(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', 'bruno');
        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');

        $this->plataforma->encerrarMesa($mesa->id, 'ana');

        $this->assertSame(SituacaoConvite::Cancelado, $convite->situacao);
    }

    #[Test]
    public function criarMesaGuardaONome(): void
    {
        $mesa = $this->plataforma->criarMesa('ana', 'Clube do ranço');

        $this->assertSame('Clube do ranço', $mesa->nome());
    }

    // Participação local

    #[Test]
    public function participacaoLocalSoEntraNoHistoricoDepoisDeReconhecida(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $idLocal = $mesa->cadastrarParticipanteLocal('Bruno', 'Bruno@Exemplo.com', por: 'ana');

        $this->assertSame([], $this->plataforma->participacoesReconhecidasDe('bruno'));
        $this->assertCount(1, $this->plataforma->participacoesLocaisPendentes('bruno@exemplo.com'));

        $this->plataforma->reconhecerParticipacaoLocal($idLocal, 'bruno', 'bruno@exemplo.com');

        $this->assertSame([$idLocal], $this->plataforma->participacoesReconhecidasDe('bruno'));
        $this->assertSame([], $this->plataforma->participacoesLocaisPendentes('bruno@exemplo.com'));
    }

    #[Test]
    public function participacaoLocalPodeSerRejeitada(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $idLocal = $mesa->cadastrarParticipanteLocal('Bruno', 'bruno@exemplo.com', por: 'ana');

        $this->plataforma->rejeitarParticipacaoLocal($idLocal, 'bruno@exemplo.com');

        $this->assertSame(SituacaoReconhecimento::Rejeitada, $mesa->participantesLocais()[0]->situacao);
        $this->assertSame([], $this->plataforma->participacoesReconhecidasDe('bruno'));
    }

    #[Test]
    public function somenteODonoDoEmailReconheceAParticipacaoLocal(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $idLocal = $mesa->cadastrarParticipanteLocal('Bruno', 'bruno@exemplo.com', por: 'ana');

        $this->expectException(RegraViolada::class);
        $this->plataforma->reconhecerParticipacaoLocal($idLocal, 'carla', 'carla@exemplo.com');
    }

    #[Test]
    public function participanteLocalNaoOcupaAMesaUnicaDeNinguem(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $mesa->cadastrarParticipanteLocal('Bruno', 'bruno@exemplo.com', por: 'ana');

        $this->plataforma->criarMesa('bruno');

        $this->assertNotNull(($this->plataforma->mesasAbertasDe('bruno')[0] ?? null));
    }

    // Partida

    #[Test]
    public function criarPartidaColocaOsMembrosESoOAdminCria(): void
    {
        $mesa = $this->mesaDaAnaCom('bruno');
        $this->plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', null, null, null), 'ana');

        $partida = $this->plataforma->mesa($mesa->id)->partidaAtiva();
        $this->assertNotNull($partida);
        $this->assertSame(['ana', 'bruno'], $partida->ordem());
        $this->assertSame('labirinto', $partida->configuracao()->jogo);

        $this->expectException(RegraViolada::class);
        $this->plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', null, null, null), 'bruno');
    }

    #[Test]
    public function iniciarPartidaRespeitaOMinimoDeJogadores(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', null, null, null), 'ana');

        $this->expectException(RegraViolada::class);
        $this->plataforma->iniciarPartida($mesa->id, 'ana');
    }

    #[Test]
    public function encerrarPartidaNaoEncerraAMesa(): void
    {
        $mesa = $this->mesaDaAnaCom('bruno');
        $this->plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao('labirinto', null, null, null), 'ana');
        $this->plataforma->iniciarPartida($mesa->id, 'ana');
        $this->plataforma->encerrarPartida($mesa->id, 'ana');

        $mesa = $this->plataforma->mesa($mesa->id);
        $this->assertSame(SituacaoMesa::Aberta, $mesa->situacao());
        $this->assertNull($mesa->partidaAtiva());
        $this->assertSame(SituacaoPartida::Encerrada, $mesa->partidas()[0]->situacao());
        $this->assertNull($mesa->partidas()[0]->vencedor());
    }

    // Apoio

    private function tornarAmigos(string $a, string $b): void
    {
        $this->plataforma->solicitarAmizade($a, $b);
        $this->plataforma->aceitarAmizade($b, $a);
    }

    private function mesaDaAnaCom(string $convidado): Mesa
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->tornarAmigos('ana', $convidado);
        $convite = $this->plataforma->convidar($mesa->id, 'ana', $convidado);
        $this->plataforma->aceitarConvite($convite->id, $convidado);

        return $mesa;
    }
}
