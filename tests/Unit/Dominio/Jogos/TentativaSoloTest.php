<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Jogos;

use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\Jogos\Labirinto\FalhaSolo;
use App\Dominio\Jogos\Labirinto\TentativaSolo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TentativaSoloTest extends TestCase
{
    private function desenho(): array
    {
        $linhas = DesenhoLabirinto::vazio();
        $linhas[1] = '#IC.........#';

        return DesenhoLabirinto::definicao($linhas);
    }

    #[Test]
    public function primeiro_e_menor_sao_recordes_mas_empate_nao(): void
    {
        $this->assertTrue(TentativaSolo::recorde(300, null));
        $this->assertTrue(TentativaSolo::recorde(300, 400));
        $this->assertFalse(TentativaSolo::recorde(300, 300));
        $this->assertFalse(TentativaSolo::recorde(400, 300));
    }

    #[Test]
    public function aceita_limites_fisico_e_de_expiracao_e_repeticao_apos_expirar(): void
    {
        $minimo = TentativaSolo::minimo($this->desenho());
        TentativaSolo::validar($minimo, null, 1, $this->desenho());
        $limite = TentativaSolo::VALIDADE_SEGUNDOS;
        TentativaSolo::validar($limite * 1000, null, $limite, $this->desenho());
        TentativaSolo::validar(300, 300, $limite + 3600, $this->desenho());
        $this->assertGreaterThan(1, $minimo);
    }

    #[Test]
    #[DataProvider('invalidos')]
    public function rejeita_tempo_incompativel_ou_expirado(int $tempo, int $decorrido): void
    {
        $this->expectException(FalhaSolo::class);
        TentativaSolo::validar($tempo, null, $decorrido, $this->desenho());
    }

    public static function invalidos(): array
    {
        $limite = TentativaSolo::VALIDADE_SEGUNDOS;

        return [[1, 10], [0, 10], [-1, 10], [1001, 0], [$limite * 1000 + 1, $limite], [300, $limite + 1]];
    }

    #[Test]
    public function nao_permite_trocar_tempo_concluido(): void
    {
        try {
            TentativaSolo::validar(400, 300, 90000, $this->desenho());
            $this->fail('Deveria recusar alteração.');
        } catch (FalhaSolo $erro) {
            $this->assertSame('conflito', $erro->motivo);
        }
    }
}
