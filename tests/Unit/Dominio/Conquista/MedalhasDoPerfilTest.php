<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Conquista;

use App\Dominio\Conquista\RepositorioDeMedalhas;
use App\Infra\Memoria\PlataformaEmMemoria;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class MedalhasDoPerfilTest extends TestCase
{
    #[Test]
    public function consultaAContaESomenteAsParticipacoesReconhecidasPorEla(): void
    {
        $plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-03 12:00:00')));
        $mesa = $plataforma->criarMesa('admin', 'Grupo');
        $reconhecida = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Ana', 'ana@example.test', 'admin');
        $plataforma->cadastrarParticipanteLocal($mesa->id, 'Ana pendente', 'ana@example.test', 'admin');
        $rejeitada = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Ana rejeitada', 'ana@example.test', 'admin');
        $deBruno = $plataforma->cadastrarParticipanteLocal($mesa->id, 'Bruno', 'bruno@example.test', 'admin');
        $plataforma->reconhecerParticipacaoLocal($reconhecida, 'ana', 'ana@example.test');
        $plataforma->rejeitarParticipacaoLocal($rejeitada, 'ana@example.test');
        $plataforma->reconhecerParticipacaoLocal($deBruno, 'bruno', 'bruno@example.test');
        $medalhas = [[
            'mesa' => 'Grupo',
            'frase' => 'Frase preservada.',
            'concedidaEm' => new DateTimeImmutable('2026-10-01 12:00:00'),
        ]];
        $repositorio = $this->createMock(RepositorioDeMedalhas::class);
        $repositorio->expects($this->once())->method('dasPessoas')
            ->with(['ana', $reconhecida])->willReturn($medalhas);

        $this->assertSame($medalhas, $plataforma->medalhasDe('ana', $repositorio));
    }
}
