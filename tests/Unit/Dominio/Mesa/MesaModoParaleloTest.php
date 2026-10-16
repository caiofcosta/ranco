<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use App\Dominio\RegraViolada;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class MesaModoParaleloTest extends TestCase
{
    /** @return array{0: Mesa, 1: string} */
    private function mesaComLocal(): array
    {
        $mesa = Mesa::criar('mesa-1', 'ana', new RelogioFalso(new DateTimeImmutable('2026-10-04 10:00:00')), 'Grupo');
        $mesa->adicionarMembro('bruno');
        $local = $mesa->cadastrarParticipanteLocal('Carla', 'carla@example.test', 'ana');

        return [$mesa, $local];
    }

    #[Test]
    public function partidaEmParaleloDeixaOParticipanteLocalForaSemTiraLoDaMesa(): void
    {
        [$mesa, $local] = $this->mesaComLocal();

        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo), 'ana');

        $this->assertSame(['ana', 'bruno'], $partida->ordem());
        $this->assertTrue($mesa->membroAtivo($local));
    }

    #[Test]
    public function participanteLocalNaoPodeSerAdmitidoNoParalelo(): void
    {
        [$mesa, $local] = $this->mesaComLocal();
        $mesa->novaPartida(CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo), 'ana');

        $this->expectException(RegraViolada::class);
        $this->expectExceptionMessage('Partida em paralelo não aceita participante local.');
        $mesa->admitirNaPartida($local, 'ana');
    }

    #[Test]
    public function sequencialContinuaComOParticipanteLocal(): void
    {
        [$mesa, $local] = $this->mesaComLocal();

        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), 'ana');

        $this->assertSame(['ana', 'bruno', $local], $partida->ordem());
        $this->assertSame(ModoExecucao::Sequencial, $partida->modo());
    }

    #[Test]
    public function reconfigurar_preserva_partida_e_respeita_locais_em_cada_modo(): void
    {
        [$mesa, $local] = $this->mesaComLocal();
        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, null), 'ana');
        $mesa->reconfigurarPartida(CatalogoDeJogos::configuracao('labirinto', 90, null, 3, ModoExecucao::Paralelo), 'ana');
        $this->assertSame($partida, $mesa->partidaAtiva());
        $this->assertCount(1, $mesa->partidas());
        $this->assertSame(['ana', 'bruno'], $partida->ordem());
        $this->assertTrue($mesa->membroAtivo($local));
        $mesa->reconfigurarPartida(CatalogoDeJogos::configuracao('labirinto', 90, null, 3), 'ana');
        $this->assertSame(['ana', 'bruno', $local], $partida->ordem());
    }

    #[Test]
    public function labirintoAceitaOsDoisModosComSequencialComoPadrao(): void
    {
        $this->assertSame([ModoExecucao::Sequencial, ModoExecucao::Paralelo], CatalogoDeJogos::modos(CatalogoDeJogos::LABIRINTO));
        $this->assertSame([], CatalogoDeJogos::modos('jogo-inexistente'));
        $this->assertSame(
            ModoExecucao::Paralelo,
            CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo)->modo,
        );
    }
}
