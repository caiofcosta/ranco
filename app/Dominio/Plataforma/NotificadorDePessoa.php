<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

/** Avisa as abas abertas da própria pessoa. A entrega ao navegador fica fora do domínio. */
interface NotificadorDePessoa
{
    public function ficouInvisivel(string $pessoa): void;

    /** A lista "Suas mesas" da pessoa mudou: convite, participação ou visibilidade. */
    public function mesasDaPessoaMudaram(string $pessoa): void;
}
