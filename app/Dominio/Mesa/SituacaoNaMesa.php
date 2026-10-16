<?php

declare(strict_types=1);

namespace App\Dominio\Mesa;

/** Situação da participação de uma pessoa na mesa. */
enum SituacaoNaMesa: string
{
    case Ativa = 'ativa';
    case Abandonada = 'abandonada';
    case Removida = 'removida';
}
