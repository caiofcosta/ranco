<?php

declare(strict_types=1);

namespace App\Infra\Memoria;

use App\Dominio\Plataforma\Transacao;

/** Sem banco não há o que desfazer: apenas executa a operação. */
final class TransacaoEmMemoria implements Transacao
{
    public function executar(callable $operacao): mixed
    {
        return $operacao();
    }
}
