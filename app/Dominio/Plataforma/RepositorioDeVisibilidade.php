<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

/** Quem escolheu ficar invisível. Pessoa sem registro é visível. */
interface RepositorioDeVisibilidade
{
    public function invisivel(string $pessoa): bool;

    public function definir(string $pessoa, bool $invisivel): void;
}
