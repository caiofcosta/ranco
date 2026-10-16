<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;

/** Conta sem senha (Google ou link) não tem o que confirmar; quem tem senha segue o password.confirm. */
final class ConfirmaSenhaSeTiver
{
    public function __construct(
        private readonly RequirePassword $confirmaSenha,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $usuario = $request->user();

        if (! $usuario instanceof User || ! $usuario->temSenha()) {
            return $next($request);
        }

        return $this->confirmaSenha->handle($request, $next);
    }
}
