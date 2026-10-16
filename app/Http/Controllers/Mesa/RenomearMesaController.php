<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mesa;

use App\Dominio\Plataforma\Plataforma;
use App\Dominio\RegraViolada;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RenomearMesaController
{
    use GaranteMembroDaMesa;

    public function __invoke(Request $request, Plataforma $plataforma, string $id): RedirectResponse
    {
        $this->mesaDoMembro($plataforma, $id);
        /** @var User $usuario */
        $usuario = auth()->user();

        $dados = $request->validate([
            'nome' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $plataforma->renomearMesa($id, trim((string) ($dados['nome'] ?? '')), $usuario->identificadorNoDominio());
        } catch (RegraViolada) {
            return redirect()->route('mesas.mostrar', $id);
        }

        return redirect()->route('mesas.mostrar', $id);
    }
}
