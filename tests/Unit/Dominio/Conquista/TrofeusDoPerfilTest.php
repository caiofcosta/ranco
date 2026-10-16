<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Conquista;

use App\Dominio\Conquista\RepositorioDeTrofeus;
use App\Infra\Memoria\PlataformaEmMemoria;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class TrofeusDoPerfilTest extends TestCase
{
    #[Test]
    public function consultaSomenteAContaEAsParticipacoesReconhecidasPorEla(): void
    {
        $plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-04 12:00:00')));
        $mesa = $plataforma->criarMesa('admin');
        $reconhecida = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Ana', 'ana@example.test', 'admin');
        $plataforma->cadastrarParticipanteLocal($mesa->id, 'Pendente', 'ana@example.test', 'admin');
        $rejeitada = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Rejeitada', 'ana@example.test', 'admin');
        $outra = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Bruno', 'bruno@example.test', 'admin');
        $plataforma->reconhecerParticipacaoLocal($reconhecida, 'ana', 'ana@example.test');
        $plataforma->rejeitarParticipacaoLocal($rejeitada, 'ana@example.test');
        $plataforma->reconhecerParticipacaoLocal($outra, 'bruno', 'bruno@example.test');
        $resultado = [['pessoa' => $reconhecida, 'tipo' => 'vitoria', 'mesa' => 'Grupo', 'frase' => null,
            'concedidoEm' => new DateTimeImmutable('2026-10-04 12:00:00')]];
        $repositorio = $this->createMock(RepositorioDeTrofeus::class);
        $repositorio->expects($this->once())->method('dasPessoas')->with(['ana', $reconhecida])->willReturn($resultado);

        $this->assertSame($resultado, $plataforma->trofeusDe('ana', $repositorio));
    }
}
