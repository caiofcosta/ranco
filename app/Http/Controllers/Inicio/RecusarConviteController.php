<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inicio;

use App\Convite\MensagemDoConvite;
use App\Dominio\NaoEncontrado;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class RecusarConviteController
{
    public function __invoke(Plataforma $plataforma, string $convite): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = auth()->user();

        try {
            $plataforma->recusarConvite($convite, $usuario->identificadorNoDominio());
        } catch (NaoEncontrado) {
            abort(404);
        } catch (RegraViolada $erro) {
            return redirect()->route('dashboard')->withErrors(['convite' => MensagemDoConvite::de($erro)]);
        }

        return redirect()->route('dashboard');
    }
}
