<?php

declare(strict_types=1);

namespace Tests\Suporte;

use DateTimeImmutable;
use App\Dominio\Relogio;

final class RelogioFalso implements Relogio
{
    public function __construct(private DateTimeImmutable $agora)
    {
    }

    public function agora(): DateTimeImmutable
    {
        return $this->agora;
    }

    public function avancar(int $segundos): void
    {
        $this->agora = $this->agora->modify("+{$segundos} seconds");
    }
}
