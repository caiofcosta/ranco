<?php

declare(strict_types=1);

namespace App\Mesa\Tempo;

use App\Dominio\Plataforma\NotificadorDePessoa;

final class NotificadorDePessoaNoLaravel implements NotificadorDePessoa
{
    public function ficouInvisivel(string $pessoa): void
    {
        PessoaFicouInvisivel::dispatch($pessoa);
    }

    public function mesasDaPessoaMudaram(string $pessoa): void
    {
        MesasDaPessoaMudaram::dispatch($pessoa);
    }
}
