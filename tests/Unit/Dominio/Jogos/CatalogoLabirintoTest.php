<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Jogos;

use App\Dominio\Jogos\Labirinto\CatalogoDeMapasLabirinto;
use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Jogos\Labirinto\RepositorioMapasLabirinto;
use App\Dominio\Plataforma\Transacao;
use App\Dominio\RegraViolada;
use App\Dominio\Relogio;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogoLabirintoTest extends TestCase
{
    private function catalogo(RepositorioMapasLabirinto $repositorio): CatalogoDeMapasLabirinto
    {
        $transacao = new class implements Transacao
        {
            public function executar(callable $operacao): mixed
            {
                return $operacao();
            }
        };
        $relogio = new class implements Relogio
        {
            public function agora(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-05T12:00:00Z');
            }
        };

        return new CatalogoDeMapasLabirinto($repositorio, $transacao, $relogio);
    }

    private function mapa(): array
    {
        return ['id' => 1, 'nome' => 'Mapa', 'linhas' => DesenhoLabirinto::vazio(), 'ativo' => false, 'revisao' => 2, 'versao_publicada' => null, 'usos_em_rodadas' => null];
    }

    #[Test]
    public function dominio_impede_ativar_rascunho(): void
    {
        $repo = $this->createMock(RepositorioMapasLabirinto::class);
        $repo->method('bloquear')->willReturn($this->mapa());
        $repo->expects($this->never())->method('gravar');
        $this->expectException(RegraViolada::class);
        $this->expectExceptionMessage('Publique uma versão');
        $this->catalogo($repo)->alternarAtivo(1, 2);
    }

    #[Test]
    public function dominio_impede_sobrescrever_revisao_antiga(): void
    {
        $repo = $this->createMock(RepositorioMapasLabirinto::class);
        $repo->method('bloquear')->willReturn($this->mapa());
        $repo->expects($this->never())->method('gravar');
        $this->expectException(RegraViolada::class);
        $this->expectExceptionMessage('outra aba');
        $this->catalogo($repo)->salvar(1, 1, 'Mapa', DesenhoLabirinto::vazio(), false, null);
    }

    #[Test]
    public function dominio_recusa_publicar_sem_caminho_antes_de_gravar(): void
    {
        $repo = $this->createMock(RepositorioMapasLabirinto::class);
        $repo->expects($this->never())->method('gravar');
        $linhas = DesenhoLabirinto::vazio();
        $linhas[1] = '#I#C........#';
        $linhas[2] = '###.........#';
        $this->expectException(RegraViolada::class);
        $this->expectExceptionMessage('Não existe caminho');
        $this->catalogo($repo)->salvar(null, 0, 'Isolado', $linhas, true, null);
    }
}
