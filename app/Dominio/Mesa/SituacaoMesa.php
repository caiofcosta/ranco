<?php

declare(strict_types=1);

namespace App\Dominio\Mesa;

enum SituacaoMesa: string
{
    case Aberta = 'aberta';
    case Encerrada = 'encerrada';
}
