<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Mesa;

use App\Dominio\Jogos\CatalogoDeJogos;
use App\Dominio\Mesa\Mesa;
use App\Dominio\Partida\ModoExecucao;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class QuemJogaAVezTest extends TestCase
{
    #[Test]
    public function aContaJogaPorSiEAAdminTambemPelosLocais(): void
    {
        [$mesa, $local] = $this->mesaComLocal();

        $this->assertTrue($mesa->podeJogarPor('bruno', 'bruno'));
        $this->assertTrue($mesa->podeJogarPor('ana', $local));
        $this->assertFalse($mesa->podeJogarPor('bruno', $local));
        $this->assertFalse($mesa->podeJogarPor('ana', 'bruno'));
    }

    #[Test]
    public function noSequencialSoJogaQuemTemAVezOuAAdminPeloLocal(): void
    {
        [$mesa, $local] = $this->mesaComLocal();
        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), 'ana');
        $this->assertNull($mesa->porQuemJoga('ana'));
        $this->assertContains($local, $partida->ordem());
        $partida->iniciar('ana');

        $vez = $partida->vezAtual();
        foreach (['ana', 'bruno'] as $conta) {
            $esperado = $mesa->podeJogarPor($conta, $vez) ? $vez : null;
            $this->assertSame($esperado, $mesa->porQuemJoga($conta));
        }
        $this->assertNull($mesa->porQuemJoga('ninguem'));
    }

    #[Test]
    public function noParaleloCadaUmJogaPorSiAteConcluirARodada(): void
    {
        [$mesa] = $this->mesaComLocal();
        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null, ModoExecucao::Paralelo), 'ana');
        $partida->iniciar('ana');

        $this->assertSame('bruno', $mesa->porQuemJoga('bruno'));
        $partida->iniciarTentativa('bruno');
        $partida->concluirTentativa('bruno', 9000);
        $this->assertNull($mesa->porQuemJoga('bruno'));
        $this->assertSame('ana', $mesa->porQuemJoga('ana'));
    }

    #[Test]
    public function podeIniciarSoComOMinimoDeJogadores(): void
    {
        $sozinha = Mesa::criar('mesa-1', 'ana', new RelogioFalso(new DateTimeImmutable('2026-10-04 10:00:00')), 'Grupo');
        $this->assertFalse($sozinha->novaPartida(CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), 'ana')->podeIniciar());

        $mesa = Mesa::criar('mesa-2', 'ana', new RelogioFalso(new DateTimeImmutable('2026-10-04 10:00:00')), 'Grupo');
        $mesa->adicionarMembro('bruno');
        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao(CatalogoDeJogos::LABIRINTO, null, null, null), 'ana');
        $this->assertTrue($partida->podeIniciar());

        $partida->iniciar('ana');
        $this->assertFalse($partida->podeIniciar());
    }

    #[Test]
    public function distingueVezAbertaDeTentativaEmCursoSemPerderAInformacaoNaPausa(): void
    {
        [$mesa] = $this->mesaComLocal();
        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, null), 'ana');
        $this->assertNull($partida->participanteJogando());
        $partida->iniciar('ana');
        $vez = $partida->vezAtual();
        $this->assertNull($partida->participanteJogando());
        $partida->iniciarTentativa($vez);
        $this->assertSame($vez, $partida->participanteJogando());
        $partida->pausar('ana');
        $this->assertSame($vez, $partida->participanteJogando());
        $partida->concluirTentativa($vez, 10000);
        $this->assertNull($partida->participanteJogando());
        $partida->encerrar('ana');
        $this->assertNull($partida->participanteJogando());
    }

    #[Test]
    public function paraleloNaoTemParticipanteJogandoAVez(): void
    {
        [$mesa] = $this->mesaComLocal();
        $partida = $mesa->novaPartida(CatalogoDeJogos::configuracao('labirinto', null, null, null, ModoExecucao::Paralelo), 'ana');
        $partida->iniciar('ana');
        $partida->iniciarTentativa('ana');
        $this->assertNull($partida->participanteJogando());
    }

    /** @return array{0: Mesa, 1: string} */
    private function mesaComLocal(): array
    {
        $mesa = Mesa::criar('mesa-1', 'ana', new RelogioFalso(new DateTimeImmutable('2026-10-04 10:00:00')), 'Grupo');
        $mesa->adicionarMembro('bruno');
        $local = $mesa->cadastrarParticipanteLocal('Carla', 'carla@example.test', 'ana');

        return [$mesa, $local];
    }
}
