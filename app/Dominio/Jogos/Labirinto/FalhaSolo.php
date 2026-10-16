<?php

declare(strict_types=1);

namespace App\Dominio\Jogos\Labirinto;

use DomainException;

final class FalhaSolo extends DomainException
{
    public function __construct(string $mensagem, public readonly string $motivo)
    {
        parent::__construct($mensagem);
    }
}
