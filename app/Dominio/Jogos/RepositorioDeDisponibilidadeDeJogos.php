<?php

declare(strict_types=1);

namespace App\Dominio\Jogos;

interface RepositorioDeDisponibilidadeDeJogos
{
    public function estaDisponivel(string $jogo): bool;
}
