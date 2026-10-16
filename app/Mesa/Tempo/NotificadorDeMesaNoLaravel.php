<?php

declare(strict_types=1);

namespace App\Mesa\Tempo;

use App\Dominio\Plataforma\NotificadorDeMesa;

final class NotificadorDeMesaNoLaravel implements NotificadorDeMesa
{
    public function mesaMudou(string $mesaId): void
    {
        MesaMudou::dispatch($mesaId);
    }
}
