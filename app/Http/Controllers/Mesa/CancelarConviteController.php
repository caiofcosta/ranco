<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Convite\MensagemDoConvite;
use App\Dominio\NaoEncontrado;
use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class CancelarConviteController
{
    use GaranteMembroDaMesa;

    public function __invoke(Plataforma $plataforma, string $id, string $convite): RedirectResponse
    {
        $this->mesaDoMembro($plataforma, $id);
        /** @var User $usuario */
        $usuario = auth()->user();

        try {
            $encontrado = $plataforma->convite($convite);
        } catch (NaoEncontrado) {
            abort(404);
        }

        if ($encontrado->mesaId !== $id) {
            abort(404);
        }

        try {
            $plataforma->cancelarConvite($convite, $usuario->identificadorNoDominio());
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withErrors(['convite' => MensagemDoConvite::de($erro)]);
        }

        return redirect()->route('mesas.mostrar', $id);
    }
}
