<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jogos\JogosDisponiveis;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class GaranteJogoDisponivel
{
    public function handle(Request $request, Closure $next, string $jogo): Response
    {
        if (! app(JogosDisponiveis::class)->estaDisponivel($jogo)) {
            return response()->view('jogos.indisponivel', ['jogo' => $jogo], 404);
        }

        return $next($request);
    }
}
