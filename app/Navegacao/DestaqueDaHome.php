<?php

declare(strict_types=1);

namespace App\Navegacao;

/** Fonte única do destaque; pode ser trocada por uma consulta ao painel sem mudar a view. */
final class DestaqueDaHome
{
    public function principal(): ?array
    {
        $destaque = config('home-mobile.destaque');

        return is_array($destaque) && ($destaque['ativo'] ?? false) ? $destaque : null;
    }
}
