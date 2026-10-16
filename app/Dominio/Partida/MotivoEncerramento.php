<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

enum MotivoEncerramento: string
{
    case Normal = 'normal';
    case Desistencia = 'desistencia';
    case Remocoes = 'remocoes';
    case EncerradaPeloAdmin = 'encerrada_pelo_admin';
    case EncerramentoDaMesa = 'encerramento_da_mesa';
    case AbandonoDoAdmin = 'abandono_do_admin';
}
