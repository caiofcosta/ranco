<?php

declare(strict_types=1);

namespace App\Dominio;

use RuntimeException;

/** Outra operação alterou o mesmo dado ao mesmo tempo; a ação pode ser repetida. */
final class ConflitoDeConcorrencia extends RuntimeException
{
}
