<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

final class ImpedirLoginDeContaBloqueada
{
    public function handle(Request $request, Closure $next): mixed
    {
        $email = mb_strtolower(trim((string) $request->input(Fortify::username())));
        $bloqueado = User::query()
            ->where('email', $email)
            ->whereNotNull('bloqueado_em')
            ->exists();

        if ($bloqueado) {
            throw ValidationException::withMessages([
                Fortify::username() => 'E-mail ou senha incorretos.',
            ]);
        }

        return $next($request);
    }
}
