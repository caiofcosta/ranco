<?php

declare(strict_types=1);

namespace App\Dominio\Mesa;

enum SituacaoReconhecimento: string
{
    case Pendente = 'pendente';
    case Reconhecida = 'reconhecida';
    case Rejeitada = 'rejeitada';
}
