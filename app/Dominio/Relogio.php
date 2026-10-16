<?php

declare(strict_types=1);

namespace App\Dominio;

use DateTimeImmutable;

interface Relogio
{
    public function agora(): DateTimeImmutable;
}
