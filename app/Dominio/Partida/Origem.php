<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

enum Origem: string
{
    case Jogador = 'jogador';
    case Admin = 'admin';
    case Sistema = 'sistema';
}
