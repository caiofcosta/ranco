<?php

declare(strict_types=1);

namespace App\Http\Controllers\Jogos;

use Illuminate\Http\Response;

final class MostrarJogoIndisponivelController
{
    public function __invoke(string $jogo): Response
    {
        return response()->view('jogos.indisponivel', ['jogo' => $jogo], 404);
    }
}
