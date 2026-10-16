<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RemoverDaPartidaController
{
    use GaranteMembroDaMesa;

    public function __invoke(Request $request, Plataforma $plataforma, string $id): RedirectResponse
    {
        $this->mesaDoAdmin($plataforma, $id);
        /** @var User $usuario */
        $usuario = auth()->user();
        $dados = $request->validate(['participante' => ['required', 'string']]);

        try {
            $plataforma->removerDaPartida($id, $dados['participante'], $usuario->identificadorNoDominio());
        } catch (RegraViolada $erro) {
            return redirect()->route('mesas.mostrar', $id)->withErrors(['partida' => $erro->getMessage()]);
        }

        return redirect()->route('mesas.mostrar', $id);
    }
}
