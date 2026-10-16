<?php

declare(strict_types=1);

namespace App\Http\Controllers\Acesso;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RegrasAceitasController
{
    public function __invoke(Request $pedido): Response
    {
        /** @var User $pessoa */
        $pessoa = $pedido->user();
        $aceites = $pessoa->aceites()->orderByDesc('aceito_em')->orderByDesc('id')
            ->get(['versao', 'aceito_em']);

        return response()->view('pages.settings.regras-aceitas', compact('aceites'))
            ->header('Cache-Control', 'no-store, private');
    }
}
