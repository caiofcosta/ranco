<?php

declare(strict_types=1);

namespace App\Dominio\Plataforma;

interface Transacao
{
    /**
     * @template T
     * @param callable(): T $operacao
     * @return T
     */
    public function executar(callable $operacao): mixed;
}
