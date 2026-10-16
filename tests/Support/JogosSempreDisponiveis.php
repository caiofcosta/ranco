<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Dominio\Jogos\RepositorioDeDisponibilidadeDeJogos;

final class JogosSempreDisponiveis implements RepositorioDeDisponibilidadeDeJogos
{
    public function estaDisponivel(string $jogo): bool
    {
        return true;
    }
}
