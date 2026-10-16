<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

final class NotificadorDeMesaNulo implements NotificadorDeMesa
{
    public function mesaMudou(string $mesaId): void {}
}
