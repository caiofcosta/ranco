<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio\Jogos;

use App\Dominio\Jogos\Labirinto\DesenhoLabirinto;
use App\Dominio\RegraViolada;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DesenhoLabirintoTest extends TestCase
{
    #[Test]
    public function rascunhoPodeFicarSemInicioEChegada(): void
    {
        DesenhoLabirinto::validarFormato(DesenhoLabirinto::vazio());

        $this->expectException(RegraViolada::class);
        DesenhoLabirinto::validarPublicacao(DesenhoLabirinto::vazio());
    }

    #[Test]
    public function publicaDesenhoTransitavelComFisicaAtual(): void
    {
        $linhas = DesenhoLabirinto::vazio();
        $linhas[1][1] = 'I';
        $linhas[11][11] = 'C';

        $definicao = DesenhoLabirinto::definicao($linhas);

        $this->assertSame($linhas, $definicao['linhas']);
        $this->assertSame(1, $definicao['formato']);
        $this->assertSame(40, $definicao['tamanho_celula']);
        $this->assertSame(10, $definicao['raio_bolinha']);
        $this->assertSame(120, $definicao['velocidade']);
    }

    /** @param list<string> $linhas */
    #[Test]
    #[DataProvider('desenhosInvalidos')]
    public function impedePublicacaoInvalida(array $linhas): void
    {
        $this->expectException(RegraViolada::class);

        DesenhoLabirinto::validarPublicacao($linhas);
    }

    /** @return array<string, array{list<string>}> */
    public static function desenhosInvalidos(): array
    {
        $valido = DesenhoLabirinto::vazio();
        $valido[1][1] = 'I';
        $valido[11][11] = 'C';

        $bloqueado = $valido;
        $bloqueado[1][2] = '#';
        $bloqueado[2][1] = '#';
        $borda = $valido;
        $borda[0][1] = '.';
        $doisInicios = $valido;
        $doisInicios[2][2] = 'I';
        $duasChegadas = $valido;
        $duasChegadas[2][2] = 'C';
        $simbolo = $valido;
        $simbolo[2][2] = 'X';
        $largura = $valido;
        $largura[2] = '#...#';

        return [
            'sem caminho' => [$bloqueado],
            'borda aberta' => [$borda],
            'dois inicios' => [$doisInicios],
            'duas chegadas' => [$duasChegadas],
            'simbolo invalido' => [$simbolo],
            'largura incorreta' => [$largura],
            'altura incorreta' => [array_slice($valido, 1)],
        ];
    }
}
