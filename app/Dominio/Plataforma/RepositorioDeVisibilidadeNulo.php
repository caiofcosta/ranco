<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

use LogicException;

final class RepositorioDeVisibilidadeNulo implements RepositorioDeVisibilidade
{
    public function invisivel(string $pessoa): bool
    {
        return false;
    }

    public function definir(string $pessoa, bool $invisivel): void
    {
        throw new LogicException('Visibilidade não configurada.');
    }
}
