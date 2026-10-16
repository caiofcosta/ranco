<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Dominio\Relogio;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/** Relógio controlado pelo teste: o domínio do Stop lê a hora só por ele. */
final class RelogioControlado implements Relogio
{
    public function __construct(private DateTimeImmutable $agora) {}

    public function agora(): DateTimeImmutable
    {
        return $this->agora;
    }

    public function avancar(int $segundos): void
    {
        $this->agora = $this->agora->modify("+{$segundos} seconds");
    }

    public function timestamp(): int
    {
        return CarbonImmutable::instance($this->agora)->getTimestamp();
    }
}
