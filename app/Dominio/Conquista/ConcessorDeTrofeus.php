<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use App\Dominio\Mesa\Mesa;

interface ConcessorDeTrofeus
{
    public function concederPendentes(Mesa $mesa): void;
}
