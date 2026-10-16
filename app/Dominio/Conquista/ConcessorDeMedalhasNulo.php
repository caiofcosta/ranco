<?php

declare(strict_types=1);

namespace App\Dominio\Conquista;

use App\Dominio\Mesa\Mesa;

/** Usado fora do aplicativo (testes em memória): não grava medalha. */
final class ConcessorDeMedalhasNulo implements ConcessorDeMedalhas
{
    public function concederPendentes(Mesa $mesa): void {}
}
