<?php

declare(strict_types=1);

namespace App\Infra\Memoria;

use App\Dominio\Plataforma\RepositorioDeVisibilidade;

final class RepositorioDeVisibilidadeEmMemoria implements RepositorioDeVisibilidade
{
    /** @var array<string, true> */
    private array $invisiveis = [];

    public function invisivel(string $pessoa): bool
    {
        return isset($this->invisiveis[$pessoa]);
    }

    public function definir(string $pessoa, bool $invisivel): void
    {
        if ($invisivel) {
            $this->invisiveis[$pessoa] = true;
        } else {
            unset($this->invisiveis[$pessoa]);
        }
    }
}
