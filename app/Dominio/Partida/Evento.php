<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

use DateTimeImmutable;

final readonly class Evento
{
    public function __construct(
        public TipoEvento $tipo,
        public Origem $origem,
        public DateTimeImmutable $em,
        public ?string $participante = null,
        public ?int $rodada = null,
    ) {
    }
}
