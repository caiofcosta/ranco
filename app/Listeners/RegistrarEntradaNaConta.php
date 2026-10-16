<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Acesso\RegistrosDeAcesso;
use App\Models\User;
use Illuminate\Auth\Events\Login;

final class RegistrarEntradaNaConta
{
    public function __construct(private readonly RegistrosDeAcesso $registros) {}

    public function handle(Login $evento): void
    {
        if ($evento->guard === 'web' && $evento->user instanceof User) {
            $this->registros->registrar($evento->user, request(), entrada: true);
        }
    }
}
