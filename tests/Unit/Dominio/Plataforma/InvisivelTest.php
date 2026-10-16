<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Plataforma;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Dominio\Social\SituacaoConvite;
use App\Infra\Memoria\PlataformaEmMemoria;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class InvisivelTest extends TestCase
{
    private Plataforma $plataforma;

    protected function setUp(): void
    {
        $this->plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-04 10:00:00')));
    }

    #[Test]
    public function terMesasNaListaNaoImpedeFicarInvisivelMasJogarImpede(): void
    {
        $mesa = $this->plataforma->criarMesa('ana');
        $this->plataforma->ficarInvisivel('ana');
        $this->assertTrue($this->plataforma->estaInvisivel('ana'));
        $this->assertRegra('Fique visível para entrar na mesa.', fn () => $this->plataforma->mesaDoMembro($mesa->id, 'ana'));

        $this->plataforma->ficarVisivel('ana');
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');
        $this->plataforma->aceitarConvite($this->plataforma->convidar($mesa->id, 'ana', 'bruno')->id, 'bruno');
        $this->plataforma->criarPartida($mesa->id, CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo), 'ana');
        $this->plataforma->iniciarPartida($mesa->id, 'ana');
        $this->plataforma->iniciarTentativa($mesa->id, 'ana', static fn (Mesa $m): array => [$m->partidaAtiva(), 'ana']);

        $this->assertRegra('Termine sua jogada e saia da mesa para ficar invisível.', fn () => $this->plataforma->ficarInvisivel('ana'));
        $this->assertFalse($this->plataforma->estaInvisivel('ana'));
    }

    #[Test]
    public function invisivelNaoCriaMesa(): void
    {
        $this->plataforma->ficarInvisivel('ana');

        $this->assertRegra('Fique visível para interagir com outras pessoas.', fn () => $this->plataforma->criarMesa('ana'));
        $this->assertNull(($this->plataforma->mesasAbertasDe('ana')[0] ?? null));
    }

    #[Test]
    public function invisivelRecebeConviteDeMesaMasSoAceitaDepoisDeFicarVisivel(): void
    {
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');
        $mesa = $this->plataforma->criarMesa('ana');
        $this->plataforma->ficarInvisivel('bruno');

        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');
        $this->assertRegra('Fique visível para interagir com outras pessoas.', fn () => $this->plataforma->aceitarConvite($convite->id, 'bruno'));
        $this->assertSame(SituacaoConvite::Pendente, $this->plataforma->convite($convite->id)->situacao);

        $this->plataforma->ficarVisivel('bruno');
        $this->plataforma->aceitarConvite($convite->id, 'bruno');
        $this->assertTrue($this->plataforma->mesa($mesa->id)->membroAtivo('bruno'));
    }

    #[Test]
    public function invisivelNaoPedeNemAceitaAmizade(): void
    {
        $this->plataforma->solicitarAmizade('carla', 'ana');
        $this->plataforma->ficarInvisivel('ana');

        $this->assertRegra('Fique visível para interagir com outras pessoas.', fn () => $this->plataforma->solicitarAmizade('ana', 'bruno'));
        $this->assertRegra('Fique visível para interagir com outras pessoas.', fn () => $this->plataforma->aceitarAmizade('ana', 'carla'));
        $this->assertRegra('Fique visível para interagir com outras pessoas.', fn () => $this->plataforma->aceitarConviteDeAmizade('bruno', 'ana'));
        $this->assertSame([], $this->plataforma->amigosDe('ana'));
    }

    #[Test]
    public function invisivelRecusaConviteDesfazAmizadeEBloqueia(): void
    {
        $this->plataforma->aceitarConviteDeAmizade('ana', 'bruno');
        $this->plataforma->aceitarConviteDeAmizade('ana', 'carla');
        $mesa = $this->plataforma->criarMesa('ana');
        $convite = $this->plataforma->convidar($mesa->id, 'ana', 'bruno');
        $this->plataforma->ficarInvisivel('bruno');
        $this->plataforma->ficarInvisivel('carla');

        $this->plataforma->recusarConvite($convite->id, 'bruno');
        $this->plataforma->desfazerAmizade('bruno', 'ana');
        $this->plataforma->bloquear('carla', 'ana');

        $this->assertSame(SituacaoConvite::Recusado, $this->plataforma->convite($convite->id)->situacao);
        $this->assertNotContains('bruno', $this->plataforma->amigosDe('ana'));
        $this->assertTrue($this->plataforma->bloqueou('carla', 'ana'));
    }

    private function assertRegra(string $mensagem, callable $acao): void
    {
        try {
            $acao();
        } catch (RegraViolada $erro) {
            $this->assertSame($mensagem, $erro->getMessage());

            return;
        }
        $this->fail('Esperava RegraViolada.');
    }
}
