<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use App\Dominio\Mesa\Mesa;

final class ConcessorDeTrofeusNulo implements ConcessorDeTrofeus
{
    public function concederPendentes(Mesa $mesa): void {}
}
