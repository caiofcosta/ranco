<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

/** Como o jogo compara resultados brutos: pontos (maior é melhor) ou tempo (menor é melhor). */
enum Criterio: string
{
    case MaiorMelhor = 'maior_melhor';
    case MenorMelhor = 'menor_melhor';
}
