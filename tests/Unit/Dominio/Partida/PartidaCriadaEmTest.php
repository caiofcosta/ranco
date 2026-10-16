<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Partida;

use App\Dominio\Partida\ConfiguracaoPartida;
use App\Dominio\Partida\Partida;
use App\Dominio\Partida\SituacaoPartida;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class PartidaCriadaEmTest extends TestCase
{
    #[Test]
    public function partidaNovaGuardaOInstanteEPartidaAntigaContinuaLegivel(): void
    {
        $instante = new DateTimeImmutable('2026-05-01T12:00:00.123Z');
        $partida = Partida::criar(new ConfiguracaoPartida(1, 6, 300), 'ana', new RelogioFalso($instante));

        $this->assertEquals($instante, $partida->criadaEm());

        $dados = $partida->__serialize();
        unset($dados['criadaEm']);
        $antiga = (new \ReflectionClass(Partida::class))->newInstanceWithoutConstructor();
        $antiga->__unserialize($dados);

        $this->assertNull($antiga->criadaEm());
        $this->assertSame(SituacaoPartida::EmConfiguracao, $antiga->situacao());
        $this->assertSame('ana', $antiga->admin());
    }
}
