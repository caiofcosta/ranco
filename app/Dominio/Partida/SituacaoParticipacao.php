<?php

declare(strict_types=1);

namespace App\Dominio\Partida;

enum SituacaoParticipacao: string
{
    case Ativa = 'ativa';
    case Abandonada = 'abandonada';
    case Removida = 'removida';
    case Concluida = 'concluida';
}
