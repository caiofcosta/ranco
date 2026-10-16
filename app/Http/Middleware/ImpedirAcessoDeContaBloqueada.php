<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class ImpedirAcessoDeContaBloqueada
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->encerrarSeBloqueado($request)) {
            return $this->redirecionarParaLogin();
        }
        $resposta = $next($request);

        if ($this->encerrarSeBloqueado($request)) {
            return $this->redirecionarParaLogin();
        }

        return $resposta;
    }

    private function encerrarSeBloqueado(Request $request): bool
    {
        $usuario = $request->user();
        if (! $usuario instanceof User || $usuario->bloqueado_em === null) {
            return false;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return true;
    }

    private function redirecionarParaLogin(): Response
    {
        return redirect()->route('login')->withErrors([
            'email' => 'Esta conta está bloqueada.',
        ]);
    }
}
