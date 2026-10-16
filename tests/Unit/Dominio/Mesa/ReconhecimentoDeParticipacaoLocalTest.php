<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Mesa;

use App\Dominio\Mesa\SituacaoReconhecimento;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Infra\Memoria\PlataformaEmMemoria;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Suporte\RelogioFalso;

final class ReconhecimentoDeParticipacaoLocalTest extends TestCase
{
    private Plataforma $plataforma;

    protected function setUp(): void
    {
        $this->plataforma = PlataformaEmMemoria::criar(new RelogioFalso(new DateTimeImmutable('2026-10-03 10:00:00')));
    }

    #[Test]
    public function reconhecerLigaAContaEEntraNoHistoricoOficial(): void
    {
        $id = $this->cadastrar('Bruno', 'Bruno@Exemplo.com');

        $this->assertSame([], $this->plataforma->participacoesReconhecidasDe('bruno'));
        $this->assertCount(1, $this->plataforma->participacoesLocaisPendentes('bruno@exemplo.com'));

        $this->plataforma->reconhecerParticipacaoLocal($id, 'bruno', 'bruno@exemplo.com');

        $this->assertSame([$id], $this->plataforma->participacoesReconhecidasDe('bruno'));
        $this->assertSame([], $this->plataforma->participacoesLocaisPendentes('bruno@exemplo.com'));
        $local = $this->plataforma->mesa($this->mesaId($id))->participanteLocal($id);
        $this->assertNotNull($local);
        $this->assertSame(SituacaoReconhecimento::Reconhecida, $local->situacao);
        $this->assertSame('bruno', $local->usuario);
    }

    #[Test]
    public function rejeitarNaoEntraNoHistoricoOficial(): void
    {
        $id = $this->cadastrar('Bruno', 'bruno@exemplo.com');

        $this->plataforma->rejeitarParticipacaoLocal($id, 'bruno@exemplo.com');

        $local = $this->plataforma->mesa($this->mesaId($id))->participanteLocal($id);
        $this->assertNotNull($local);
        $this->assertSame(SituacaoReconhecimento::Rejeitada, $local->situacao);
        $this->assertNull($local->usuario);
        $this->assertSame([], $this->plataforma->participacoesReconhecidasDe('bruno'));
        $this->assertSame([], $this->plataforma->participacoesLocaisPendentes('bruno@exemplo.com'));
    }

    #[Test]
    public function outroEmailNaoReconheceNemRejeita(): void
    {
        $id = $this->cadastrar('Bruno', 'bruno@exemplo.com');

        try {
            $this->plataforma->reconhecerParticipacaoLocal($id, 'carla', 'carla@exemplo.com');
            $this->fail('Outro e-mail não deveria reconhecer.');
        } catch (RegraViolada) {
            $this->addToAssertionCount(1);
        }

        try {
            $this->plataforma->rejeitarParticipacaoLocal($id, 'carla@exemplo.com');
            $this->fail('Outro e-mail não deveria rejeitar.');
        } catch (RegraViolada) {
            $this->addToAssertionCount(1);
        }

        $this->assertCount(1, $this->plataforma->participacoesLocaisPendentes('bruno@exemplo.com'));
        $this->assertSame([], $this->plataforma->participacoesReconhecidasDe('carla'));
    }

    #[Test]
    public function participacaoJaRespondidaNaoMudaDeNovo(): void
    {
        $id = $this->cadastrar('Bruno', 'bruno@exemplo.com');
        $this->plataforma->reconhecerParticipacaoLocal($id, 'bruno', 'bruno@exemplo.com');

        $this->expectException(RegraViolada::class);
        $this->plataforma->rejeitarParticipacaoLocal($id, 'bruno@exemplo.com');
    }

    private function cadastrar(string $nome, string $email): string
    {
        $mesa = $this->plataforma->criarMesa('ana', 'Grupo');

        return $this->plataforma->cadastrarParticipanteLocal($mesa->id, $nome, $email, 'ana');
    }

    private function mesaId(string $idLocal): string
    {
        $mesaId = strstr($idLocal, ':local:', true);

        return $mesaId === false ? '' : $mesaId;
    }
}
