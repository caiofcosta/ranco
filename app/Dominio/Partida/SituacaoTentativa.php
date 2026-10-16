<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

enum SituacaoTentativa: string
{
    case EmAndamento = 'em_andamento';
    case Concluida = 'concluida';
    case Interrompida = 'interrompida';
}
