<?php

declare(strict_types=1);

namespace App\Tom;

/** Frase do Tom já escolhida para a tela, com a pose e a expressão que a acompanham. */
final readonly class Fala
{
    public function __construct(
        public string $texto,
        public string $pose,
        public string $expressao,
    ) {
    }
}
