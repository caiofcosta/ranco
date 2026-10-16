<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final class DespausarPartidaController
{
    use GaranteMembroDaMesa;

    public function __invoke(Plataforma $plataforma, string $id): RedirectResponse
    {
        $this->mesaDoAdmin($plataforma, $id);
        /** @var User $usuario */
        $usuario = auth()->user();

        try {
            $plataforma->despausarPartida($id, $usuario->identificadorNoDominio());
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withErrors(['partida' => $erro->getMessage()]);
        }

        return redirect()->route('mesas.mostrar', $id);
    }
}
