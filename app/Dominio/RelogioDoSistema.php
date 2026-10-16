<?php

declare(strict_types=1);

namespace App\Dominio;

use DateTimeImmutable;
use DateTimeZone;

final class RelogioDoSistema implements Relogio
{
    public function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
