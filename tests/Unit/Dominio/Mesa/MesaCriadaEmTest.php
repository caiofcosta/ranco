<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Mesa;

use App\Dominio\Mesa\Mesa;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class MesaCriadaEmTest extends TestCase
{
    #[Test]
    public function mesaNovaGuardaOInstanteEMesaAntigaContinuaLegivel(): void
    {
        $instante = new DateTimeImmutable('2026-05-01T12:00:00Z');
        $mesa = Mesa::criar('mesa-1', 'ana', new RelogioFalso($instante), 'Grupo');

        $this->assertEquals($instante, $mesa->criadaEm());

        $dados = $mesa->__serialize();
        unset($dados['criadaEm']);
        $antiga = (new \ReflectionClass(Mesa::class))->newInstanceWithoutConstructor();
        $antiga->__unserialize($dados);

        $this->assertNull($antiga->criadaEm());
        $this->assertSame('mesa-1', $antiga->id);
        $this->assertSame('Grupo', $antiga->nome());
    }
}
