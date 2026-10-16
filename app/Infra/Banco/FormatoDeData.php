<?php

declare(strict_types=1);

namespace App\Infra\Banco;

/** Formato das colunas DATETIME(3) gravadas pelos repositórios (sempre em UTC). */
final class FormatoDeData
{
    public const PADRAO = 'Y-m-d H:i:s.v';
}
