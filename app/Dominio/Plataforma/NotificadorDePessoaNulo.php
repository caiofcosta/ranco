<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

final class NotificadorDePessoaNulo implements NotificadorDePessoa
{
    public function ficouInvisivel(string $pessoa): void {}

    public function mesasDaPessoaMudaram(string $pessoa): void {}
}
