<?php

declare(strict_types=1);

namespace App\Infra\Banco;

use App\Dominio\Plataforma\Transacao;
use Illuminate\Support\Facades\DB;

/** Usa a transação do Laravel, que aninha com savepoints se já houver uma aberta. */
final class TransacaoDoLaravel implements Transacao
{
    public function executar(callable $operacao): mixed
    {
        return DB::transaction($operacao);
    }
}
