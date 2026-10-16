<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use App\Dominio\Mesa\Mesa;

/** Concede as medalhas das partidas que acabaram com vencedor e ainda não têm a da pessoa. */
interface ConcessorDeMedalhas
{
    public function concederPendentes(Mesa $mesa): void;
}
