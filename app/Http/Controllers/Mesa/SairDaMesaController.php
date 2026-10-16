<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Mesa\NomeDaMesa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class SairDaMesaController
{
    use GaranteMembroDaMesa;

    public function formulario(Plataforma $plataforma, string $id): View
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $mesa = $this->mesaDoMembro($plataforma, $id);
        if ($mesa->admin() === $usuario->identificadorNoDominio()) {
            abort(404);
        }

        return view('mesa.sair', [
            'id' => $mesa->id,
            'nome' => NomeDaMesa::de($mesa),
        ]);
    }

    public function sair(Plataforma $plataforma, string $id): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = auth()->user();
        $this->mesaDoMembro($plataforma, $id);

        try {
            $plataforma->abandonarMesa($id, $usuario->identificadorNoDominio());
        } catch (RegraViolada) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('dashboard');
    }
}
