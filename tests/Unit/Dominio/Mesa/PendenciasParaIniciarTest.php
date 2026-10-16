<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Mesa;

use App\Dominio\Mesa\Mesa;
use App\Dominio\Mesa\PendenciaParaIniciar;
use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\SituacaoPartida;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class PendenciasParaIniciarTest extends TestCase
{
    private RelogioFalso $relogio;

    protected function setUp(): void
    {
        $this->relogio = new RelogioFalso(new DateTimeImmutable('2026-10-07 10:00:00'));
    }

    #[Test]
    public function semPartidaFaltaEscolherOJogo(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio);

        $this->assertSame([PendenciaParaIniciar::EscolherJogo], $mesa->pendenciasParaIniciar());
        $this->assertFalse($mesa->podeIniciarPartida());
    }

    #[Test]
    public function abaixoDoMinimoFaltamJogadores(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio);
        $mesa->novaPartida($this->configuracao(), por: 'ana');

        $this->assertSame([PendenciaParaIniciar::MinimoDeJogadores], $mesa->pendenciasParaIniciar());
        $this->assertFalse($mesa->podeIniciarPartida());
    }

    #[Test]
    public function lugaresVaziosNaoImpedemIniciar(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio);
        $mesa->adicionarMembro('bia');
        $mesa->configurarLugares(3, 'ana');
        $mesa->novaPartida($this->configuracao(), por: 'ana');

        $this->assertSame([], $mesa->pendenciasParaIniciar());
        $this->assertTrue($mesa->podeIniciarPartida());

        $mesa->iniciarPartida('ana');

        $this->assertSame(SituacaoPartida::EmAndamento, $mesa->partidaAtiva()->situacao());
    }

    #[Test]
    public function sentadoQueNaoEntrouNaPartidaContaEEntraAoComecar(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio);
        $mesa->novaPartida($this->configuracao(), por: 'ana');
        $mesa->adicionarMembro('bruno');

        $this->assertSame([], $mesa->pendenciasParaIniciar());
        $this->assertTrue($mesa->podeIniciarPartida());

        $mesa->iniciarPartida('ana');

        $this->assertSame(['ana', 'bruno'], $mesa->partidaAtiva()->ordem());
    }

    #[Test]
    public function partidaEmAndamentoNaoTemPendenciaNemPodeIniciarDeNovo(): void
    {
        $mesa = Mesa::criar('mesa-1', 'ana', $this->relogio);
        $mesa->adicionarMembro('bia');
        $mesa->novaPartida($this->configuracao(), por: 'ana')->iniciar(por: 'ana');

        $this->assertSame([], $mesa->pendenciasParaIniciar());
        $this->assertFalse($mesa->podeIniciarPartida());
    }

    private function configuracao(): ConfiguracaoPartida
    {
        return new ConfiguracaoPartida(minimoJogadores: 2, maximoJogadores: 6, prazoVezSegundos: 300);
    }
}
