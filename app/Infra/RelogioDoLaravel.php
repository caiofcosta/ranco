<?php

declare(strict_types=1);

namespace App\Infra;

use App\Dominio\Relogio;
use DateTimeImmutable;

final class RelogioDoLaravel implements Relogio
{
    public function agora(): DateTimeImmutable
    {
        return now('UTC')->toDateTimeImmutable();
    }
}
