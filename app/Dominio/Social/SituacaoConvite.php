<?php

declare(strict_types=1);

namespace App\Dominio\Social;

enum SituacaoConvite: string
{
    case Pendente = 'pendente';
    case Aceito = 'aceito';
    case Recusado = 'recusado';
    case Cancelado = 'cancelado';
}
