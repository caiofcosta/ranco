<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

/** Avisa que o estado de uma mesa mudou. A entrega ao navegador fica fora do domínio. */
interface NotificadorDeMesa
{
    public function mesaMudou(string $mesaId): void;
}
